<?php

// Functions to support offline jobs ($offline_job_queue = true)
// Offline jobs require a frequent cron/scheduled task to run tools/offline_jobs.php

/**
 * Adds a job to the job_queue table.
 *
 * @param  string $type
 * @param  array $job_data
 * @param  string $user
 * @param  string $time
 * @param  string $success_text
 * @param  string $failure_text
 * @param  string $job_code
 * @param  int    $priority
 * @return string|integer ID of newly created job or error text
 */
function job_queue_add($type = "", $job_data = array(), $user = "", $time = "", $success_text = "", $failure_text = "", $job_code = "", $priority = null)
{
    global $lang, $userref;
    if ($time == "") {
        $time = date('Y-m-d H:i:s');
    }
    if ($type == "") {
        return false;
    }
    if ($user == "") {
        $user = isset($userref) ? $userref : 0;
    }
    // Assign priority based on job type if not explicitly passed
    if (!is_int_loose($priority)) {
        $priority = get_job_type_priority($type);
    }

    $job_data_json = json_encode($job_data, JSON_UNESCAPED_SLASHES); // JSON_UNESCAPED_SLASHES is needed so we can effectively compare jobs

    if ($job_code == "") {
        // Generate a code based on job data to avoid incorrect duplicate job detection
        $job_code = $type . "_" . substr(md5(serialize($job_data)), 10);
    }

    // Check for existing job matching
    $existing_user_jobs = job_queue_get_jobs($type, STATUS_ACTIVE, "", $job_code);
    if (count($existing_user_jobs) > 0) {
            return $lang["job_queue_duplicate_message"];
    }
    ps_query("INSERT INTO job_queue (type,job_data,user,start_date,status,success_text,failure_text,job_code, priority) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)", array("s",$type,"s",$job_data_json,"i",$user,"s",$time,"i",STATUS_ACTIVE,"s",$success_text,"s",$failure_text,"s",$job_code,"i",(int)$priority));
    return sql_insert_id();
}

/**
 * Update the data/status/time of a job queue record.
 *
 * @param  integer $ref
 * @param  array $job_data - pass empty array to leave unchanged
 * @param  string $newstatus
 * @param  string $newtime
 * @return void
 */
function job_queue_update($ref, $job_data = array(), $newstatus = "", $newtime = "", $priority = null)
{
    $update_sql = array();
    $parameters = array();
    if (count($job_data) > 0) {
        $update_sql[] = "job_data = ?";
        $parameters = array_merge($parameters, array("s",json_encode($job_data)));
    }
    if ($newtime != "") {
        $update_sql[] = "start_date = ?";
        $parameters = array_merge($parameters, array("s",$newtime));
    }
    if ($newstatus != "") {
        $update_sql[] = "status = ?";
        $parameters = array_merge($parameters, array("i",$newstatus));
    }
    if (is_int_loose($priority)) {
        $update_sql[] = "priority = ?";
        $parameters = array_merge($parameters, array("i",(int)$priority));
    }
    if (count($update_sql) == 0) {
        return false;
    }

    $sql = "UPDATE job_queue SET " . implode(",", $update_sql) . " WHERE ref = ?";
    $parameters = array_merge($parameters, array("i",$ref));
    ps_query($sql, $parameters);
}

/**
 * Delete a job queue entry if user owns job or user is admin
 *
 * @param  mixed $ref
 * @return void
 */
function job_queue_delete($ref)
{
    global $userref;

    $job_data = job_queue_get_job($ref);

    if (empty($job_data)) {
        return;
    }

    // Delete log file if it exists
    $log_path = get_job_queue_log_path($job_data["type"], $job_data["job_code"], $job_data["ref"]);

    if (file_exists($log_path)) {
        unlink($log_path);
    }

    // If triggerable job, log as deleted by user
    if (triggerable_job_check($job_data["type"])) {
        log_activity("Deleted {$job_data['type']} job $ref",
                        LOG_CODE_JOB_DELETED, null, 'job_queue', null, null, null, "", null, true);
    }
 
    $query = "DELETE FROM job_queue WHERE ref= ?";
    $parameters = array("i",$ref);
    if (!checkperm('a') && !php_sapi_name() == "cli") {
        $query .= " AND user = ?";
        $parameters = array_merge($parameters, array("i",$userref));
    }
    ps_query($query, $parameters);
}

/**
 * Gets a list of offline jobs
 *
 * @param  string $type         Job type, can be a comma separated list of job types
 * @param  string $status       Job status - see definitions.php
 * @param  int    $user         Job user
 * @param  string $job_code     Unique job code
 * @param  string $job_order_by Column to order by - default is priority
 * @param  string $job_sort     Sort order - ASC or DESC
 * @param  string $find         Search jobs for this string
 * @param  bool   $returnsql    Return raw SQL
 * @param  int    $maxjobs      Maximum number of jobs to return
 * @param  bool   $overdue      Only return overdue jobs?
 * @param array $find_by_job_ref Find queued jobs by their ref
 * @return mixed                Resulting array of requests or an SQL query object
 */
function job_queue_get_jobs($type = "", $status = -1, $user = "", $job_code = "", $job_order_by = "priority", $job_sort = "asc", $find = "", $returnsql = false, int $maxjobs = 0, bool $overdue = false, array $find_by_job_ref = [])
{
    global $userref;
    $condition = array();
    $parameters = array();
    if ($type != "") {
        $types = explode(",", $type);
        $condition[] = " type IN (" . ps_param_insert(count($types)) . ")";
        $parameters = array_merge($parameters, ps_param_fill($types, "s"));
    }
    if (PHP_SAPI != 'cli') {
        // Don't show certain jobs if not accessing via CLI
        $hiddentypes = array();
        $hiddentypes[] = "delete_file";
        $condition[] = " type NOT IN (" . ps_param_insert(count($hiddentypes)) . ")";
        $parameters = array_merge($parameters, ps_param_fill($hiddentypes, "s"));
    }

    if ((int)$status > -1) {
        $condition[] = " status = ? ";
        $parameters = array_merge($parameters, array("i",(int)$status));
    }

    if ($overdue) {
        $condition[] = " start_date <= ? ";
        $parameters = array_merge($parameters, array("s",date('Y-m-d H:i:s')));
    }

    if ((int)$user > 0) {
        // Has user got access to see this user's jobs?
        if ($user == $userref || checkperm_user_edit($user)) {
            $condition[] = " user = ?";
            $parameters = array_merge($parameters, array("i",(int)$user));
        } elseif (isset($userref)) {
            // Only show own jobs
            $condition[] = " user = ?";
            $parameters = array_merge($parameters, array("i",(int)$userref));
        } else {
            // No access - return empty array
            return array();
        }
    } else {
        // Requested jobs for all users - only possible for cron or system admin, set condition otherwise
        if (PHP_SAPI != "cli" && !checkperm('a')) {
            if (isset($userref)) {
                // Only show own jobs
                $condition[] = " user = ?";
                $parameters = array_merge($parameters, array("i",(int)$userref));
            } else {
                // No access - return nothing
                return array();
            }
        }
    }

    if ($job_code != "") {
        $condition[] = " job_code = ?";
        $parameters = array_merge($parameters, array("s",$job_code));
    }

    if ($find != "") {
        $find = '%' . $find . '%';
        $condition[] = " (j.ref LIKE ? OR j.job_data LIKE ? OR j.success_text LIKE ? OR j.failure_text LIKE ? OR j.user LIKE ? OR u.username LIKE ? OR u.fullname LIKE ?)";
    }

    $find_by_job_ref = array_values(array_filter($find_by_job_ref, is_positive_int_loose(...)));
    if ($find_by_job_ref !== []) {
        $condition[] = 'j.ref IN (' . ps_param_insert(count($find_by_job_ref)) . ')';
        $parameters = array_merge($parameters, ps_param_fill($find_by_job_ref, 'i'));
    }

    $conditional_sql = "";
    if (count($condition) > 0) {
        $conditional_sql = " WHERE " . implode(" AND ", $condition);
    }

    // Check order by value is valid
    if (!in_array(strtolower($job_order_by), array("priority", "ref", "type", "fullname", "status", "start_date"))) {
        $job_order_by = "priority";
    }

    // Check sort value is valid
    if (!in_array(strtolower($job_sort), array("asc", "desc"))) {
        $job_sort = "ASC";
    }

    $limit = "";
    if ($maxjobs > 0) {
        $limit = " LIMIT ?";
        $parameters = array_merge($parameters, ["i",$maxjobs]);
    }
    $sql = "SELECT j.ref, j.type, REPLACE(REPLACE(j.job_data,'\r',' '),'\n',' ') AS job_data, j.user, j.status, j.start_date, j.success_text, j.failure_text,j.job_code, j.priority, u.username, u.fullname FROM job_queue j LEFT JOIN user u ON u.ref = j.user " . $conditional_sql . " ORDER BY " . $job_order_by . " " . $job_sort . ", start_date " . $job_sort . $limit;
    if ($returnsql) {
        return new PreparedStatementQuery($sql, $parameters);
    }
    return ps_query($sql, $parameters);
}

/**
 * Get details of specified offline job
 *
 * @param  int $job identifier
 * @return array
 */
function job_queue_get_job($ref)
{
    $sql = "SELECT j.ref, j.type, j.job_data, j.user, j.status, '' as progress, j.start_date, j.priority, j.success_text, j.failure_text, j.job_code, u.username, u.fullname FROM job_queue j LEFT JOIN user u ON u.ref = j.user WHERE j.ref = ?";
    $job_data = ps_query($sql, array("i",(int)$ref));

    return (is_array($job_data) && count($job_data) > 0) ? $job_data[0] : array();
}

/**
 * Delete all jobs in the specified state
 *
 * @param  int $status to purge, whole queue will be purged if not set
 * @return void
 */
function job_queue_purge($status = 0)
{
    $deletejobs = job_queue_get_jobs('', $status == 0 ? '' : $status);
    if (count($deletejobs) > 0) {

        foreach ($deletejobs as $deletejob) {
            $log_path = get_job_queue_log_path($deletejob['type'], $deletejob['job_code'], $deletejob["ref"]);
            
            if (file_exists($log_path)) {
                unlink($log_path);
            }

            // If triggerable job, log as deleted by user
            if (triggerable_job_check($deletejob["type"])) {
                log_activity("Deleted {$deletejob['type']} job {$deletejob['ref']}",
                                LOG_CODE_JOB_DELETED, null, 'job_queue', null, null, null, "", null, true);
            }
        }

        $deletejobs_sql = job_queue_get_jobs('', $status == 0 ? '' : $status, "", "", "priority", "asc", "", true);

        ps_query(
            "DELETE FROM job_queue 
                WHERE ref IN 
                    (SELECT jobs.ref FROM 
                        ( " . $deletejobs_sql->sql . ") AS jobs)",
            $deletejobs_sql->parameters
        );
    }
}

/**
* Run offline job
*
* @param  array    $job                 Metadata of the queued job as returned by job_queue_get_jobs()
* @param  boolean  $clear_process_lock  Clear process lock for this job
*
* @return string   'Process lock', 'Error' or 'Complete'.
*/
function job_queue_run_job($job, $clear_process_lock): string
{
    global $offline_job_list;

    // Runs offline job using defined job handler
    $job_type = $job['type'];
    $job_code = $job["job_code"];
    $jobref  = $job["ref"];
    $job_data = json_decode($job["job_data"], true);
    
    $log_file = null;

    if (!empty($job_data) && triggerable_job_check($job_type)) {
        $log_file_path = get_job_queue_log_path($job_type, $job_code, $jobref);

        if($log_file_path === false) {
            logScript("[job_handler] Error generating path to log offline job");
        } else {
            $log_file = fopen($log_file_path, 'w');
            
            if ($log_file === false) {
                $log_file = null;
            }
        }
    }
   
    $job_user = $job["user"];
    if (!isset($job_user) || $job_user == 0 || $job_user == "") {
        $logmessage = " - Job could not be run as no user was supplied #{$jobref}";
        logScript("[job_handler] " . $logmessage, $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return 'Error';
    }

    $jobuserdata = get_user($job_user);
    if (!$jobuserdata) {
        $logmessage = " - Job #{$jobref} could not be run as invalid user ref #{$job_user} was supplied.";
        logScript("[job_handler] " . $logmessage, $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return 'Error';
    }
    setup_user($jobuserdata);
    $job_success_text = $job["success_text"];
    $job_failure_text = $job["failure_text"];

    // Variable used to avoid spinning off offline jobs from an already existing job.
    // Example: create_previews() is using extract_text() and both can run offline.
    global $offline_job_in_progress, $plugins;
    $offline_job_in_progress = false;

    if (is_process_lock('job_' . $jobref) && !$clear_process_lock) {
        $logmessage =  " - Process lock for job #{$jobref}";
        logScript("[job_handler] " . $logmessage, $log_file);
        return 'Process lock';
    } elseif ($clear_process_lock) {
        $logmessage =  " - Clearing process lock for job #{$jobref}";
        logScript("[job_handler] " . $logmessage, $log_file);
        clear_process_lock("job_{$jobref}");
    }

    set_process_lock('job_' . $jobref);

    $logmessage =  "Running job #" . $jobref;
    logScript("[job_handler] " . $logmessage, $log_file);

    $logmessage =  " - Looking for " . __DIR__ . "/job_handlers/" . $job_type . ".php";
    logScript("[job_handler] " . $logmessage);

    if (file_exists(__DIR__ . "/job_handlers/" . $job_type . ".php")) {
        $logmessage = " - Attempting to run job #" . $jobref . " using handler " . $job_type;
        logScript("[job_handler] " . $logmessage, $log_file);
        job_queue_update($jobref, $job_data, STATUS_INPROGRESS);
        $offline_job_in_progress = true;
        include __DIR__ . "/job_handlers/" . $job_type . ".php";
    } else {
        // Check for handler in plugin
        $offline_plugins = $plugins;

        // Include plugins for this job user's group
        $group_plugins = ps_query("SELECT name, config, config_json, disable_group_select FROM plugins WHERE inst_version >= 0 AND disable_group_select = 0 AND find_in_set(?,enabled_groups) ORDER BY priority", array("i",$jobuserdata["usergroup"]), "plugins");
        foreach ($group_plugins as $group_plugin) {
            include_plugin_config($group_plugin['name'], $group_plugin['config'], $group_plugin['config_json']);
            register_plugin($group_plugin['name']);
            register_plugin_language($group_plugin['name']);
            $offline_plugins[] = $group_plugin['name'];
        }

        foreach ($offline_plugins as $plugin) {
            if (file_exists(__DIR__ . "/../plugins/" . $plugin . "/job_handlers/" . $job_type . ".php")) {
                $logmessage = " - Attempting to run job #" . $jobref . " using handler " . $job_type;
                logScript("[job_handler] " . $logmessage, $log_file);
                job_queue_update($jobref, $job_data, STATUS_INPROGRESS);
                $offline_job_in_progress = true;
                include __DIR__ . "/../plugins/" . $plugin . "/job_handlers/" . $job_type . ".php";
                break;
            }
        }
    }

    if (!$offline_job_in_progress) {
        $logmessage = "Unable to find handlerfile: " . $job_type;
        logScript("[job_handler] " . $logmessage, $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR, date('Y-m-d H:i:s'));
    }

    $logmessage =  " - Finished job #" . $jobref;
    logScript("[job_handler] " . $logmessage, $log_file);

    clear_process_lock('job_' . $jobref);
    return 'Complete';
}

/**
 * Get the default priority for a given job type
 *
 * @param  string $type      Name of job type e.g. 'collection_download'
 *
 * @return int
 */
function get_job_type_priority($type = "")
{
    if (trim($type) != "") {
        switch (trim($type)) {
            case 'collection_download':
            case 'create_download_file':
            case 'config_export':
            case 'csv_metadata_export':
                return JOB_PRIORITY_USER;
                break;

            case 'create_previews':
            case 'extract_text':
            case 'replace_batch_local':
            case 'create_alt_file':
            case 'delete_file':
            case 'update_resource':
            case 'upload_processing':
                return JOB_PRIORITY_SYSTEM;
                break;

            default:
                return JOB_PRIORITY_SYSTEM;
                break;
        }
    }
    return JOB_PRIORITY_SYSTEM;
}

/**
 * Build and ensure a writable log file path for a queued job
 *
 * @param string $type     Job category or queue type used as a subdirectory name
 * @param string $job_code Job identifier used in the log filename
 * @param string $job_ref  Job reference value used in the log filename
 *
 * @return string|false Full path to the log file on success, or false on failure
 */
function get_job_queue_log_path(string $type = "", string $job_code = "", string $job_ref = ""): string|false
{

    if ($type === "" || $job_code === "" || $job_ref === "" 
            || !triggerable_job_check($type)) {
        return false;
    }

    // Build path for logging
    $log_path = get_temp_dir() . "/offline_job_logs/" . $type . "/";
    if (!is_dir($log_path)) {
        $log_path_created = mkdir($log_path, 0770, true);
        if (!$log_path_created) {
            return false;
        } else {
            chmod($log_path, 0770);
        }
    }

    return $log_path . $job_ref . "_" . $job_code . ".log";
}

/**
 * Resolve a queued job reference to its corresponding log file path
 *
 * @param int $job_ref Job reference
 *
 * @return string|false Full path to the job log file on success, or false on failure
 */
function get_job_queue_log_path_by_ref(int $job_ref = 0): string|false
{
    if ($job_ref <= 0) {
        return false;
    }

    $job_data = job_queue_get_job($job_ref);

    if (!is_array($job_data) || empty($job_data)) {
        return false;
    }

    return get_job_queue_log_path($job_data["type"], $job_data["job_code"], $job_data["ref"]);

}

/**
 * Scan a log file from the end and return the most recent line matching a pattern.
 *
 * The file is read in fixed-size blocks starting from EOF and scanned backwards
 * line-by-line for the first line that matches the supplied regular expression
 * (by default, lines containing "[PROGRESS]").
 *
 * On the first read pass, the function also captures the last non-empty line in
 * the file (closest to EOF), regardless of whether it matches the pattern.
 *
 * @param string $path       Path to the log file to scan
 * @param string $pattern    Regular expression used to match progress lines
 * @param int    $block_size Number of bytes to read per backward scan iteration
 *
 * @return array found: string|null     The most recent line matching $pattern, or null if none found
 *                                      or if the file could not be read
 *               last_line: string|null The last non-empty line in the file, or null if unavailable
 */
function get_job_progress_from_log_file(string $path = "", string $pattern = "/\[PROGRESS\]/i", int $block_size = 65536): array
{
    $fp = @fopen($path, 'rb');

    if (!$fp) {
        return ['found' => null, 'last_line' => null];
    }

    if (fseek($fp, 0, SEEK_END) === -1) {
        fclose($fp);
        return ['found' => null, 'last_line' => null];
    }

    $position  = ftell($fp);
    $carry     = '';
    $last_line  = null;
    $first_pass = true;

    while ($position > 0) {
        $read_size = ($position >= $block_size) ? $block_size : $position;
        $position -= $read_size;
        
        fseek($fp, $position);
        
        $chunk = fread($fp, $read_size);

        // Combine with carried partial line
        $buffer = $chunk . $carry;

        // Split into lines
        $lines = preg_split("/\r\n|\n|\r/", $buffer);

        // The first element may be incomplete (its beginning lies in an earlier block)
        $carry = array_shift($lines);

        if ($first_pass) {
            // Capture the last non-empty line (closest to EOF)
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if ($lines[$i] !== '') {
                    $last_line = $lines[$i];
                    break;
                }
            }
            $first_pass = false;
        }

        // Scan lines backward (from newest to oldest)
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if ($line === '') continue;
            if (@preg_match($pattern, $line)) {
                fclose($fp);
                return ['found' => $line, 'last_line' => $last_line];
            }
        }
    }

    // Handle the very first (oldest) line if any partial remains
    if ($carry !== '') {
        if ($last_line === null && $carry !== '') {
            $last_line = $carry;
        }
        if (@preg_match($pattern, $carry)) {
            fclose($fp);
            return ['found' => $carry, 'last_line' => $last_line];
        }
    }

    fclose($fp);
    return ['found' => null, 'last_line' => $last_line];

}

/**
 * Determine whether the current user is allowed to manually trigger background jobs.
 * The system must have config $offline_job_queue set to true and the user must 
 * have 'a', 'f*', 't' and 'v' permissions as well as edit access to all workflow states.
 *
 * @return bool true if the user satisfies all required permissions and workflow access, false otherwise
 */
function job_trigger_permission_check(): bool
{
    global $offline_job_queue, $userref;

    $all_wf_states = get_workflow_states();
    $editable_wf_states = array_column(get_editable_states($userref), 'id');

    sort($all_wf_states);
    sort($editable_wf_states);

    $access_to_all_wf_states = ($all_wf_states === $editable_wf_states);

    return $offline_job_queue && checkperm('a') && $access_to_all_wf_states && checkperm('t') && checkperm('v') && checkperm('f*');
}

/**
 * Determine whether a job type is user triggerable.
 * This can stop system jobs from creating unnecessary log files.
 *
 * @return bool true if job is user triggerable, so logging is allowed
 */
function triggerable_job_check(string $type = ""): bool
{
    global $offline_job_list;

    // Build list of offline jobs - including from plugins
    $offline_job_list_full = $offline_job_list;
    $offline_job_list_hook = hook('addtriggerablejob', '', [], true);

    if (!empty($offline_job_list_hook)) {
        $offline_job_list_full = array_merge($offline_job_list, $offline_job_list_hook);
    }

    if ($type === "") {
        return false;
    }
    
    // Check if type is user triggerable - this may be amended in future to allow some system jobs to create log files etc.
    return in_array($type, array_values(array_filter(array_column($offline_job_list_full, 'script_name'))));
}
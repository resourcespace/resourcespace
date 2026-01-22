<?php

/*
Create a zip file with system configuration and selected data
Requires the following:-

$job_data["exporttables"] - Array of table information to export
$job_data["obfuscate"] -  Whether table data should be obfuscated or not
*/
global $baseurl, $baseurl_short, $userref, $offline_job_delete_completed, $lang,$mysql_bin_path, $mysql_server, $mysql_db,$mysql_username,$mysql_password,$scramble_key, $system_download_config, $system_download_config_force_obfuscation;
$exporttables   = $job_data["exporttables"];
$obfuscate      = ($system_download_config_force_obfuscation || $job_data["obfuscate"] == "true");
$userref        = $job_data["userref"];
$separatesql    = $job_data["separatesql"] == "true";
$path           = $mysql_bin_path . "/mysqldump";
$cmd_db_pass    = $mysql_password === '' ? '' : '-p' . escapeshellarg($mysql_password);

if (!$system_download_config) {
    // Not permitted but shouldn't ever occur. Update job queue
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    $message = $lang["exportfailed"] . " - " . str_replace("[config_option]", "\$system_download_config", $lang["error_check_config"]);
    message_add($job["user"], $message, "", 0);
    exit();
}

$jobuser = get_user($userref);
if (is_array($jobuser)) {
    $jobusername = $jobuser["username"];
} else {
    $joberror = true;
}

$jobsuccess = false;
if (!isset($joberror)) {
    $randstring = md5(rand() . microtime());
    $dumppath = get_temp_dir(false, md5($userref . $randstring . $scramble_key)) . "/mysql";
    $zippath = get_temp_dir(false, 'user_downloads');
    mkdir($dumppath, 0777, true);

    $zipfile = $zippath . "/" . $userref . "_" . md5($jobusername . $randstring . $scramble_key) . ".zip";
    $zip = new ZipArchive();
    $zip->open($zipfile, ZIPARCHIVE::CREATE);
    $zip->addFile(__DIR__ . "/../../include/config.php", "config.php");

    foreach ($exporttables as $exporttable => $exportoptions) {
        echo "Exporting table " . $exporttable . "\n";
        $dumpfile = $separatesql ? $dumppath . "/" . $exporttable . ".sql" : $dumppath . "/resourcespace.sql";

        run_command(
            "{$path} -h db_host -u db_user {$cmd_db_pass} --no-tablespaces --no-data db_name export_table >> dump_file",
            false,
            [
                'db_host' => $mysql_server,
                'db_user' => $mysql_username,
                'db_name' => $mysql_db,
                'export_table' => $exporttable,
                'dump_file' => $dumpfile,
            ]
        );

        $sql = "SET sql_mode = '';\n"; // Ensure that any old values that may not now be valid are still accepted into new DB
        $output = fopen($dumpfile, 'a');
        fwrite($output, $sql);
        fclose($output);

        // Get data
        $exportcondition = isset($exportoptions["exportcondition"]) ? $exportoptions["exportcondition"] : "";
        $datarows = ps_query("SELECT * FROM " . $exporttable . " " . $exportcondition, array());

        if (count($datarows) > 0) {
            // Call function to scramble the data based on per table configuration
            array_walk($datarows, 'alter_data', (isset($exportoptions["scramble"]) && $obfuscate) ? $exportoptions["scramble"] : array());

            // Get columns to insert
            $columns = array_keys($datarows[0]);

            $sql = "";
            foreach ($datarows as $datarow) {
                $datarow = array_map("safe_export", $datarow);
                $sql .= "INSERT INTO " . $exporttable . " (" . implode(",", $columns) . ") VALUES (" . implode(",", $datarow) . ");\n";
            }

            $output = fopen($dumpfile, 'a');
            fwrite($output, $sql);
            fclose($output);
        }

        if ($separatesql) {
            $zip->addFile($dumpfile, "mysql/" . $exporttable . ".sql");
        }
    }

    if (!$separatesql) {
        $zip->addFile($dumpfile, "mysql/resourcespace.sql");
    }

    $zip->close();

    # Log this
    log_activity($lang["exportdata"], LOG_CODE_DOWNLOADED);
    debug("Job handler 'config_export' created zip download file {$dumpfile}");

    if (file_exists($zipfile)) {
        $download_url = $baseurl_short . "pages/download.php?userfile=" . $userref . "_" . $randstring . ".zip";
        $message = $lang["exportcomplete"];
        message_add($job["user"], $message, $download_url, 0);
        if ($offline_job_delete_completed) {
            job_queue_delete($jobref);
        } else {
            job_queue_update($jobref, $job_data, STATUS_COMPLETE);
        }

        $delete_job_data = array();
        $delete_job_data["file"] = $zipfile;
        $delete_date = date('Y-m-d H:i:s', time() + (60 * 60 * 24)); // Delete these after 1 day
        $job_code = md5($zipfile);
        job_queue_add("delete_file", $delete_job_data, "", $delete_date, "", "", $job_code);
        $jobsuccess = true;
    }
}

if (!$jobsuccess) {
    // Job failed, update job queue
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    $message = $lang["exportfailed"];
    message_add($job["user"], $message, "", 0);
}

unlink($dumpfile);

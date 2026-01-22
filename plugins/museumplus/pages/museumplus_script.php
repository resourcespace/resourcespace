<?php
if(PHP_SAPI != 'cli') { exit('Access denied'); }
$museumplus_rs_root = __DIR__ . '/../../../';
include $museumplus_rs_root . 'include/boot.php';

$help_text = <<<'HELP'
NAME
    museumplus_script - MuseumPlus admin

SYNOPSIS
    php /path/to/plugins/museumplus/pages/museumplus_script.php [OPTIONS]

DESCRIPTION
    A tool to help administrators manage resources associated with MuseumPlus records.

OPTIONS SUMMARY

    -c, --clear-lock        Clear the lock after a failed run
    --filter                Filter the resources that should be processed. Available options:
                            * new_and_changed_associations - this will process resources that have just been assocciated 
                                with a module or that had their association changed since the last time they've been validated.
                            * byref - this will take as argument a list of resource IDs. See examples.
    -h, --help              Display this help text and exit.
    --revalidate            This will (force) revalidate, even when the association is already determined to be valid.

EXAMPLES
    # Run script as a fallback (to catch any resources that for some reason haven't triggered an ad-hoc sync)
    php plugins/museumplus/pages/museumplus_script.php

    # Revalidate resource IDs 121 and 234 
    php plugins/museumplus/pages/museumplus_script.php --revalidate --filter byref:121,234

    # Revalidate resources that have changed since the last time they've been validated 
    php plugins/museumplus/pages/museumplus_script.php --revalidate --filter new_and_changed_associations


HELP;
logScript('[museumplus] Initiating MuseumPlus script process...');
$mplus_script_start_time = microtime(true);
set_time_limit($cron_job_time_limit);
logScript("[museumplus] Set script maximum execution time to '{$cron_job_time_limit}' seconds");

// Log in the specified directory (new files get created each time)
$mplus_log_file = null;
if('' != trim($museumplus_log_directory))
    {
    $museumplus_script_log_path = sprintf('%s%smplus_script_log_%s.log', $museumplus_log_directory, DIRECTORY_SEPARATOR, date('Y_m_d-H_i'));
    $museumplus_enable_script_log = false;

    if(is_dir($museumplus_log_directory))
        {
        $museumplus_enable_script_log = true;
        }
    elseif(mkdir($museumplus_log_directory, 0755, true))
        {
        $museumplus_enable_script_log = true;
        logScript("[museumplus] Created log directory: '{$museumplus_log_directory}'");
        }
    else
        {
        logScript("[museumplus][warn] Unable to create log directory: '{$museumplus_log_directory}'");
        }

    if($museumplus_enable_script_log)
        {
        $mplus_log_file = fopen($museumplus_script_log_path, 'ab');
        if($mplus_log_file === false)
            {
            logScript("[museumplus][warn] Unable to create log file: '{$museumplus_script_log_path}'");
            }
        else
            {
            logScript("[museumplus] Logging script events in file: '{$museumplus_script_log_path}'");
            }
        }
    // Cleaning up old log files is done by the cron_copy_hitcount hook!
    }

// Truncate museumplus_log table at regular intervals as configured
$mplus_last_log_truncate = get_sysvar(MPLUS_LAST_LOG_TRUNCATE, false);
if($mplus_last_log_truncate !== false)
    {
    $mplus_today_date = new DateTime();
    $date_diff = $mplus_today_date->diff(DateTime::createFromFormat('Y-m-d', $mplus_last_log_truncate));
    if($date_diff->days > $museumplus_truncate_log_interval)
        {
        ps_query('TRUNCATE museumplus_log');
        set_sysvar(MPLUS_LAST_LOG_TRUNCATE, date('Y-m-d'));
        logScript('[museumplus] Truncate museumplus_log table', $mplus_log_file);
        }
    }
else
    {
    set_sysvar(MPLUS_LAST_LOG_TRUNCATE, date('Y-m-d'));
    }



// Script options @see https://www.php.net/manual/en/function.getopt.php
$mplus_short_options = 'hc';
$mplus_long_options  = array(
    'help',
    'clear-lock',
    'filter:',
    'revalidate',
);

// Script options defaults (if applicable)
$filter = [];
$revalidate = false;
foreach(getopt($mplus_short_options, $mplus_long_options) as $option_name => $option_value)
    {
    if(in_array($option_name, array('h', 'help')))
        {
        ob_clean();
        echo $help_text;
        exit(0);
        }

    if(in_array($option_name, array('c', 'clear-lock')))
        {
        if(is_process_lock(MPLUS_LOCK) && clear_process_lock(MPLUS_LOCK))
            {
            logScript('[museumplus] Process lock removed!', $mplus_log_file);
            }
        elseif(!is_process_lock(MPLUS_LOCK))
            {
            logScript('[museumplus][warn] No process lock found! Please remove the "-c" -or- "--clear-lock" option.', $mplus_log_file);
            }
        else
            {
            logScript('[museumplus][error] Unable to clear process lock!', $mplus_log_file);
            }
        }

    if($option_name === 'filter')
        {
        $raw_options = (is_array($option_value) ? $option_value : [$option_value]);
        foreach ($raw_options as $filter_name_with_args)
            {
            // Filter takes args (e.g --filter byref:102,201 WHERE 102 and 201 are the IDs)
            if (mb_strpos($filter_name_with_args, ':', 0, 'UTF-8'))
                {
                [$filter_name, $filter_args] = explode(':', $filter_name_with_args);
                $raw_filter[$filter_name] = explode(',', $filter_args);
                continue;
                }

            // Simple filters (e.g --filter new_and_changed_associations)
            $raw_filter[$filter_name_with_args] = null;
            }

        $filter = mplus_validate_resource_association_filters($raw_filter);
        logScript('[museumplus] Additional valid filters: ' . implode(', ', array_keys($filter)), $mplus_log_file);
        }
    elseif($option_name === 'revalidate')
        {
        $revalidate = true;
        logScript('[museumplus] Set to force re-validation.', $mplus_log_file);
        }
    }

// Prepare list of users to send notifications/emails when needed
$notify_users = get_notification_users('SYSTEM_ADMIN');
$message_users = array();
foreach($notify_users as $notify_user)
    {
    get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'user_pref_show_notifications', $show_notifications);
    get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'user_pref_system_management_notifications', $sys_mgmt_notifications);
    if(!$show_notifications || !$sys_mgmt_notifications) { continue; }
    $message_users[] = $notify_user['ref'];
    }

// Check when this script was last run
$museumplus_script_last_ran = '';
$museumplus_check_script_last_run = check_script_last_ran(MPLUS_LAST_IMPORT, $museumplus_script_failure_notify_days, $museumplus_script_last_ran);
logScript("[museumplus] Script last ran: {$museumplus_script_last_ran}", $mplus_log_file);
if(!$museumplus_check_script_last_run)
    {
    mplus_notify($message_users, str_replace('%script_last_ran', $museumplus_script_last_ran, $lang['museumplus_warning_script_not_completed']));
    }

// Check for a process lock
if(is_process_lock(MPLUS_LOCK)) 
    {
    logScript('[museumplus][error] Script lock is in place. Deferring...', $mplus_log_file);
    logScript('[museumplus] To clear the lock after a failed run, pass either the "-c" -or- "--clear-lock" option.', $mplus_log_file);
    mplus_notify($message_users, $lang['museumplus_error_script_failed']);
    exit(1);
    }
set_process_lock(MPLUS_LOCK);

logScript('[museumplus] Starting actual process...', $mplus_log_file);
logScript('[museumplus] IMPORTANT: for debugging issues with the actual process, please refer to the museumplus_log table!', $mplus_log_file);

mplus_log_event('Running from MuseumPlus script...');
$mplus_resources = mplus_resource_get_association_data($filter);
logScript('[museumplus] Initial total of resources to be processed: ' . count($mplus_resources), $mplus_log_file);



$batch_no = 0;
foreach(array_chunk($mplus_resources, 1000) as $mplus_resource_refs)
    {
    logScript(sprintf('[museumplus] Started processing batch #%s - %s resources', ++$batch_no, count($mplus_resource_refs)), $mplus_log_file);
    logScript('[museumplus] Resources to check for associated module configuration: ' . implode(',', $mplus_resource_refs), $mplus_log_file);
    $ramcs = mplus_get_associated_module_conf($mplus_resource_refs, true);

    if(array_key_exists('new_and_changed_associations', $filter))
        {
        // Filter resources - discard of the ones where the "module name - MpID" combination hasn't changed since resource association was last validated
        foreach(mplus_flip_struct_by_module($ramcs) as $module_name => $mdata)
            {
            $computed_md5s = mplus_compute_data_md5($mdata['resources'], $module_name);
            $resources_md5s = array_column(mplus_resource_get_data(array_keys($mdata['resources'])), 'museumplus_data_md5', 'ref');
            foreach(array_keys($mdata['resources']) as $r_ref)
                {
                if(isset($computed_md5s[$r_ref], $resources_md5s[$r_ref]) && $computed_md5s[$r_ref] === $resources_md5s[$r_ref])
                    {
                    logScript('[museumplus] No change to the "module name - MpID" combination for resource #' . $r_ref, $mplus_log_file);
                    unset($ramcs[$r_ref]);
                    }
                }
            }
        }

    logScript('[museumplus] Total resources ready to be processed: ' . count(array_keys($ramcs)), $mplus_log_file);

    logScript('[museumplus] Attempting to clear metadata (if configured)...', $mplus_log_file);
    mplus_resource_clear_metadata(array_keys($ramcs));

    $valid_associations = mplus_validate_association($ramcs, false, $revalidate);
    logScript('[museumplus] Total resources with a valid module association: ' . count(array_keys($valid_associations)), $mplus_log_file);

    logScript('[museumplus] Attempting to sync MuseumPlus data...', $mplus_log_file);
    $errors = mplus_sync($valid_associations);

    if(is_array($errors) && !empty($errors))
        {
        logScript('[museumplus][error] Batch processed with errors: ' . PHP_EOL . implode(PHP_EOL . ' - ', $errors), $mplus_log_file);
        }
    }



logScript("[museumplus] ", $mplus_log_file);
logScript(sprintf("[museumplus] Script completed in %01.2f seconds.", microtime(true) - $mplus_script_start_time), $mplus_log_file);
set_sysvar(MPLUS_LAST_IMPORT, date('Y-m-d H:i:s'));
if($mplus_log_file !== null) { fclose($mplus_log_file); }
clear_process_lock(MPLUS_LOCK);
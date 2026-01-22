<?php

$php_sapi_name = php_sapi_name();

if ('cli' != $php_sapi_name) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Access denied');
}

include __DIR__ . '/../../../include/boot.php';
include_once __DIR__ . '/../include/emu_functions.php';
include_once __DIR__ . '/../include/emu_api.php';


// Init
ob_end_clean();
set_time_limit($cron_job_time_limit);

$debug_log           = false;
$emu_log_text        = '';
$emu_updated_records = array();
$emu_errors          = array();

if ('' != $emu_email_notify) {
    $email_notify = $emu_email_notify;
}

$emu_query_offset = 100;
$emu_test_count   = 999999999;
if ($emu_test_mode) {
    $emu_test_count = 500;
}

// Check if we need to clear locks or need help using the script
if ('cli' == $php_sapi_name && 2 == $argc) {
    if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
        echo 'To clear the lock after a failed run, pass in "--clearlock", "-clearlock", "-c" or "--c"' . PHP_EOL;
        exit('Bye!');
    } elseif (in_array($argv[1], array('--clearlock', '-clearlock', '-c', '--c'))) {
        if (is_process_lock('emu_import')) {
            clear_process_lock('emu_import');
        }
    } else {
        exit("Unknown argv: {$argv[1]}" . PHP_EOL);
    }
}

// Check when this script was last run - do it now in case of permanent process locks
$emu_script_last_ran = '';
if (!check_script_last_ran('last_emu_import', $emu_script_failure_notify_days, $emu_script_last_ran)) {
    $emu_script_failed_subject = ($emu_test_mode ? 'TESTING MODE: ' : '') . 'EMu Import script - WARNING';
    $adminusers = get_notification_users();
    $message = new ResourceSpaceUserNotification();
    $message->set_text("lang_emu_script_problem", ["%days%"], [$emu_script_failure_notify_days]);
    $message->append_text($emu_script_last_ran);
    $message->set_subject($emu_script_failed_subject);
    $message->user_preference = ["user_pref_system_management_notifications" => ["requiredvalue" => true,"default" => true]];
    $message->url =  $baseurl . "/plugins/emu/pages/setup.php";
    send_user_notification($adminusers, $message);
}

// Check for a process lock
if (is_process_lock('emu_import')) {
    echo 'EMu script lock is in place. Deferring.' . PHP_EOL . 'To clear the lock after a failed run use --clearlock flag.' . PHP_EOL;

    $emu_script_failed_subject = ($emu_test_mode ? 'TESTING MODE: ' : '') . 'EMu Import script - FAILED';
    $adminusers = get_notification_users();
    $message = new ResourceSpaceUserNotification();
    $message->set_text("The EMu script failed to run because a process lock was in place. This indicates that the previous run did not complete.\r\n\r\nIf you need to clear the lock after a failed run, run the script as follows:\r\n\r\nphp emu_script.php --clearlock\r\n");
    $message->set_subject($emu_script_failed_subject);
    $message->user_preference = ["user_pref_system_management_notifications" => ["requiredvalue" => true,"default" => true]];
    $message->url =  $baseurl . "/plugins/emu/pages/setup.php";
    send_user_notification($adminusers, $message);
    exit();
}
set_process_lock('emu_import');

$emu_script_start_time = microtime(true);
$emu_resources         = get_emu_resources();
$count_emu_resources   = count($emu_resources);
$emu_rs_mappings       = plugin_decode_complex_configs($emu_rs_saved_mappings);
$emu_pointer           = 0;

// Init script logging (if set)
$emu_log_file = null;

if ('' != trim($emu_log_directory)) {
    if (!is_dir($emu_log_directory)) {
        @mkdir($emu_log_directory, 0755, true);

        if (!is_dir($emu_log_directory)) {
            echo 'Unable to create log directory: "' . escape($emu_log_directory) . '"' . PHP_EOL;
        }
    } else {
        // Valid log directory

        // Clean up old files
        $iterator        = new DirectoryIterator($emu_log_directory);
        $log_expiry_time = $emu_script_start_time - ((5 * intval($emu_script_failure_notify_days)) * 24 * 60 * 60) ;

        foreach ($iterator as $file_info) {
            if (!$file_info->isFile()) {
                continue;
            }

            $filename = $file_info->getFilename();

            // Delete log file if it is older than its expiration time
            if ('emu_script_log' == substr($filename, 0, 14) && $file_info->getMTime() < $log_expiry_time) {
                @unlink($file_info->getPathName());
            }
        }

        // New log file
        $emu_log_file = fopen($emu_log_directory . DIRECTORY_SEPARATOR . 'emu_script_log_' . date('Y_m_d-H_i') . '.log', 'ab');
    }
}

$emu_query_ids = array();

while ($emu_pointer < $count_emu_resources && $emu_pointer < $emu_test_count) {
    unset($emu_query_ids);
    $emu_query_ids = array();

    for ($t = $emu_pointer; $t < ($emu_pointer + $emu_query_offset) && (($emu_pointer + $t) < $emu_test_count) && $t < $count_emu_resources; $t++) {
        if ('' != $emu_resources[$t]['object_irn'] && is_numeric($emu_resources[$t]['object_irn']) && false === strpos($emu_resources[$t]['object_irn'], '.')) {
            $emu_query_ids[] = $emu_resources[$t]['object_irn'];
        } else {
            // Invalid IRN
            $emu_log_message = "Invalid EMu data stored in ResourceSpace: {$emu_resources[$t]['object_irn']}";
            $emu_errors[$emu_resources[$t]['resource']] = $emu_log_message;

            emu_script_log($emu_log_message, $emu_log_file);
        }
    }

    emu_script_log('Retrieving data from EMu database', $emu_log_file);
    emu_script_log("EMu query IRNs:" . PHP_EOL . print_r($emu_query_ids, true), $emu_log_file);

    $emu_records = get_emu_data($emu_api_server, $emu_api_server_port, $emu_query_ids, $emu_rs_mappings);

    if (!is_array($emu_records) || 0 === count($emu_records)) {
        emu_script_log('No EMu data received, continuing...', $emu_log_file);

        $emu_pointer = $emu_pointer + $emu_query_offset;

        continue;
    }

    for ($ri = $emu_pointer; $ri < ($emu_pointer + $emu_query_offset) && (($emu_pointer + $ri) < $emu_test_count) && $ri < $count_emu_resources; $ri++) {
        $emu_object_data_found = false;

        foreach ($emu_records as $emu_record_irn => $emu_record) {
            if ($emu_resources[$ri]['object_irn'] != $emu_record_irn) {
                continue;
            }

            $emu_object_data_found = true;
            emu_script_log("Checking resource: '{$emu_resources[$ri]['resource']}'. Object IRN: '{$emu_resources[$ri]['object_irn']}'", $emu_log_file);

            /*
            Example of $emu_rs_mappings:
            [epublic] => Array
                (
                    [ObjTitle] => 8
                    [ObjName] => 25
                )

            [emultimedia] => Array
                (
                    [ChaAspectRatio] => 10
                )
            */
            foreach ($emu_rs_mappings as $emu_module => $emu_module_columns) {
                foreach ($emu_module_columns as $emu_module_column => $rs_field_id) {
                    if (
                        0 == intval($rs_field_id)
                        || !isset($emu_record[$emu_module_column])
                        || (isset($emu_record[$emu_module_column]) && '' == trim($emu_record[$emu_module_column]))
                    ) {
                        continue;
                    }

                    $existing_value = get_data_by_field($emu_resources[$ri]['resource'], $rs_field_id);

                    // No need to update if values are the same
                    if ($existing_value === $emu_record[$emu_module_column]) {
                        emu_script_log("No need to update RS field ID '{$rs_field_id}' with data from module '{$emu_module}', column '{$emu_module_column}'", $emu_log_file);

                        continue;
                    }

                    emu_script_log("Updating RS field ID '{$rs_field_id}' with data from module '{$emu_module}', column '{$emu_module_column}'. VALUE: <<{$emu_record[$emu_module_column]}>>", $emu_log_file);

                    if (!$emu_test_mode) {
                        update_field($emu_resources[$ri]['resource'], $rs_field_id, $emu_record[$emu_module_column]);
                    }

                    $emu_updated_records[$emu_resources[$ri]['resource']] = $emu_resources[$ri]['object_irn'];

                    emu_script_log("Resource ID '{$rs_field_id}' updated successfully!", $emu_log_file);
                }
            }
        }
    }

    // Update pointer and go onto next set of resources
    $emu_pointer = $emu_pointer + $emu_query_offset;
}

$emu_log_text .= sprintf("EMu Script completed in %01.2f seconds.\r\n", microtime(true) - $emu_script_start_time) . "\r\n";

if (0 === $count_emu_resources) {
    $emu_status_text = 'Completed with errors';
    emu_script_log($emu_status_text, $emu_log_file);

    $emu_log_text .= 'No Resources found with EMu IRNs. Please check the emu plugin configuration.';
    emu_script_log('No Resources found with EMu IRNs. Please check the emu plugin configuration.', $emu_log_file);
} else {
    $emu_log_text .= "Processed {$count_emu_resources} resource(s) with EMu Object IRNs.\r\n\r\n";
    $emu_log_text .= 'Successfully updated ' . count($emu_updated_records) . " resource(s).\r\n\r\n";

    if (0 < count($emu_updated_records)) {
        $emu_log_text .= "Resource ID  :  EMu Object IRN \r\n";
    }

    foreach ($emu_updated_records as $success_ref => $success_irn) {
        $emu_log_text .=  "{$success_ref}  :  {$success_irn}\r\n";
    }

    $emu_status_text = 'Success!';
    if (0 != count($emu_errors)) {
        $emu_status_text = "\r\nCompleted with errors";

        $emu_log_text .= "\r\n\r\nFailed to update " . count($emu_errors) .  " resource(s).\r\n";
        $emu_log_text .= "\r\nError summary:\r\n";

        foreach ($emu_errors as $error_resource => $emu_error) {
            $emu_log_text .= "Resource ID {$error_resource} {$emu_error}\r\n";
        }
    }

    emu_script_log($emu_status_text, $emu_log_file);
}

$adminusers = get_notification_users();
$message = str_replace("%days%", $emu_script_failure_notify_days, $lang["emu_script_problem"]) . $emu_script_last_ran;

$message = new ResourceSpaceUserNotification();
$message->set_text(($emu_test_mode ? 'TEST MODE - ' : '') . "EMu Import script - {$emu_status_text} <br/><br/>");
$message->append_text($emu_log_text);
$message->set_subject(($emu_test_mode ? 'TEST MODE - ' : '') . "EMu Import script - {$emu_status_text}");
$message->user_preference = ["user_pref_system_management_notifications" => ["requiredvalue" => true,"default" => true]];
$message->url =  $baseurl . "/plugins/emu/pages/setup.php";
send_user_notification($adminusers, $message);

echo $emu_log_text;

fclose($emu_log_file);

clear_process_lock('emu_import');

ps_query('DELETE FROM sysvars WHERE name = "last_emu_import"', []);
ps_query('INSERT INTO sysvars VALUES ("last_emu_import", NOW())', []);
clear_query_cache("sysvars");

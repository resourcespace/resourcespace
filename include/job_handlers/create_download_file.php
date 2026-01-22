<?php

/*
Run a command that will create an output file, optionally specifying a download URL that can be sent to the user
Requires the following:-

$job_data["resource"] - Resource ID
$job_data["title"] -  Download title/description
$job_data["command"] - command to run
$job_data["outputfile"] - target output file
$job_data["lifetime"] - [optional]  length of time for which file will be available before automatic deletion
$job_data["url"] - [optional] URL to send to the user

If a file is to be created for only a specific user to download you can create a random string e.g. $randomstring and set the path and url for the job as below:-

    $job_data["outputfile"] = get_temp_dir(false,'user_downloads') . "/" . $ref . "_" . md5($username . $randomstring . $scramble_key) . ".<file extension here>";
    $job_data["url"]=$baseurl . "/pages/download.php?userfile=" . $ref . "_" . $randomstring . ".<file extension here>;
*/

include_once __DIR__ . "/../image_processing.php";

global $config_windows,$baseurl, $baseurl_short, $offline_job_prefixes;
$jobsuccess = false;
$job_cmd_ok = false;

$shell_exec_cmd = $job_data["command"];
$shell_exec_params = $job_data["command_params"];
$shell_exec_params[$job_data["output_file_placeholder"]] = $job_data["outputfile"];

// Check we are using a whitelisted command path to create file
foreach ($offline_job_prefixes as $offline_job_prefix) {
    $cmd_path = get_utility_path($offline_job_prefix);
    if (substr($shell_exec_cmd, 0, strlen($cmd_path)) == $cmd_path) {
        $job_cmd_ok = true;
        break;
    }
}

// Skip if any other unwanted characters in command (|,<,>,!,&,#,; or `)
if ($job_cmd_ok && !preg_match("/(\||<|>|;|!|&|#|`)/i", $shell_exec_cmd)) {
    if ($config_windows) {
        $shell_exec_cmd = str_replace(array_keys($shell_exec_params), array_values($shell_exec_params), $shell_exec_cmd);
        file_put_contents(get_temp_dir() . "/create_download_" . $randstring . ".bat", $shell_exec_cmd);
        $shell_exec_cmd = get_temp_dir() . "/create_download_" . $randstring . ".bat";
        $shell_exec_params = [];
        $deletebat = true;
    }
    $output = run_command($shell_exec_cmd, false, $shell_exec_params);

    if (file_exists($job_data["outputfile"])) {
        global $lang, $baseurl, $offline_job_delete_completed;
        $url = (isset($job_data["url"])) ? $job_data["url"] : (isset($job_data["resource"]) ? $baseurl_short . "?r=" . $job_data["resource"] : "");
        $message = $job_success_text != "" ? $job_success_text : $lang["download_file_created"]  . ": " . str_replace(array('%ref','%title'), array($job_data['resource'],$resource['field' . $view_title_field]), $lang["ref-title"]) . "(" . $job_data["alt_name"] . "," . $job_data["alt_description"] . ")";
        message_add($job["user"], $message, $url, 0);
        if ($offline_job_delete_completed) {
            job_queue_delete($jobref);
        } else {
            job_queue_update($jobref, $job_data, STATUS_COMPLETE);
        }
        if (isset($job_data["lifetime"])) {
            $delete_job_data = array();
            $delete_job_data["file"] = $job_data["outputfile"];
            $delete_date = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * DOWNLOAD_FILE_LIFETIME));
            $job_code = md5($job_data["outputfile"]);
            job_queue_add("delete_file", $delete_job_data, "", $delete_date, "", "", $job_code);
        }
        $jobsuccess = true;
    }

    if (isset($deletebat) && file_exists($shell_exec_cmd)) {
        unlink($shell_exec_cmd);
    }
}

if (!$jobsuccess) {
    // Job failed, update job queue
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    $message = $job_failure_text != "" ? $job_failure_text : $lang["download_file_creation_failed"]  . ": " . str_replace(array('%ref','%title'), array($job_data['resource'],$resource['field' . $view_title_field]), $lang["ref-title"]) . "(" . $job_data["alt_name"] . "," . $job_data["alt_description"] . ")";
    $url = $baseurl_short . "?r=" . $job_data["resource"];
    message_add($job["user"], $message, $url, 0);
}

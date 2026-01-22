<?php

include_once __DIR__ . "/../include/meta_functions.php";
include_once __DIR__ . "/../include/csv_functions.php";

global $userref,$username,$scramble_key,$baseurl,$csvfile,$meta,$resource_types, $messages, $csv_set_options;

$csv_set_options = $job_data["csv_set_options"];
$csvfile = $job_data["csvfile"];

// Set up the user who initiated the CSV upload as all permissions must be honoured
$user_select_sql = new PreparedStatementQuery();
$user_select_sql->sql = "u.ref = ?";
$user_select_sql->parameters = ["i",$job['user']];
$user_data = validate_user($user_select_sql, true);

if (count($user_data) > 0) {
    setup_user($user_data[0]);
} else {
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

$messages = array();
$meta = meta_get_map();

$restypearr = get_resource_types();
$resource_types = array();

foreach ($restypearr as $restype) {
    $resource_types[$restype["ref"]] = $restype;
}

$log_time = date("Ymd-H:i:s", time());
$logfile = get_temp_dir(false, 'user_downloads') . "/" . $userref . "_" . md5($username . md5($csv_set_options["csvchecksum"] . $log_time) . $scramble_key) . ".log";
$logurl = $baseurl . "/pages/download.php?userfile=" . $userref . "_" . md5($csv_set_options["csvchecksum"] . $log_time) . ".log&filename=csv_upload_" . $log_time;
$csv_set_options["log_file"] = $logfile;
$csv_process_steps = [
    'validate' => ['max_error_count' => 100, 'processcsv' => false],
    'process'  => ['max_error_count' => 0,   'processcsv' => true],
];

foreach ($csv_process_steps as $step_info) {
    $step_txt = $step_info['processcsv'] ? $GLOBALS['lang']['csv_upload_step5'] : $GLOBALS['lang']['csv_upload_step4'];

    if (csv_upload_process($csvfile, $meta, $resource_types, $messages, $csv_set_options, $step_info['max_error_count'], $step_info['processcsv'])) {
        if ($step_info['processcsv']) {
            job_queue_update($jobref, $job_data, STATUS_COMPLETE);
            message_add(
                $job["user"],
                $job["success_text"] . (isset($csv_set_options["csv_filename"]) ? ": {$csv_set_options["csv_filename"]}" : ""),
                $logurl
            );
            break;
        }
        continue;
    }

    // Failure will always stop the entire process regardless of the step (validation/ processing)
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    message_add($job["user"], "{$job["failure_text"]}: $step_txt", $logurl);
    break;
}

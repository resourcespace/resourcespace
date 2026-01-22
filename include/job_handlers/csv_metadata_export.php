<?php

/*
Job handler to process CSV metadata download for result set

Requires the following job data:

$job_data["personaldata"]       - (bool) Only include fields marked as likely to contains personal data?
$job_data["allavailable"]       - (bool) Include data from all fields?
$job_data["exportresources"]    - (array) List of resources to export
*/

include_once __DIR__ . '/../csv_export_functions.php';

global $lang, $baseurl_short, $offline_job_delete_completed, $scramble_key, $userref, $username;

$personaldata       = $job_data["personaldata"];
$allavailable       = $job_data["allavailable"];
$exportresources    = $job_data["exportresources"];
$search             = $job_data["search"];
$restypes           = $job_data["restypes"];
$archive            = $job_data["archive"];
$access             = $job_data["access"];
$sort               = $job_data["sort"];

// Set up the user who requested the metadata download as it needs to be processed with their access
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

$randstring = md5(json_encode($job_data));
$csvfile = get_temp_dir(false, 'user_downloads') . "/" . $userref . "_" . md5($username . $randstring . $scramble_key) . ".csv";

$findstrings = array("[search]","[time]");
$replacestrings = array(mb_substr(safe_file_name($search), 0, 150), date("Ymd-H:i", time()));
$csv_filename = str_replace($findstrings, $replacestrings, $lang["csv_export_filename"]);

$csv_filename_noext = strip_extension($csv_filename);

$csvurl = $baseurl_short . "pages/download.php?userfile=" . $userref . "_" . $randstring . ".csv&filename=" . $csv_filename_noext;

generateResourcesMetadataCSV($exportresources, $personaldata, $allavailable, $csvfile);

log_activity($lang['csvExportResultsMetadata'], LOG_CODE_DOWNLOADED);
debug("Job handler 'csv_metadata_export' created zip download file {$csv_filename}");

$jobsuccess = true;
job_queue_update($jobref, $job_data, STATUS_COMPLETE);
message_add($job["user"], $job_success_text, $csvurl);

$delete_job_data = array();
$delete_job_data["file"] = $csvfile;
$delete_date = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * DOWNLOAD_FILE_LIFETIME)); // Delete file after set number of days
$job_code = md5($csvfile);
job_queue_add("delete_file", $delete_job_data, "", $delete_date, "", "", $job_code);

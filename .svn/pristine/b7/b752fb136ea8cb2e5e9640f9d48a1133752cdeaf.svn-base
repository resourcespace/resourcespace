<?php

/*
Job handler to process collection downloads

Requires the following job data:
$job_data['collection']                 Collection ID
$job_data['collectiondata']             Collection data from get_collection()
$job_data['collection_resources']       Resources to include in download (was previously 'result')
$job_data['size']                       Requested size
$job_data['exiftool_write_option']      Write embedded data (not for TAR downloads)
$job_data['useoriginal']                Use original if requested size not available?
$job_data['id']                         Unique identifier - used to create a download.php link that is specific to the user
$job_data['includetext']                Include text file in download (config $zipped_collection_textfile)
$job_data['count_data_only_types']      Count of data only resources
$job_data['usage']                      Download usage (selected index of $download_usage)
$job_data['usagecomment']               Download usage comment
$job_data['settings_id']                Index of selected option from $collection_download_settings array
$job_data['include_csv_file']           Include metadata CSV file?
$job_data['include_alternatives']       Include alternative files?
$job_data['k']                          External access key if set
*/

include_once __DIR__ . '/../pdf_functions.php';
include_once __DIR__ . '/../csv_export_functions.php';

// Used by format chooser
if (isset($job_data["ext"])) {
    global $job_ext;
    $job_ext = $job_data["ext"];
}

// Set up the user who requested the collection download as it needs to be processed in its name
$user_select_sql = new PreparedStatementQuery();
$user_select_sql->sql = "u.ref = ?";
$user_select_sql->parameters = ["i", $job['user']];

$user_data = validate_user($user_select_sql, true);
if (count($user_data) > 0) {
    setup_user($user_data[0]);
} else {
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

$zipinfo = process_collection_download($job_data);
collection_log($job_data['collection'], LOG_CODE_COLLECTION_COLLECTION_DOWNLOADED, "", $job_data['size']);

if (!is_valid_rs_path($zipinfo["path"])) {
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    message_add($job["user"], $GLOBALS["lang"]["nothing_to_download"]);
    return;
}

if ($GLOBALS['offline_job_delete_completed']) {
    job_queue_delete($jobref);
} else {
    job_queue_update($jobref, $job_data, STATUS_COMPLETE);
}

$extension = "zip";
if (isset($job_data["archiver"]) && isset($job_data["settings_id"])) {
    $extension = $GLOBALS["collection_download_settings"][$job_data["settings_id"]]['extension'] ?? "zip";
}

$urlparams = [
   "userfile" => $user_data[0]["ref"] . "_" . $job_data["id"] . "." . $extension,
   "filename" => pathinfo($zipinfo["filename"], PATHINFO_FILENAME),
];
$download_url = generateURL($GLOBALS['baseurl_short'] . "pages/download.php", $urlparams);
message_add($job["user"], $job_success_text, $download_url);

$delete_job_data = [];
$delete_job_data["file"] = $zipinfo["path"];
$delete_date = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * DOWNLOAD_FILE_LIFETIME)); // Delete file after set number of days
$job_code = md5($zipinfo["path"]);
job_queue_add("delete_file", $delete_job_data, "", $delete_date, "", "", $job_code);

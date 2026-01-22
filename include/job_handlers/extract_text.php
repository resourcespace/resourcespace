<?php

/*
Job handler to process collection downloads

Requires the following job data:
$job_data['ref'] - Resource ref
$job_data['extension'] - File extension
$job_data['path'] - Path can be set to use an alternate file, for example, in the case of unoconv
*/
include_once __DIR__ . '/../image_processing.php';

global $offline_job_delete_completed;

$ref        = $job_data["ref"];
$extension  = $job_data["extension"];
$path       = $job_data["path"] ?? '';

extract_text($ref, $extension, $path);

// May be needed elsewhere in the code further up
if ($offline_job_delete_completed) {
    job_queue_delete($jobref);
} else {
    job_queue_update($jobref, $job_data, STATUS_COMPLETE);
}

unset($ref, $extension, $path);

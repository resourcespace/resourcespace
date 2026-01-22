<?php

/*
Job handler for creating previews for a resource/ alternative

Requires the following job data:-
$job_data['resource'] - Resource ID
$job_data['thumbonly'] - Optional
$job_data['extension'] - Optional
$job_data['previewonly'] - Optional
$job_data['previewbased'] - Optional
$job_data['alternative'] - Optional
$job_data['ignoremaxsize'] - Optional
$job_data['ingested'] - Optional
$job_data['checksum_required'] - Optional
*/
include_once __DIR__ . '/../image_processing.php';

global $lang, $baseurl, $offline_job_delete_completed, $baseurl_short;

$resource          = $job_data["resource"] ?? 0;
$thumbonly         = $job_data["thumbonly"] ?? false;
$extension         = $job_data["extension"] ?? 'jpg';
$previewonly       = $job_data["previewonly"] ?? false;
$previewbased      = $job_data["previewbased"] ?? false;
$alternative       = $job_data["alternative"] ?? -1;
$ignoremaxsize     = $job_data["ignoremaxsize"] ?? true;
$ingested          = $job_data["ingested"] ?? false;
$checksum_required = $job_data["checksum_required"] ?? true;

// For messages
$url = isset($job_data['resource']) ? "{$baseurl_short}?r={$job_data['resource']}" : '';

$resdata = get_resource_data($resource);
if (!$resdata) {
    job_queue_update($jobref, $job_data, STATUS_DISABLED);
    return;
}

if ($resource > 0) {
    delete_previews($resource);
}

logScript("[create_previews] Creating previews", $log_file);

$success = create_previews(
    $resource, 
    $thumbonly, 
    in_array($extension, NON_PREVIEW_EXTENSIONS) ? 'jpg' : $extension, 
    $previewonly, 
    in_array($extension, NON_PREVIEW_EXTENSIONS) || $previewbased, 
    $alternative, 
    $ignoremaxsize, 
    $ingested, 
    $checksum_required
);

if ($resource > 0 && $success) {
    // Success - no message required
    update_disk_usage($resource);
    if ($offline_job_delete_completed) {
        job_queue_delete($jobref);
    } else {
        job_queue_update($jobref, $job_data, STATUS_COMPLETE);
    }
} else {
    // Fail
    $preview_attempts = $resdata["preview_attempts"];
    if ($preview_attempts < SYSTEM_MAX_PREVIEW_ATTEMPTS) {
        // Reschedule job to try again later in the event that 3rd party processing has failed e.g. Unoserver
        // Increase gap between subsequent attempts
        $retry_date = date('Y-m-d H:i:s', time() + (60 * 60  * pow(2, $preview_attempts + 1)));
        job_queue_update($jobref, $job_data, STATUS_ACTIVE, $retry_date);
    } else {
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        get_config_option(['user' => $job['user']], 'user_pref_resource_notifications', $send_notifications, false);
        if ($send_notifications) {
            $create_previews_job_failure_text = str_replace('%RESOURCE', $resource, $lang['jq_create_previews_failure_text']);
            $message = $job["failure_text"] != '' ? $job["failure_text"] : $create_previews_job_failure_text;
            message_add($job['user'], $message, $url, 0);
        }
    }
}

unset(
    $resource,
    $thumbonly,
    $extension,
    $previewonly,
    $previewbased,
    $alternative,
    $ignoremaxsize,
    $ingested,
    $checksum_required
);

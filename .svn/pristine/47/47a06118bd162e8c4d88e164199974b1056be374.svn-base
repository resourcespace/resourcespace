<?php

include_once __DIR__ . '/../image_processing.php';

# $job_data["r"]
# $job_data["title"]
# $job_data["ingest"]
# $job_data["createPreviews"]
# $job_data["archive"] -> optional based on $upload_then_process_holding_state

global $baseurl, $offline_job_delete_completed,$baseurl_short;
$resource = get_resource_data($job_data["r"]);
$status = false;

if ($resource !== false) {
    $status = update_resource($job_data["r"], $resource['file_path'], $resource['resource_type'], $job_data["title"], $job_data["ingest"], $job_data["createPreviews"], $resource['file_extension'], true);

    # update the archive status
    if (isset($job_data['archive']) && $job_data['archive'] !== '') {
        update_archive_status($job_data["resource"], $job_data["archive"]);
    }
}

$url = isset($job_data['r']) ? $baseurl_short . "?r=" . $job_data['r'] : '';

if ($status === false) {
    # fail
    message_add($job['user'], $job_failure_text, $url, 0);

    job_queue_update($jobref, $job_data, STATUS_ERROR);
} else {
    # only delete the job if completed successfully;
    if ($offline_job_delete_completed) {
        job_queue_delete($jobref);
    } else {
        job_queue_update($jobref, $job_data, STATUS_COMPLETE);
    }
}

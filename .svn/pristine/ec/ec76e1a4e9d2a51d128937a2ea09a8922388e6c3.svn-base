<?php

include_once __DIR__ . '/../image_processing.php';

# $job_data['recreate'] -> Optional. Boolean, will default to false if not specified

logScript("[update_checksums] Starting update_checksums job", $log_file);

$recreate = (bool) $job_data['recreate'] ?? false;

if ($recreate) {
    $resources = ps_query("SELECT ref, file_extension FROM resource WHERE ref > 0 AND integrity_fail = 0 AND length(file_extension) > 0 ORDER by ref ASC");
} else {
    $resources = ps_query("SELECT ref, file_extension FROM resource WHERE ref > 0 AND integrity_fail = 0 AND length(file_extension) > 0 AND (file_checksum IS NULL OR file_checksum = '')");
}

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[update_checksums] Updating checksums for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[update_checksums] Updating checksum for resource #" . $resource["ref"] . "...", $log_file);

        if (generate_file_checksum($resource["ref"], $resource["file_extension"], true)) {
            logScript("[update_checksums] ....completed", $log_file);
        } else {
            logScript("[update_checksums] [ERROR] Failed - skipping", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[update_checksums] [PROGRESS] $progress%", $log_file);
        $resource_count++;    
    }

} else {
    logScript("[update_checksums] No resources found", $log_file);
}

logScript("[update_checksums] Ending update_checksums job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed update_checksums job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
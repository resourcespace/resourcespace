<?php

include_once dirname(__FILE__, 2) . '/include/clip_functions.php';

global $clip_resource_types;

# $job_data['limit'] -> Optional. Defaults to 10000

logScript("[generate_vectors] Starting generate_vectors job", $log_file);

// Ensure only one instance of this, use the function name from the existing script/cron call
if (is_process_lock("clip_generate_missing_vectors")) {
    logScript("[generate_vectors] [ERROR] unable to start job due to existing process lock", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
} elseif (count($clip_resource_types) === 0) {
    logScript("[generate_vectors] [ERROR] unable to start job due to no resource types selected for vector creation", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return; 
}

set_process_lock("clip_generate_missing_vectors");

$limit = (int) $job_data['limit'] ?? 10000;

if ($limit  <= 0 || $limit > 100000) {
    $limit = 10000;
}

// Get resources needing vector generation or update - look at the modified date vs. the creation date on the text vector, and also the image checksum on the vector vs the one on the resource record. This catches both metadata and image updates.

$sql = "
    SELECT r.ref value
    FROM resource r
    LEFT JOIN resource_clip_vector v_image ON v_image.is_text=0 and r.ref = v_image.resource

    WHERE r.has_image = 1
    AND r.resource_type in (" . ps_param_insert(count($clip_resource_types)) . ")
    AND r.file_checksum IS NOT NULL
    AND 
        (v_image.checksum IS NULL OR v_image.checksum != r.file_checksum)
    ORDER BY r.ref ASC
    LIMIT ?";

$resources = ps_array($sql, array_merge(ps_param_fill($clip_resource_types, "i"), array('i', (int) $limit)));

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[generate_vectors] Generating vectors for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[generate_vectors] Generating vector for resource #" . $resource . "...", $log_file);
        ob_flush();

        if (clip_generate_vector($resource)) {
            logScript("[generate_vectors] ....completed", $log_file);
        } else {
            logScript("[generate_vectors] [ERROR] Failed - file missing or vector generation error", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[generate_vectors] [PROGRESS] $progress%", $log_file);
        $resource_count++;
        ob_flush();        
    }

} else {
    logScript("[generate_vectors] No resources found", $log_file);
}

clear_process_lock("clip_generate_missing_vectors");

logScript("[generate_vectors] Ending generate_vectors job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed generate_vectors job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
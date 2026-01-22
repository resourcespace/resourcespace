<?php

include_once dirname(__FILE__, 2) . '/include/faces_functions.php';

# $job_data can be empty array for this job

logScript("[faces_detect] Starting faces_detect job", $log_file);

// Get all resources that haven't had faces processed yet

// Ensure only one instance of this, use the function name from the existing script/cron call
if (is_process_lock("faces_detect_missing")) {
    logScript("[faces_detect] [ERROR] unable to start job due to existing process lock", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

set_process_lock("faces_detect_missing");

$resources = ps_array("SELECT ref value FROM resource WHERE has_image=1 and (faces_processed is null or faces_processed=0) ORDER BY ref desc");

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[faces_detect] Detecting faces for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[faces_detect] Detecting faces for resource #" . $resource . "...", $log_file);
        ob_flush();

        if (faces_detect($resource)) {
            logScript("[faces_detect] ....completed", $log_file);
        } else {
            logScript("[faces_detect] [ERROR] Failed - image missing, the service failed, or invalid data was returned", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[faces_detect] [PROGRESS] $progress%", $log_file);
        $resource_count++;
        ob_flush();        
    }

} else {
    logScript("[faces_detect] No resources found", $log_file);
}

clear_process_lock("faces_detect_missing");

logScript("[faces_detect] Ending faces_detect job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed faces_detect job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
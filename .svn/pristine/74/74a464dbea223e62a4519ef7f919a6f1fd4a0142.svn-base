<?php

include_once dirname(__FILE__, 2) . '/include/whisper_functions.php';

# $job_data can be empty array for this job

logScript("[process_whisper] Starting process_whisper job", $log_file);

// Get all resources that haven't been processed yet

// Ensure only one instance of this, use the function name from the existing script/cron call
if (is_process_lock("whisper_process_unprocessed")) {
    logScript("[process_whisper] [ERROR] unable to start job due to existing process lock", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

set_process_lock("whisper_process_unprocessed");

global $whisper_extensions;

$extensions = explode(",", $whisper_extensions);

$resources = ps_array("SELECT ref value FROM resource WHERE file_extension IN (" . ps_param_insert(count($extensions)) . ") AND (whisper_processed IS NULL OR whisper_processed=0) AND archive <> 3 ORDER BY ref DESC", ps_param_fill($extensions, "s"));

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[process_whisper] Running Whisper for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[process_whisper] Processing resource #" . $resource . "...", $log_file);
        ob_flush();

        if (whisper_process($resource)) {
            logScript("[process_whisper] ....completed", $log_file);
        } else {
            logScript("[process_whisper] [ERROR] Failed", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[process_whisper] [PROGRESS] $progress%", $log_file);
        $resource_count++;
        ob_flush();        
    }

} else {
    logScript("[process_whisper] No resources found", $log_file);
}

clear_process_lock("whisper_process_unprocessed");

logScript("[process_whisper] Ending process_whisper job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed process_whisper job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
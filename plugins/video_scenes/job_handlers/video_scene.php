<?php

// Write directly to the CLI stream so startup is visible before any processing begins.
$job_start_message = date('Y-m-d H:i:s') . ' [video_scene] Starting scene job #'
    . (int) $jobref . ' for resource #' . (int) ($job_data['resource'] ?? 0) . PHP_EOL;
if (defined('STDOUT')) {
    fwrite(STDOUT, $job_start_message);
    fflush(STDOUT);
}
if (isset($log_file) && is_resource($log_file)) {
    fwrite($log_file, $job_start_message);
    fflush($log_file);
}

include_once dirname(__DIR__) . '/include/video_scene_functions.php';

try {
    $completed = video_scene_process($job_data, $log_file);
    if ($completed && $GLOBALS['offline_job_delete_completed']) {
        job_queue_delete($jobref);
    } else {
        job_queue_update($jobref, $job_data, $completed ? STATUS_COMPLETE : STATUS_ERROR);
    }
    logScript('[video_scene] ' . ($completed ? 'Completed' : 'Scene descriptions failed; boundaries saved'), $log_file);
} catch (Throwable $error) {
    // The worker redacts credentials and source paths before returning diagnostics.
    logScript('[video_scene] ' . $error->getMessage(), $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
}

<?php

include_once dirname(__DIR__) . '/include/video_scene_functions.php';

video_scene_log(
    'Starting scene job #' . (int) $jobref . ' for resource #' . (int) ($job_data['resource'] ?? 0),
    $log_file
);
try {
    $completed = video_scene_process($job_data, $log_file);
    if ($completed && $GLOBALS['offline_job_delete_completed']) {
        job_queue_delete($jobref);
    } else {
        job_queue_update($jobref, $job_data, $completed ? STATUS_COMPLETE : STATUS_ERROR);
    }
    video_scene_log($completed ? 'Completed' : 'Scene descriptions failed; boundaries saved', $log_file);
} catch (Throwable $error) {
    // The worker redacts credentials and source paths before returning diagnostics.
    video_scene_log($error->getMessage(), $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
}

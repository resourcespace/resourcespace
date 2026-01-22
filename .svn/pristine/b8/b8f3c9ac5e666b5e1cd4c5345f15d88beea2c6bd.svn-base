<?php

/*
Delete a file
$job_data["file"] - full patch of file to delete
*/

if (isset($job_data["file"]) && file_exists($job_data["file"])) {
    unlink($job_data["file"]);
    job_queue_delete($jobref);
} else {
    // Job failed, upate job queue
    job_queue_update($jobref, $job_data, STATUS_ERROR);
}

<?php

// Validate that an offline extract_text job functions
command_line_only();


// Offline job should fail as userref is invalid (e.g. old job created by deleted user).
// This shouldn't block the next job from being processed - just mark failed and move on.
job_queue_purge(STATUS_ERROR);

$testresource1 = create_resource(2, 0);
// Create a new offline job
$extract_text_job_data = array(
    'ref'       => $testresource1,
    'extension' => "txt",
);

$offlinejob_to_fail = job_queue_add('extract_text', $extract_text_job_data, 2147483647); # $user is max value for MySQL int column.


// Offline job to be processed
$testresource2 = create_resource(2, 0);
$settext = "This is test text from an offline job";

$resource_path = get_resource_path($testresource2, true, '', true, "txt");
file_put_contents($resource_path, "This is test text from an offline job");

// Create a new offline job
$extract_text_job_data = array(
    'ref'       => $testresource2,
    'extension' => "txt",
);

$offlinejob = job_queue_add('extract_text', $extract_text_job_data);

// Run the job
$alljobs = job_queue_get_jobs("extract_text", STATUS_ACTIVE);

ob_start();
foreach ($alljobs as $run_job) {
    job_queue_run_job($run_job, true);
}
ob_end_clean();

// Test job linked to invalid userref was marked as failed.
$failed_jobs = job_queue_get_jobs("extract_text", STATUS_ERROR);
if (!isset($failed_jobs[0]) || (isset($failed_jobs[0]) && $failed_jobs[0]['ref'] !== $offlinejob_to_fail)) {
    return false;
}

$gettext = get_data_by_field($testresource2, $extracted_text_field);

if ($gettext != $settext) {
    return false;
}

return true;

<?php

include_once __DIR__ . "/../../include/boot.php";
command_line_only();

set_time_limit(0);

$offline_job_short_options = 'chm:j:';

$offline_job_cli_long_options  = array(
    'clear-lock',
    'job:',
    'max-jobs:',
    'type:',
    'exclude-type:',
    'help',
);
$clear_lock = false;
$jobs = array();
$max_jobs = 10;
$job_types = [];
$excluded_job_types = [];

$help_text = "NAME
    offline_jobs.php - Process offline jobs

SYNOPSIS
    php /path/to/pages/tools/offline_jobs.php [OPTIONS...]

DESCRIPTION
    Used to run ResourceSpace offline jobs. Normally triggered by a cron entry/scheduled task.

OPTIONS SUMMARY

    -h, --help          Display this help text and exit
    -j, --job           Specify the job ID to run
    -c, --clear-lock    Clear any existing process locks for jobs
    -m, --max-jobs      Maximum number of concurrent jobs that can be running (integer)
        --type          Run only these job types (comma-separated)
        --exclude-type  Skip these job types (comma-separated)

EXAMPLES
    php offline_jobs.php --clear-lock --job 256 
    php offline_jobs.php --max-jobs 5

    To schedule separate general and video scene workers, use one cron entry for each command:
    php offline_jobs.php --exclude-type=video_scene
    php offline_jobs.php --type=video_scene --max-jobs=1

    Workers with matching type filters share a concurrency limit. Use complementary filters
    when scheduling separate workers.
";

// CLI options check
$options = getopt($offline_job_short_options, $offline_job_cli_long_options);
foreach (['type', 'exclude-type'] as $filter_option) {
    if (
        !isset($options[$filter_option])
        && array_intersect(['--' . $filter_option, '--' . $filter_option . '='], $argv) !== []
    ) {
        fwrite(STDERR, 'Missing job type for --' . $filter_option . PHP_EOL);
        exit(1);
    }
}

foreach ($options as $option_name => $option_value) {
    if (in_array($option_name, ['h', 'help'])) {
        fwrite(STDOUT, $help_text . PHP_EOL);
        exit(0);
    }

    if (in_array($option_name, ['c', 'clear-lock'])) {
        $clear_lock = true;
    }

    // IMPORTANT: job can be an integer when option is used once or an array when options is used multiple times
    // (e.g. php offline_jobs.php --clear-lock --job=2 --job=10 )
    if (in_array($option_name, ['j', 'job'])) {
        if (!is_array($option_value)) {
            $jobs[] = (int)$option_value;
            continue;
        }
        $jobs = $option_value;
    }

    if (in_array($option_name, ['m', 'max-jobs'])) {
        $max_jobs =  (int)$option_value;
    }

    if (in_array($option_name, ['type', 'exclude-type'], true)) {
        $types = [];
        foreach ((array) $option_value as $type_list) {
            foreach (explode(',', $type_list) as $type) {
                $type = strtolower(trim($type));
                if (!preg_match('/\A[a-z0-9_-]+\z/', $type)) {
                    fwrite(STDERR, "Invalid job type for --" . $option_name . PHP_EOL);
                    exit(1);
                }
                $types[] = $type;
            }
        }
        $types = array_unique($types);
        sort($types);
        if ($option_name === 'type') {
            $job_types = $types;
        } else {
            $excluded_job_types = $types;
        }
    }
}

if ($offline_job_queue) {
    $readonly_jobs = array(
        "collection_download",
        "create_download_file",
        "csv_metadata_export",
        );

    $worker_lock_prefix = 'offlinejobs_';
    if ($job_types !== [] || $excluded_job_types !== []) {
        $filter_key = hash('sha256', serialize([$job_types, $excluded_job_types]));
        $worker_lock_prefix = 'offlinejobs-filtered_' . $filter_key . '_';
    }

    // Only run essential and non-data affecting jobs in read only mode.
    $allowed_types = $job_types;
    if ($system_read_only) {
        $allowed_types = $job_types === [] ? $readonly_jobs : array_intersect($job_types, $readonly_jobs);
        if (array_diff($allowed_types, $excluded_job_types) === []) {
            exit($lang['complete'] . ' ' . date('Y-m-d H:i:s') . PHP_EOL);
        }
    }
    $gettypes = implode(',', $allowed_types);
    // Mark any jobs that are still marked as in progress but have an old process lock as failed
    $runningjobs = job_queue_get_jobs(
        $gettypes,
        STATUS_INPROGRESS,
        job_order_by: 'ref',
        job_sort: 'ASC',
        exclude_types: $excluded_job_types
    );

    $jobcount = count($runningjobs);
    foreach ($runningjobs as $runningjob) {
        $runningjob_data = json_decode($runningjob["job_data"], true);
        if (!is_process_lock("job_" . $runningjob["ref"])) {
            // No current lock in place. Check for presence of an old lock and mark as failed
            $saved_process_lock_max_seconds = $process_locks_max_seconds;
            $process_locks_max_seconds = 9999999;
            if (is_process_lock("job_" . $runningjob["ref"])) {
                echo "Job is in progress (ID: " . $runningjob["ref"] . ") but has exceeded maximum lock time - checking log file for activity\n";

                // Jobs that write a log file may legitimately run for longer than the lock period. Treat recent
                // log activity as proof the job is still alive; anything else (no log file, unreadable log or
                // no recent writes) is considered stale.
                $job_is_stale = true;
                $job_log_file = get_job_queue_log_path_by_ref($runningjob["ref"]);

                if ($job_log_file !== false) {
                    clearstatcache(true, $job_log_file);
                    $modified_time = false;
                    if (file_exists($job_log_file)) {
                        $GLOBALS["use_error_exception"] = true;
                        try {
                            $modified_time = filemtime($job_log_file);
                        } catch (Exception $e) {
                            echo "Attempt to get file modified time for '$job_log_file' failed. Reason: {$e->getMessage()}\n";
                        } finally {
                            unset($GLOBALS["use_error_exception"]);
                        }
                    }

                    if ($modified_time !== false && (time() - (int) $modified_time) <= $saved_process_lock_max_seconds) {
                        $job_is_stale = false;
                    }
                }

                if ($job_is_stale) {
                    echo "Job is in progress (ID: " . $runningjob["ref"] . ") but has no recent log activity - marking as failed\n";
                    job_queue_update($runningjob["ref"], $runningjob_data, STATUS_ERROR);
                    clear_process_lock("job_" . $runningjob["ref"]);
                    $jobcount--;
                } else {
                    echo "Job is in progress (ID: " . $runningjob["ref"] . ") and has recent log activity - continuing\n";
                }
            } else {
                // No lock at all, job is not running
                $jobcount--;
            }
            $process_locks_max_seconds = $saved_process_lock_max_seconds;
        }
    }

    # Extra check for current running instances
    $instances = 0;
    foreach (glob(get_temp_dir() . '/process_locks/' . $worker_lock_prefix . '*') as $lock_file) {
        $lock_file_name = substr($lock_file, strrpos($lock_file, '/') + 1);
        if (is_process_lock($lock_file_name)) {
            $instances++;
        }
    }

    if ($jobcount >= $max_jobs || $instances >= $max_jobs) {
        exit("There are currently " . $max_jobs . " jobs in progress. Exiting\n");
    }

    $lock_name = $worker_lock_prefix . time() . '_' . getmypid();
    if (!set_process_lock($lock_name)) {
        exit("Unable to set process lock. Exiting\n");
    }

    while (!isset($offlinejobs) || count($offlinejobs) > 0) {
        // Get jobs in small batches so that new higher priority jobs won't have to wait if there is a backlog
        $offlinejobs = job_queue_get_jobs(
            $gettypes,
            STATUS_ACTIVE,
            job_order_by: 'priority',
            job_sort: 'ASC',
            maxjobs: 5,
            overdue: true,
            find_by_job_ref: $jobs,
            exclude_types: $excluded_job_types
        );
        $offlinejob = array_shift($offlinejobs);
        if (
            !isset($offlinejob["start_date"])
            || (!empty($jobs) && !in_array($offlinejob["ref"], $jobs))
        ) {
            continue;
        }

        $clear_job_process_lock = false;
        if (PHP_SAPI == 'cli' && $clear_lock && in_array($offlinejob['ref'], $jobs)) {
            $clear_job_process_lock = true;
        }
        if ($offlinejob["start_date"] > date('Y-m-d H:i:s', time())) {
            continue;
        }
        if (job_queue_run_job($offlinejob, $clear_job_process_lock) == 'Process lock') {
            # Process lock when running job. Give some extra time to finish before checking again.
            sleep(2);
        }
    }

    clear_process_lock($lock_name);

    echo $lang["complete"] . ' ' . date('Y-m-d H:i:s') . PHP_EOL;
} else {
    echo $lang["offline_processing_disabled"] . PHP_EOL;
}

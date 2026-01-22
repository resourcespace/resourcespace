<?php

include_once __DIR__ . "/../include/boot.php";
command_line_only();

include_once __DIR__ . "/../include/reporting_functions.php";
include_once __DIR__ . "/../include/action_functions.php";
include_once __DIR__ . "/../include/request_functions.php";

if (is_process_lock("cron")) {
    exit($lang["error-processlock-aborting"] . PHP_EOL);
}
set_process_lock("cron");

$LINE_END = ('cli' == PHP_SAPI) ? PHP_EOL : "<br>";
set_time_limit($cron_job_time_limit);
ob_end_flush();
ob_implicit_flush();
ob_start();

$this_run_start = date("Y-m-d H:i:s");
echo "{$this_run_start} {$baseurl} Starting cron process..." . $LINE_END;

# Get last cron date
$lastcron       = get_sysvar('last_cron', '1970-01-01');
$lastcrontime   = strtotime($lastcron);
$sincelast      = time() - $lastcrontime;

// grab a list of files to run as part of the upgrade process
$new_system_version_files = array();
$files = scandir(__DIR__ .  '/cron_jobs');
$total_upgrade_files = 0;
for ($i = 0; $i <= 999; $i++) {
    foreach ($files as $file) {
        if (preg_match('/^' . str_pad($i, 3, '0', STR_PAD_LEFT) . '_.*\.php/', $file)) {
            $this_job_start = date("Y-m-d H:i:s");
            echo "{$this_job_start} {$baseurl} Executing job: " . $file  . $LINE_END;
            flush();
            ob_flush();
            include __DIR__ .  '/cron_jobs/' . $file;
        }
    }
}

# Allow plugins to add their own cron jobs.
hook("cron");

$this_run_end = date("Y-m-d H:i:s");
echo PHP_EOL . "{$this_run_end} {$baseurl} All tasks complete" .  $LINE_END;

# Update last cron date
set_sysvar("last_cron", date("Y-m-d H:i:s"));
clear_process_lock("cron");

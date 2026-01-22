<?php

include_once __DIR__ . "/../../../include/boot.php";
include_once __DIR__ . "/../include/search_notifications_functions.php";
command_line_only();

// Don't run more than once every 24 hours
$last_search_notifications_cron  = get_sysvar('last_search_notifications_cron', '1970-01-01');

# No need to run if already run in last 24 hours.
if (time() - strtotime($last_search_notifications_cron) < 24 * 60 * 60) {
    if ('cli' == PHP_SAPI) {
        echo " - Skipping search_notifications cron - last run: " . $last_search_notifications_cron . PHP_EOL;
    }
    return false;
}

# Store time to update last run date/time after completion
$this_run_start = date("Y-m-d H:i:s");

define('THIS_PROCESS_LOCK','watchedsearchescron');

if (is_process_lock(THIS_PROCESS_LOCK))
    {
    echo "Process lock in place";
    return;
    }

set_process_lock(THIS_PROCESS_LOCK);

$users = ps_query("SELECT DISTINCT owner FROM search_saved WHERE enabled = 1", array());

foreach ($users as $user)
    {
    $user=$user['owner'];
    $userdetails = get_user($user);

    if ($userdetails["username"] == $anonymous_login ?? "") {
        search_notification_delete_by_owner($user);
        continue;
    }
    setup_user($userdetails);
    search_notification_process($user);
    }

# Update last run date/time.
set_sysvar("last_search_notifications_cron", $this_run_start);
clear_process_lock(THIS_PROCESS_LOCK);

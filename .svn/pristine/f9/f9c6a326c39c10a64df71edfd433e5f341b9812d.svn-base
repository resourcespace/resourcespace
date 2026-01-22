<?php
$rse_search_notifications_plugin_root_path = dirname(__DIR__);
include_once "{$rse_search_notifications_plugin_root_path}/include/search_notifications_functions.php";

function HookRse_search_notificationsCron_copy_hitcountAddplugincronjob()
    {
    echo "\r\n\r\nrse_search_notifications plugin: starting cron process...\r\n";

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

    $users = ps_query("SELECT DISTINCT owner FROM search_saved WHERE enabled = 1 ORDER BY owner ASC;", array());

    foreach($users as $user)
        {
        $user = $user["owner"];
        $userdata = get_user($user);
        if(!$userdata)
            {
            debug("rse_search_notifications: no user found for search owner id: " . $user);
            continue;    
            }
        
        if ($userdata["username"] == ($GLOBALS['anonymous_login'] ?? "")) {
            // Not permitted for this user
            debug("Deleting invalid watched searches for user " . $user);
            search_notification_delete_by_owner($user);
            continue;
        }
        setup_user($userdata);
        $GLOBALS['userdata'][0] = $userdata;
        search_notification_process($user);
        }

    # Update last run date/time.
    set_sysvar("last_search_notifications_cron", $this_run_start);

    return true;
    }
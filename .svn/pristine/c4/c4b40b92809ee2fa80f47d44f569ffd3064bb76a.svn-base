<?php

include_once __DIR__ . "/../include/offline_archive_functions.php";

function HookOffline_archiveAllInitialise()
{
    $offline_archive_config = get_plugin_config('offline_archive');
    if (isset($offline_archive_config["offline_archive_archivepath"]) || isset($offline_archive_config["offline_archive_restorepath"])) {
        // Legacy configs  - remove from plugin settings
        save_removed_ui_config('offline_archive_archivepath');
        save_removed_ui_config('offline_archive_restorepath');
        unset($offline_archive_config["offline_archive_archivepath"]);
        unset($offline_archive_config["offline_archive_restorepath"]);
        set_plugin_config('offline_archive', $offline_archive_config);
    }
	check_removed_ui_config("offline_archive_archivepath");
	check_removed_ui_config("offline_archive_restorepath");
}

function HookOffline_archiveAllAddplugincronjob()
    {
    $linebreak = PHP_SAPI == "cli" ? PHP_EOL : "<br/>"; // Cron may be called from browser
    echo $linebreak . "Offline_archive plugin: running jobs" . $linebreak;
    $errors = offline_archive_run_jobs(PHP_SAPI == "cli");
    if(is_array($errors) && PHP_SAPI == "cli")
        {
        echo "Errors: " . $linebreak . implode($linebreak,$errors) . $linebreak;
        }
    else
        {
        echo "Offline archive job complete" . $linebreak;
        }
    }

<?php

include_once dirname(__FILE__, 2) . '/include/whisper_functions.php';

/**
 * Add whisper processing to the cron.
 *
 * @return void
 */
function HookWhisperAllCron()
{
    global $whisper_cron_enable;

    if ($whisper_cron_enable) {
        //Process a 10GB batch of unprocessed resources
        whisper_process_unprocessed(10);
    } else {
        logScript("Whisper: Processing disabled via plugin config.");
    }
}

/**
 * Prevent downloaded Whisper models from being deleted 
 * 
 * @return string
 */
function HookWhisperAllTemp_block_deletion()
{
    return $GLOBALS['whisper_model_dir'] ?? get_temp_dir() . "whisper";
}

/**
 * Hook into offline jobs list to add custom job
 * 
 * @return array Array of existing job data with custom job added
 * 
 */
function HookWhisperAllAddtriggerablejob(): array
{

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $existing_scripts = $GLOBALS["hook_return_value"];
    } else {
        $existing_scripts = [];
    }

    $scripts = [
        0 => ['name' => 'Whisper', 'lang_string' => 'whisper', 'type' => 'group_start'],
        1 => ['name' => 'Process unprocessed resources', 'lang_string' => 'whisper_process_existing', 'script_name' => 'process_whisper', 'plugin' => 'whisper'],
        2 => ['name' => 'Whisper', 'type' => 'group_end'],
    ];

    return array_merge($existing_scripts, $scripts);
}


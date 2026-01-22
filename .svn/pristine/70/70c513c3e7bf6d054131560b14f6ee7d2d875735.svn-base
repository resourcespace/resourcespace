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



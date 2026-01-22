<?php
function HookWhisperManage_jobsAddjobtriggerpage()
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
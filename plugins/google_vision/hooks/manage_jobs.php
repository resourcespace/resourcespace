<?php
function HookGoogle_visionManage_jobsAddjobtriggerpage()
{

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $existing_scripts = $GLOBALS["hook_return_value"];
    } else {
        $existing_scripts = [];
    }

    $scripts = [
        0 => ['name' => 'Google Vision', 'lang_string' => 'google_vision', 'type' => 'group_start'],
        1 => ['name' => 'Process unprocessed resources', 'lang_string' => 'google_vision_process_existing', 'script_name' => 'process_gv_existing', 'plugin' => 'google_vision'],
        2 => ['name' => 'Google Vision', 'type' => 'group_end'],
    ];

    return array_merge($existing_scripts, $scripts);
}
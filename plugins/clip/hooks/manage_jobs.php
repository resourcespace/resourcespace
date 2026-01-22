<?php
function HookClipManage_jobsAddjobtriggerpage()
{

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $existing_scripts = $GLOBALS["hook_return_value"];
    } else {
        $existing_scripts = [];
    }

    $scripts = [
        0 => ['name' => 'CLIP AI Smart Search', 'lang_string' => 'clip-ai_smart_search', 'type' => 'group_start'],
        1 => ['name' => 'Generate CLIP vectors', 'lang_string' => 'clip-generate_vectors', 'script_name' => 'generate_vectors', 'plugin' => 'clip'],
        2 => ['name' => 'CLIP AI Smart Search', 'type' => 'group_end'],
    ];

    return array_merge($existing_scripts, $scripts);
}
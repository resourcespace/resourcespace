<?php
function HookFacesManage_jobsAddjobtriggerpage()
{

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $existing_scripts = $GLOBALS["hook_return_value"];
    } else {
        $existing_scripts = [];
    }

    $scripts = [
        0 => ['name' => 'Insight Faces', 'lang_string' => 'faces_insight_faces', 'type' => 'group_start'],
        1 => ['name' => 'Detect Faces', 'lang_string' => 'faces_detect_faces','script_name' => 'faces_detect', 'plugin' => 'faces'],
        2 => ['name' => 'Tag Faces', 'lang_string' => 'faces_tag_faces','script_name' => 'faces_tag', 'plugin' => 'faces'],
        3 => ['name' => 'Insight Faces', 'type' => 'group_end'],
    ];

    return array_merge($existing_scripts, $scripts);
}
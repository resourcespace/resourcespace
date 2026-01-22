<?php
function HookOpenai_gptManage_jobsAddjobtriggerpage()
{

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $existing_scripts = $GLOBALS["hook_return_value"];
    } else {
        $existing_scripts = [];
    }

    $scripts = [
        0 => ['name' => 'OpenAI GPT', 'lang_string' => 'openai_gpt', 'type' => 'group_start'],
        1 => ['name' => 'Process existing GPT fields', 'lang_string' => 'openai_gpt_process_existing', 'script_name' => 'process_gpt_existing', 'plugin' => 'openai_gpt'],
        2 => ['name' => 'OpenAI GPT', 'type' => 'group_end'],
    ];

    return array_merge($existing_scripts, $scripts);
}
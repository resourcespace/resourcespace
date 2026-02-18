<?php

function HookGoogle_visionAllInitialise()
{
    global $google_vision_fieldvars;
    config_register_core_fieldvars("Google vision plugin",$google_vision_fieldvars);
}

function HookGoogle_visionAllAfterpreviewcreation(int $resource, int $alternative, bool $generate_all = false): void
{
    global $google_vision_blocked_by_script;
    if (isset($google_vision_blocked_by_script) && $google_vision_blocked_by_script) {
        # Don't use google vision for this resource as request originated in a script where we have chosen to disable this.
        return;
    }

    if ($alternative === -1) {
        // Nothing to do for alternatives; Google Vision is processed for the main file only.
        include_once __DIR__ . "/../include/google_vision_functions.php";
        google_visionProcess($resource);
    } 
}

/**
 * Hook into offline jobs list to add custom job
 * 
 * @return array Array of existing job data with custom job added
 * 
 */
function HookGoogle_visionAllAddtriggerablejob(): array
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
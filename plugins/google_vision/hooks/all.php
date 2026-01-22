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
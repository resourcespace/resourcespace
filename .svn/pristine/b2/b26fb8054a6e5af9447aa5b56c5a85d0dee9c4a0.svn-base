<?php

function HookConditional_termsAllInitialise()
{
    global $conditional_terms_fieldvars;
    config_register_core_fieldvars("Conditional terms plugin", $conditional_terms_fieldvars);
}


function HookConditional_termsAllExtra_checks()
{
    global $lang;

    if (!conditional_terms_config_check()) {
        $message['conditional_terms'] = [
            'status' => 'FAIL',
            'info' => $lang['conditional_terms_plugin_misconfigured'],
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
        return $message;
    }
}

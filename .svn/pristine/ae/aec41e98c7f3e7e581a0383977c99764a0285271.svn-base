<?php
function HookPropose_changesUser_preferencesAdd_user_preference_page_def($page_def)
{
    global $actions_propose_changes, $lang, $enable_disable_options;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $page_def = $GLOBALS["hook_return_value"];
    }
    
    $lastactionkey = array_search('AFTER_ACTIONS_MARKER',$page_def);
    $addoption = config_add_boolean_select('actions_propose_changes', $lang['actions_propose_changes'], $enable_disable_options, 300, '', true);
    array_splice($page_def, $lastactionkey, 0,array($addoption));
    return $page_def;
}

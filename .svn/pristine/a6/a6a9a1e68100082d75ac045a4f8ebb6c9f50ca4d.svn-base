<?php
function HookLicensemanagerUser_preferencesAdd_user_preference_page_def($page_def)
{
    global $user_pref_license_notifications, $lang, $enable_disable_options;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $page_def = $GLOBALS["hook_return_value"];
    }
    
    $lastmessageskey = array_search('AFTER_MESSAGES_MARKER',$page_def);
    $addoption = config_add_boolean_select('user_pref_license_notifications', $lang['user_pref_license_notifications'], $enable_disable_options, 300, '', true);
    array_splice($page_def, $lastmessageskey, 0, array($addoption));

    return $page_def;
}
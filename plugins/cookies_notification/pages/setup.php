<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit($lang['error-permissiondenied']);
}

$plugin_name = 'cookies_notification';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$page_def[] = config_add_boolean_select(
    'cookies_notification_allow_using_site_on_no_feedback',
    $lang['cookies_notification_allow_using_site_on_no_feedback_label']
);

// Render setup page ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $lang['cookies_notification_configuration']);
include '../../../include/footer.php';

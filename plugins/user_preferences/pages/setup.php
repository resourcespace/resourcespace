<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

// Specify the name of this plugin, the heading to display for the page.
$plugin_name = 'user_preferences';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['user_preferences_configuration'];

$choices = array($lang['no'], $lang['yes']);

// Build the config page
$page_def[] = config_add_boolean_select('user_preferences_change_username',
        $lang['user_preferences_change_username'], $choices);
$page_def[] = config_add_boolean_select('user_preferences_change_email',
        $lang['user_preferences_change_email'], $choices);
$page_def[] = config_add_boolean_select('user_preferences_change_name',
        $lang['user_preferences_change_name'], $choices);

config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);

include '../../../include/footer.php';

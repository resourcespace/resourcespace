<?php
#
# Setup page for rss2 plugin
#

// Do the include and authorization checking ritual.
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'rss2';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$plugin_page_heading = $lang['rss_setup_heading'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
// Each element of $page_def describes one configuration variable. Each description is 
// created by one of the config_add_xxxx helper functions. See their definitions and
// descriptions in include/plugin_functions for more information.

$page_def[] = config_add_multi_ftype_select('rss_fields', $lang['rss_fields']);
$page_def[] = config_add_text_input('rss_ttl', $lang['rss_ttl']);
$page_def[] = config_add_boolean_select('rss_show_field_titles', $lang['rss_show_field_titles']);

// Do the page generation ritual.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

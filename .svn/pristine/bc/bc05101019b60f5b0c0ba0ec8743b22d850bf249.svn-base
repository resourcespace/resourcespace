<?php
#
# wordpress_sso setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

global $baseurl;
// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'wordpress_sso';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$plugin_page_heading = $lang['wordpress_sso_configuration'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.

$page_def[] = config_add_text_input('wordpress_sso_secret',$lang['wordpress_sso_secret']);
$page_def[] = config_add_text_input('wordpress_sso_url',$lang['wordpress_sso_url']);
$page_def[] = config_add_boolean_select('wordpress_sso_auto_create',$lang['wordpress_sso_auto_create']);
$page_def[] = config_add_boolean_select('wordpress_sso_auto_approve',$lang['wordpress_sso_auto_approve']);
$page_def[] = config_add_text_input('wordpress_sso_auto_create_group',$lang['wordpress_sso_auto_create_group']);
$page_def[] = config_add_boolean_select('wordpress_sso_allow_standard_login',$lang['wordpress_sso_allow_standard_login']);


// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

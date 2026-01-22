<?php

#
# falcon_link setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

global $baseurl;
// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'falcon_link';

if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$plugin_page_heading = $lang['falcon_link_configuration'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_text_input('falcon_link_api_key', $lang['falcon_link_api_key']);
$page_def[] = config_add_single_ftype_select('falcon_link_text_field', $lang["falcon_link_text_field"], 420);
$page_def[] = config_add_multi_ftype_select('falcon_link_tag_fields', $lang["falcon_link_tag_fields"], 420);
$page_def[] = config_add_text_input('falcon_link_default_tag', $lang['falcon_link_default_tag']);
$page_def[] = config_add_single_ftype_select('falcon_link_id_field', $lang["falcon_link_id_field"], 420);
$page_def[] = config_add_multi_rtype_select('falcon_link_restypes', $lang['falcon_link_resource_types_to_include'], 420);
$page_def[] = config_add_text_input('falcon_link_filter', $lang['falcon_link_filter']);
$page_def[] = config_add_text_input('falcon_link_share_user', $lang['falcon_link_share_user']);
$page_def[] = config_add_multi_group_select('falcon_link_usergroups', $lang['falcon_link_usergroups'], 420);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

<?php

#
# adobe_link setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

global $baseurl;
// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'adobe_link';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}
$plugin_page_heading = $lang['adobe_link_configuration'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_text_list_input('adobe_link_document_extensions', $lang['adobe_link_document_extensions']);
$page_def[] = config_add_text_list_input('adobe_link_asset_extensions', $lang['adobe_link_asset_extensions']);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

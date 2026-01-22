<?php

#
# conditional_terms setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

global $baseurl;
// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'conditional_terms';

if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$plugin_page_heading = $lang['conditional_terms_title'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.

$page_def[] = config_add_html($lang['conditional_terms_description']);
$page_def[] = config_add_single_ftype_select('conditional_terms_field', $lang['conditional_terms_field']);
$page_def[] = config_add_text_input('conditional_terms_value', $lang['conditional_terms_value']);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

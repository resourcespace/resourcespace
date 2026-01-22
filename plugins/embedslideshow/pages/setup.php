<?php

#
# Embed Slideshow setup
#

// Do the include and authorization checking ritual -- don't change this section.
include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'embedslideshow';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}
$plugin_page_heading = $lang["embedslideshowconfig"];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_boolean_select('embedslideshow_textfield', $lang['embedslideshow_textfield']);
$page_def[] = config_add_single_ftype_select('embedslideshow_resourcedatatextfield', $lang['embedslideshow_resourcedatatextfield']);
$page_def[] = config_add_boolean_select('embedslideshow_dynamic_size', $lang['embedslideshow_dynamic_size']);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

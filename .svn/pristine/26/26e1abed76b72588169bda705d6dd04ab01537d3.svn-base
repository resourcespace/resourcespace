<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'stencilvg';
if (!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['stencilvg-go'];
$page_intro = "<p></p>";

// Build configuration variable descriptions

$page_def[] = config_add_single_rtype_select("stencilvg_resource_type_for_new", $lang["stencilvg_resource_type_for_new"]);

$page_def[] = config_add_text_input("stencilvg_dpi", $lang["stencilvg_dpi"]);


// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';
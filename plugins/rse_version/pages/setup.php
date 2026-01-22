<?php
include_once "../../../include/boot.php";

include "../../../include/authenticate.php"; if (!checkperm("u")) {exit ("Permission denied.");}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'rse_version';
$plugin_page_heading = $lang['rse_version_configuration'];
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
 
$page_def[] = config_add_multi_group_select('rse_version_override_groups',$lang['rse_version_override_groups']);


// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';


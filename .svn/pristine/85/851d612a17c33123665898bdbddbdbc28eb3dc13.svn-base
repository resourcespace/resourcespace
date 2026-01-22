<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'sensitive_images';
if (!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['sensitive_images'];
$page_intro = "<p>" . $lang['sensitive_images_help'] . "</p>";

$page_def[]= config_add_single_ftype_select("sensitive_images_field", $lang["sensitive_images_field"],300,false,$TEXT_FIELD_TYPES); 
$page_def[]= config_add_single_select("sensitive_images_blur_level",$lang["sensitive_images_blur_level"],
            array(4=>"1",5=>"2",6=>"3",7=>"4",8=>"5",9=>"6"));

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';

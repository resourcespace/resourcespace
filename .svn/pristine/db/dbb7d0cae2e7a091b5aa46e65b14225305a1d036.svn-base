<?php
#
# Setup page for transform plugin
#

// Do the include and authorization checking ritual.
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin, the heading to display for the page.
$plugin_name = 'transform';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['transform_configuration'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_text_list_input('cropper_formatarray', $lang['output_formats']);
$page_def[] = config_add_text_list_input('cropper_allowed_extensions', $lang['input_formats']);
$page_def[] = config_add_boolean_select('cropper_allow_scale_up', $lang['allow_upscale']);
$page_def[] = config_add_boolean_select('cropper_rotation', $lang['allow_rotation']);
$page_def[] = config_add_boolean_select('cropper_transform_original', $lang['allow_transform_original']);
$page_def[] = config_add_boolean_select('cropper_use_repage', $lang['use_repage']);
$page_def[] = config_add_boolean_select('cropper_enable_batch', $lang['enable_batch_transform']);
$page_def[] = config_add_boolean_select('cropper_enable_alternative_files', $lang['cropper_enable_alternative_files']);
$page_def[]= config_add_multi_group_select("cropper_restricteduse_groups",$lang["cropper_restricteduse_groups"]);
$page_def[] = config_add_text_list_input('cropper_resolutions', $lang['cropper_resolutions']);
$page_def[] = config_add_boolean_select('cropper_quality_select', $lang['cropper_quality_select']);
$page_def[] = config_add_boolean_select('cropper_jpeg_rgb', $lang['cropper_jpeg_rgb']);
$page_def[] = config_add_boolean_select('cropper_srgb_option', $lang['cropper_srgb_option']);
$page_def[] = config_add_boolean_select('use_system_icc_profile_config', $lang['use_system_icc_profile_config']);
$page_def[] = config_add_boolean_select('cropper_use_filename_as_title', $lang['use_filename_as_title']);

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);
include '../../../include/footer.php';

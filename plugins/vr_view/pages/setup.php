<?php
include_once "../../../include/boot.php";

include "../../../include/authenticate.php"; if (!checkperm("u")) {exit ("Permission denied.");}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'vr_view';
$plugin_page_heading = $lang['vr_view_configuration'];
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}

$page_def[] = config_add_boolean_select('vr_view_google_hosted',$lang['vr_view_google_hosted']);
$page_def[] = config_add_text_input('vr_view_js_url', $lang['vr_view_js_url']);
$page_def[] = config_add_multi_rtype_select('vr_view_restypes', $lang['vr_view_restypes']);

$page_def[] = config_add_boolean_select('vr_view_autopan',$lang['vr_view_autopan']);
$page_def[] = config_add_boolean_select('vr_view_vr_mode_off',$lang['vr_view_vr_mode_off']);

$page_def[] = config_add_boolean_select('vr_view_orig_image',$lang['vr_view_orig_image']);
$page_def[] = config_add_boolean_select('vr_view_orig_video',$lang['vr_view_orig_video']);

$page_def[] = config_add_section_header($lang['vr_view_condition'], $lang['vr_view_condition_detail']);
$page_def[] = config_add_single_ftype_select('vr_view_projection_field', $lang['vr_view_projection_field'],420);
$page_def[] = config_add_text_input('vr_view_projection_value', $lang['vr_view_projection_value']);

// Extra options
$page_def[] = config_add_section_header($lang['vr_view_additional_options'], $lang['vr_view_additional_options_detail']);
$page_def[] = config_add_single_ftype_select('vr_view_stereo_field', $lang['vr_view_stereo_field'],420);
$page_def[] = config_add_text_input('vr_view_stereo_value', $lang['vr_view_stereo_value']);
$page_def[] = config_add_single_ftype_select('vr_view_yaw_only_field', $lang['vr_view_yaw_only_field'],420);
$page_def[] = config_add_text_input('vr_view_yaw_only_value', $lang['vr_view_yaw_only_value']);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';


<?php

include_once "../../../include/boot.php";

include "../../../include/authenticate.php";
if (!checkperm("u")) {
    exit(escape($lang['error-permissiondenied']));
}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'panoramic_viewer';
$plugin_page_heading = $lang['panoramic_viewer_configuration'];
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$page_def[] = config_add_multi_rtype_select('panoramic_viewer_restypes', $lang['panoramic_viewer_restypes']);

$page_def[] = config_add_boolean_select('panoramic_viewer_autopan', $lang['panoramic_viewer_autopan']);
$page_def[] = config_add_boolean_select('panoramic_viewer_vr_mode_off', $lang['panoramic_viewer_vr_mode_off']);

$page_def[] = config_add_boolean_select('panoramic_viewer_orig_image', $lang['panoramic_viewer_orig_image']);
$page_def[] = config_add_boolean_select('panoramic_viewer_orig_video', $lang['panoramic_viewer_orig_video']);

$page_def[] = config_add_section_header(
    $lang['panoramic_viewer_condition'],
    $lang['panoramic_viewer_condition_detail'],
);
$page_def[] = config_add_single_ftype_select(
    'panoramic_viewer_projection_field',
    $lang['panoramic_viewer_projection_field'],
    420,
);
$page_def[] = config_add_text_input('panoramic_viewer_projection_value', $lang['panoramic_viewer_projection_value']);

// Extra options
$page_def[] = config_add_section_header(
    $lang['panoramic_viewer_additional_options'],
    $lang['panoramic_viewer_additional_options_detail'],
);
$page_def[] = config_add_single_ftype_select(
    'panoramic_viewer_stereo_field',
    $lang['panoramic_viewer_stereo_field'],
    420,
);
$page_def[] = config_add_text_input('panoramic_viewer_stereo_value', $lang['panoramic_viewer_stereo_value']);
$page_def[] = config_add_single_ftype_select(
    'panoramic_viewer_yaw_only_field',
    $lang['panoramic_viewer_yaw_only_field'],
    420,
);
$page_def[] = config_add_text_input('panoramic_viewer_yaw_only_value', $lang['panoramic_viewer_yaw_only_value']);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

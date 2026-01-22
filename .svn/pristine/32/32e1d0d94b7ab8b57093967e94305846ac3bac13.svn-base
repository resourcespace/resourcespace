<?php
include "../../../include/boot.php";

include "../../../include/authenticate.php"; if (!checkperm("u")) {exit ("Permission denied.");}

// Specify the name of this plugin, the heading to display for the page
$plugin_name = 'video_splice';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['videospliceconfiguration'];


$page_def[] = config_add_single_rtype_select("videosplice_resourcetype",$lang["video_resource_type"]);
$page_def[] = config_add_text_list_input('videosplice_allowed_extensions', $lang['video_allowed_extensions']);
$page_def[] = config_add_single_ftype_select("videosplice_description_field",$lang["description_resource_field"]);
$page_def[] = config_add_single_ftype_select("videosplice_video_bitrate_field",$lang["video_bitrate_resource_field"]);
$page_def[] = config_add_single_ftype_select("videosplice_video_size_field",$lang["video_size_resource_field"]);
$page_def[] = config_add_single_ftype_select("videosplice_frame_rate_field",$lang["frame_rate_resource_field"]);
$page_def[] = config_add_single_ftype_select("videosplice_aspect_ratio_field",$lang["aspect_ratio_resource_field"]);

// Page generation
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);
include '../../../include/footer.php';


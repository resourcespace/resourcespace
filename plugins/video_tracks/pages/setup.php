<?php
#
# video_tracks setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'video_tracks';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$plugin_page_heading = $lang['video_tracks_title'];

$page_def[] = config_add_html("<h2>" . $lang['video_tracks_intro'] . "</h2>");
$page_def[] = config_add_boolean_select('video_tracks_convert_vtt', $lang['video_tracks_convert_vtt']);
$page_def[] = config_add_text_list_input('video_tracks_permitted_video_extensions', $lang['video_tracks_permitted_video_extensions']);
$page_def[] = config_add_text_list_input('video_tracks_audio_extensions', $lang['video_tracks_audio_extensions']);
$page_def[] = config_add_text_list_input('video_tracks_subtitle_extensions', $lang['video_tracks_subtitle_extensions']);
$page_def[] = config_add_text_input('video_tracks_process_size_limit', $lang['video_tracks_process_size_limit']);
$page_def[] = config_add_boolean_select('video_tracks_allow_original_custom_formats', $lang['video_tracks_use_for_custom_video_formats_of_original_label']);


// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
        
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';
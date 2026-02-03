<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'google_vision';
if (!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['google_vision_api'];
$page_intro = "<p>" . $lang['google_vision_help'] . "</p>";

// Build configuration variable descriptions
$page_def[] = config_add_text_input("google_vision_api_key",$lang["google_vision_api_key"]);
$page_def[]= config_add_single_ftype_select("google_vision_label_field", $lang["google_vision_label_field"],300,false,$FIXED_LIST_FIELD_TYPES); 
$page_def[]= config_add_single_ftype_select("google_vision_landmarks_field", $lang["google_vision_landmarks_field"],300,false,$TEXT_FIELD_TYPES); 
$page_def[]= config_add_single_ftype_select("google_vision_text_field", $lang["google_vision_text_field"],300,false,$TEXT_FIELD_TYPES); 

$page_def[]= config_add_single_ftype_select("google_vision_logo_field", $lang["google_vision_logo_field"],300,false,$FIXED_LIST_FIELD_TYPES); 


$page_def[] = config_add_multi_rtype_select("google_vision_restypes", $lang["google_vision_restypes"]);

$page_def[] = config_add_multi_select("google_vision_features", $lang["google_vision_features"], array("LABEL_DETECTION","LANDMARK_DETECTION","TEXT_DETECTION", "LOGO_DETECTION"), false);

$page_def[] = config_add_boolean_select("google_vision_autotitle", $lang["google_vision_autotitle"],'',300);

$page_def[] = config_add_section_header($lang["google_vision_face_detect"]);
$page_def[]= config_add_single_ftype_select("google_vision_face_detect_field", $lang["google_vision_face_detect_field"],300,false,array(FIELD_TYPE_TEXT_BOX_MULTI_LINE,FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE)); 
$page_def[]= config_add_single_ftype_select("google_vision_face_dependent_field", $lang["google_vision_face_dependent_field"],300); 
$page_def[] = config_add_boolean_select("google_vision_face_detect_fullface", $lang["google_vision_face_detect_fullface"],'',300); 
$page_def[] = config_add_boolean_select("google_vision_face_detect_verbose", $lang["google_vision_face_detect_verbose"],'',300);


$page_def[] = config_add_section_header($lang["google_vision_translation"]);
$page_def[] = config_add_text_input("google_vision_translation_api_key",$lang["google_vision_translation_api_key"]);
$page_def[] = config_add_text_input("google_vision_translation_languages",$lang["google_vision_translation_languages"]);
$page_def[] = config_add_boolean_select("google_vision_translation_keep_english", $lang["google_vision_translation_keep_english"],'',300);

if (job_trigger_permission_check()) {
    $page_def[] = config_add_section_header("Offline Jobs");
    $page_def[] = config_add_html("<p>" . escape($lang["google_vision_process_existing_configure"]) . " <input type=\"button\" value=\"" . escape($lang["job_configure"]) . "\" onclick=\"window.location.href='" . 
                                    generateURL($baseurl_short . "/plugins/google_vision/pages/offline_jobs/process_gv_existing.php", ['job_user' => 0, 'plugin' => 1]) . "'\"></p>");
}

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';

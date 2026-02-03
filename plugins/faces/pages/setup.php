<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$plugin_name = "faces";
$page_heading = $lang['faces-configuration'];
$page_intro = "";

$page_def[] = config_add_text_input("faces_service_endpoint", $lang["faces-service-endpoint"]);

// Build configuration variable descriptions

$page_def[] = config_add_percent_range("faces_confidence_threshold", $lang["faces-confidence-threshold"]);
$page_def[] = config_add_percent_range("faces_match_threshold", $lang["faces-match-threshold"]);
$page_def[] = config_add_percent_range("faces_tag_threshold", $lang["faces-tag-threshold"]);

$page_def[] = config_add_single_ftype_select("faces_tag_field", $lang["faces-tag-field"], 300, false, [FIELD_TYPE_DYNAMIC_KEYWORDS_LIST]);

$page_def[] = config_add_boolean_select("faces_detect_on_upload", $lang["faces-detect-on-upload"]);
$page_def[] = config_add_boolean_select("faces_tag_on_upload", $lang["faces-tag-on-upload"]);

$page_def[] = config_add_html("<p><br />" . $lang["faces_count_faces"] . ": " . faces_count_faces());
$page_def[] = config_add_html("<br />" . $lang["faces_count_missing"] . ": " . faces_count_missing() . "</p>");

if (job_trigger_permission_check()) {
    $page_def[] = config_add_section_header("Offline Jobs");
    $page_def[] = config_add_html("<p>" . escape($lang["faces_detect_faces_configure"]) . " <input type=\"button\" value=\"" . escape($lang["job_configure"]) . "\" onclick=\"window.location.href='" . 
                                    generateURL($baseurl_short . "/plugins/faces/pages/offline_jobs/faces_detect.php", ['job_user' => 0, 'plugin' => 1]) . "'\"></p>");
    $page_def[] = config_add_html("<p>" . escape($lang["faces_tag_faces_configure"]) . " <input type=\"button\" value=\"" . escape($lang["job_configure"]) . "\" onclick=\"window.location.href='" . 
                                    generateURL($baseurl_short . "/plugins/faces/pages/offline_jobs/faces_tag.php", ['job_user' => 0, 'plugin' => 1]) . "'\"></p>");
}

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';

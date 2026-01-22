<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$plugin_name = "tesseract";
$page_heading = $lang['tesseract_configuration'];
$page_intro = "";

$page_def[] = config_add_single_ftype_select("tesseract_field", $lang["tesseract_field"], 600, false, $TEXT_FIELD_TYPES);
$page_def[] = config_add_text_input("tesseract_extensions", $lang["tesseract_extensions"], false, 600);

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';

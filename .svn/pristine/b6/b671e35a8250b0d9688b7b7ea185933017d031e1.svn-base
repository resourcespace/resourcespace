<?php

include "../../../include/boot.php";
include "../../../include/authenticate.php";

if (!checkperm("u")) {
    exit("Permission denied.");
}

$plugin_name = 'embeddocument';

if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$page_heading = $lang['embeddocument_heading'];
$page_def[] = config_add_single_rtype_select("embeddocument_resourcetype", $lang["embeddocument_specify_resourcetype"]);

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);
include '../../../include/footer.php';

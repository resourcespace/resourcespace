<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    http_response_code(401);
    exit($lang['error-permissiondenied']);
}

$plugin_name = 'custom_filename';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$page_def[] = config_add_single_ftype_select('cf_field', $lang['custom_filename_field_label'], 420);

config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';

if (isset($error)) {
    $error = escape($error);
    echo "<div class=\"PageInformal\">{$error}</div>";
}

config_gen_setup_html($page_def, $plugin_name, null, $lang['custom_filename_configuration']);
include '../../../include/footer.php';

<?php

// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'totp';
if (!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['totp_set_up'];
$page_intro = "<p></p>";

// Build configuration variable descriptions

$page_def[] = config_add_single_select ("totp_date_binding",$lang["totp_date_binding"],
[
    "Ymd" => $lang["totp_date_binding_d"],
    "YW"  => $lang["totp_date_binding_w"],
    "Ym"  => $lang["totp_date_binding_m"],
    "Y"   => $lang["totp_date_binding_y"],
]);

$page_def[] = config_add_boolean_select("totp_saml", $lang['totp_saml']);

// Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading, $page_intro);
include '../../../include/footer.php';
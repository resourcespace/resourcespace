<?php
include "../../../include/boot.php";

include "../../../include/authenticate.php"; if (!checkperm("a")) {exit ("Permission denied.");}
$plugin_page_heading = 'License Manager';
$plugin_name = 'licensemanager';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}

$page_def[] = config_add_text_list_input(
    'license_usage_mediums',
    $lang['license_manager_mediums']
);

$workflow_states = get_workflow_state_names();

#Prepend a blank option
$workflow_states = ['' => $lang['license_no_archiving']] + $workflow_states;

$page_def[] = config_add_boolean_select("license_expiry_notification", $lang["license_expiry_notification"], array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']));
$page_def[] = config_add_text_input("license_expiry_notification_days", $lang["license_expiry_notification_days"], false);

$page_def[] = config_add_boolean_select("license_attach_upload", $lang["license_attach_upload"], array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']));
$page_def[] = config_add_single_select("license_expired_workflow_state", $lang["license_expired_workflow_state"], $workflow_states);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';
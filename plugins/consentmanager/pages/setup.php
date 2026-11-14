<?php

include "../../../include/boot.php";
include "../../../include/authenticate.php";

if (!checkperm("a")) {
    exit(escape($lang["error-permissiondenied"]));
}

$plugin_page_heading = 'Consent Manager';
$plugin_name = 'consentmanager';

if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$page_def[] = config_add_text_list_input(
    'consent_usage_mediums',
    $lang['consent_manager_mediums']
);

$workflow_states = get_workflow_state_names();

#Prepend a blank option
$workflow_states = ['' => $lang['consent_no_archiving']] + $workflow_states;

$page_def[] = config_add_boolean_select("consent_expiry_notification", $lang["consent_expiry_notification"], array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']));
$page_def[] = config_add_text_input("consent_expiry_notification_days", $lang["consent_expiry_notification_days"], false);

$page_def[] = config_add_boolean_select("consent_attach_upload", $lang["consent_attach_upload"], array($lang['userpreference_disable_option'], $lang['userpreference_enable_option']));
$page_def[] = config_add_single_select("consent_expired_workflow_state", $lang["consent_expired_workflow_state"], $workflow_states);

$page_def[] = config_add_html('<h2>' . escape($lang['cm_external_configure']) . '</h2>');
$page_def[] = config_add_html('<p><a href="'
    . escape($baseurl_short . 'plugins/consentmanager/pages/templates.php')
    . '" onclick="return CentralSpaceLoad(this, true);"><i class="icon-file-text" aria-hidden="true"></i> '
    . escape($lang['cm_external_templates']) . '</a></p>');
foreach (['enabled', 'drawn', 'typed', 'originals', 'reminders', 'recipient_copy', 'include_requester'] as $external_option) {
    $page_def[] = config_add_boolean_select(
        'consentmanager_external_' . $external_option,
        $lang['cm_external_' . $external_option]
    );
}
foreach (['validity_days', 'default_template', 'reminder_days', 'reminder_limit',
    'audit_days', 'temporary_days', 'max_resources'] as $external_option) {
    $page_def[] = config_add_text_input(
        'consentmanager_external_' . $external_option,
        $lang['cm_external_' . $external_option],
        false
    );
}

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

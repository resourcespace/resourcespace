<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit(escape($lang['error-permissiondenied']));
}

$plugin_name = 'antivirus';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

$archive_states = array();
foreach (get_editable_states($userref) as $state) {
    $archive_states[$state['id']] = $state['name'];
}

if (!isset($antivirus_path) || trim($antivirus_path) == '') {
    $error = $lang['antivirus_av_not_setup_error'];
}

$page_def[] = config_add_single_select(
    'antivirus_action',
    $lang['antivirus_action_label'],
    array(
        ANTIVIRUS_ACTION_DELETE     => $lang['antivirus_action_delete'],
        ANTIVIRUS_ACTION_QUARANTINE => $lang['antivirus_action_quarantine']
    )
);
$page_def[] = config_add_single_select(
    'antivirus_quarantine_state',
    $lang['antivirus_quarantine_status_label'],
    $archive_states
);

// Render setup page ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
render_top_page_error_style($error ?? '');
config_gen_setup_html($page_def, $plugin_name, null, $lang['antivirus_configuration']);
include '../../../include/footer.php';

<?php
#
# offline_archive setup page
#

include '../../../include/boot.php';
include '../../../include/authenticate.php';
if (!checkperm('a'))
    {
    exit (escape($lang['error-permissiondenied']));
    }

$plugin_name = 'offline_archive';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
    check_removed_ui_config("offline_archive_archivepath");
    check_removed_ui_config("offline_archive_restorepath");
}

$plugin_page_heading = $lang['offline_archive_configuration'];

$page_def[] = config_add_single_ftype_select('offline_archive_archivefield',$lang['offline_archive_archivefield']);

// Removed from UI
$helptext = str_replace("%variable","\$offline_archive_archivepath",$lang['ui_removed_config_message']);
$showval = $offline_archive_archivepath !== "" ? $offline_archive_archivepath : $lang["notavailableshort"];
$page_def[] = config_add_fixed_input($lang['offline_archive_archivepath'], $showval, $helptext);

$helptext = str_replace("%variable","\$offline_archive_restorepath",$lang['ui_removed_config_message']);
$page_def[] = config_add_fixed_input($lang['offline_archive_restorepath'], $offline_archive_restorepath, $helptext);

$page_def[] = config_add_boolean_select('offline_archive_preservedate', $lang['offline_archive_preservedate']);
// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';

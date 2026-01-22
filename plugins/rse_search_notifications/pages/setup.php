<?php
#
# RSE search notifications setup
#

// Do the include and authorization checking ritual -- don't change this section.
include '../../../include/boot.php';
include_once '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'rse_search_notifications';
$plugin_page_heading = $lang['search_notifications_config_heading'];

// Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_single_select(
    'search_notification_max_thumbnails',
    $lang['search_notifications_config_thumbnail_count'],
    array(1,2,3,4,5,6,7,8,9),
    false,
    50,
    null,
    true
);

$page_def[] = config_add_boolean_select(
    'only_show_changes',
    $lang['search_notifications_config_only_changes'],
    '',
    75
);

// Do the page generation ritual -- don't change this section.
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $plugin_page_heading);
include '../../../include/footer.php';



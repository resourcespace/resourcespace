<?php

# Setup page for track_field_history plugin

# Do the include and authorization checking ritual.
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

# Specify the name of this plugin, the heading to display for the page.
$plugin_name = 'track_field_history';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = "Track Field History Configuration";

# Build the $page_def array of descriptions of each configuration variable the plugin uses.
$page_def[] = config_add_multi_ftype_select('track_fields', $lang['track_fields']);


# Do the page generation ritual
config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);
include '../../../include/footer.php';



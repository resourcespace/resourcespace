<?php

include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

// Specify the name of this plugin, the heading to display for the page.
$plugin_name = 'format_chooser';
if(!in_array($plugin_name, $plugins))
    {plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['format_chooser_configuration'];

if ('' != getval('submit', '') || '' != getval('save', '')) {
    $formats = imageMagickFormats();

    $inputs = parseFormatExtensions(getval('format_chooser_input_formats', ''));
    $blocked_inputs = array_diff($inputs, $formats['readable']);
    if (!empty($blocked_inputs)) {
        $blocked_inputs = implode(', ', $blocked_inputs);
        $page_def[] = config_add_html(sprintf($lang["format_chooser_blocked_inputs"], $blocked_inputs));
    }

    $outputs = parseFormatExtensions(getval('format_chooser_output_formats', ''));
    $blocked_outputs = array_diff($outputs, $formats['readable']);
    if (!empty($blocked_outputs)) {
        $blocked_outputs = implode(', ', $blocked_outputs);
        $page_def[] = config_add_html(sprintf($lang["format_chooser_blocked_outputs"], $blocked_outputs));
    }
}

// Build the config page
$page_def[] = config_add_text_list_input('format_chooser_input_formats', $lang['format_chooser_input_formats']);
$page_def[] = config_add_text_list_input('format_chooser_output_formats', $lang['format_chooser_output_formats']);

config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, null, $page_heading);
echo '<p>Please consult config.php directly in order to change the color profile settings.</p>';

include '../../../include/footer.php';

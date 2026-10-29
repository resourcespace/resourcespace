<?php

command_line_only();
# Test overriding variables in the global scope by eval - replicating config override.
# Testing with config who's value is changed from the default in config.default.php

global $metadata_report;
$original_value_metadata_report = $metadata_report;
$metadata_report = false;
$starting_value = $metadata_report;

$config_to_override = '$metadata_report = true;';
$config_to_override_signed = "//SIG" . sign_code($config_to_override) . "\n" . $config_to_override;

override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'test');

if ($starting_value == $GLOBALS['metadata_report']) {
    echo '1. Override was not applied. '; # false didn't become true
    return false;
}


$config_to_override_signed = '';

override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'test');

if ($starting_value != $GLOBALS['metadata_report']) {
    echo '2. Override was not applied. '; # false was changed
    return false;
}

$metadata_report = true;
$config_to_override = '$metadata_report = false;';
$config_to_override_signed = "//SIG" . sign_code($config_to_override) . "\n" . $config_to_override;

override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'test');

if ($starting_value != $GLOBALS['metadata_report']) {
    echo '3. Override was not applied. ';  # true didn't become false
    return false;
}


# Testing with config that isn't defined in config.default.php

if (isset($resource_created_by_filter)) {
    unset($resource_created_by_filter);
}

$config_to_override = '$resource_created_by_filter = array(-1);';
$config_to_override_signed = "//SIG" . sign_code($config_to_override) . "\n" . $config_to_override;

override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'test');

if (!isset($GLOBALS['resource_created_by_filter']) && count($GLOBALS['resource_created_by_filter']) != 0) {
    echo '4. Override was not applied. '; # previously undefined variable was not set
    return false;
}

$config_to_override_signed = '';

override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'test');

if (isset($GLOBALS['resource_created_by_filter'])) {
    echo '5. Override was not applied. Variable was not unset. '; # previously defined variable was not unset
    return false;
}


# Test configs changed by the usergroup are not reverted to their original value if called again as a resource type override.
unset($GLOBALS['configs_overwritten']);
$original_value_edit_autosave = $edit_autosave;
$edit_autosave = true;

$config_to_override = '$edit_autosave = false;';
$config_to_override_signed = "//SIG" . sign_code($config_to_override) . "\n" . $config_to_override;
# Apply usergroup override - $edit_autosave becomes false in global.
override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'usergroup');

$metadata_report = true;
$config_to_override = '$metadata_report = false;';
$config_to_override_signed = "//SIG" . sign_code($config_to_override) . "\n" . $config_to_override;
# Apply override from resource_type.
override_rs_variables_by_eval($GLOBALS, $config_to_override_signed, 'resource_type');

if ($GLOBALS['edit_autosave'] !== false) {
    echo '6. Later applied config overrides are resetting earlier overrides to default values. '; # no longer false
    return false;
}


unset($resource_created_by_filter);
$metadata_report = $original_value_metadata_report;
$edit_autosave = $original_value_edit_autosave;
return true;

<?php

command_line_only();


$newfield = create_resource_type_field("New title", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "newtitle", false);

// Set field to be a standard core variable and ensure that deletion is prevented
$saved_view_title_field = $view_title_field;
$view_title_field = $newfield;
$deleted = delete_resource_type_field($newfield);
$view_title_field = $saved_view_title_field;
if ($deleted === true) {
    return false;
}

// Set field to be a member of standard core array variable and ensure that deletion is prevented
$saved_sort_fields = $sort_fields;
$sort_fields[] = $newfield;
$deleted = delete_resource_type_field($newfield);
$sort_fields = $saved_sort_fields;
if ($deleted === true) {
    return false;
}

// Simulate a plugin registering core field variables
config_register_core_fieldvars("Dummy plugin", array("dummy_plugin_fieldids"));
$plugin_field = create_resource_type_field("Plugin field title", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "pluginfieldtitle", false);
$dummy_plugin_fieldids = array($plugin_field);
$deleted = delete_resource_type_field($plugin_field);
if ($deleted === true) {
    return false;
}

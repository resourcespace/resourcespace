<?php

command_line_only();

$resource1 = create_resource(1);

$autocomplete_field1 = create_resource_type_field('autocomplete_field1');
$autocomplete_macro1 = 'return "test";';
$autocomplete_macro1_value = 'test';
ps_query('UPDATE resource_type_field SET autocomplete_macro = ? WHERE ref = ?', array('s', $autocomplete_macro1, 'i', $autocomplete_field1));
resign_all_code(false, false);

$resource2 = create_resource(2);

$resource2_field_value = get_data_by_field($resource2, $autocomplete_field1);
if ($resource2_field_value != $autocomplete_macro1_value) {
    echo "autocomplete_blank_fields didn't set autocomplete macro value on creation of new resource";
    return false;
}

$resource1_field_value = get_data_by_field($resource1, $autocomplete_field1);
if ($resource1_field_value != "") {
    echo "autocomplete_blank_fields updated an existing resource when called for a new (different) resource.";
    return false;
}


$user_set_value = 'manual';
update_field($resource2, $autocomplete_field1, $user_set_value);
autocomplete_blank_fields($resource1, false);
autocomplete_blank_fields($resource2, false);

$resource2_field_value = get_data_by_field($resource2, $autocomplete_field1);
if ($resource2_field_value != $user_set_value) {
    echo "autocomplete_blank_fields has overwritten existing data which shouldn't have been affected.";
    return false;
}

$resource1_field_value = get_data_by_field($resource1, $autocomplete_field1);
if ($resource1_field_value != $autocomplete_macro1_value) {
    echo "autocomplete_blank_fields didn't fill in blank field value.";
    return false;
}


$autocomplete_macro1 = 'return "test1";';
$autocomplete_macro1_value = 'test1';
ps_query('UPDATE resource_type_field SET autocomplete_macro = ? WHERE ref = ?', array('s', $autocomplete_macro1, 'i', $autocomplete_field1));
resign_all_code(false, false);

$autocomplete_field2 = create_resource_type_field('autocomplete_field2');
$autocomplete_macro2 = 'return "test";';
$autocomplete_macro2_value = 'test';
ps_query('UPDATE resource_type_field SET autocomplete_macro = ? WHERE ref = ?', array('s', $autocomplete_macro2, 'i', $autocomplete_field2));
resign_all_code(false, false);

autocomplete_blank_fields($resource1, true, false, $autocomplete_field1);
autocomplete_blank_fields($resource2, true, false, $autocomplete_field1);

$resource1_field_value_field1 = get_data_by_field($resource1, $autocomplete_field1);
$resource2_field_value_field1 = get_data_by_field($resource2, $autocomplete_field1);
$resource1_field_value_field2 = get_data_by_field($resource1, $autocomplete_field2);
$resource2_field_value_field2 = get_data_by_field($resource2, $autocomplete_field2);

if (($resource1_field_value_field1 != $resource2_field_value_field1) || ($resource2_field_value_field1 != $autocomplete_macro1_value) || ($resource1_field_value_field1 != $autocomplete_macro1_value)) {
    echo "autocomplete_blank_fields didn't update the specified field id.";
    return false;
}

if (($resource1_field_value_field2 != "") || ($resource2_field_value_field2 != "")) {
    echo "autocomplete_blank_fields updated an unexpected field id.";
    return false;
}


update_field($resource2, $autocomplete_field2, $user_set_value);
autocomplete_blank_fields($resource1, true, false);
$returned_changes = autocomplete_blank_fields($resource2, true, true);

$resource1_field_value_field1_old = $resource1_field_value_field1;
$resource2_field_value_field1_old = $resource2_field_value_field1;
$resource1_field_value_field1 = get_data_by_field($resource1, $autocomplete_field1);
$resource2_field_value_field1 = get_data_by_field($resource2, $autocomplete_field1);
$resource1_field_value_field2 = get_data_by_field($resource1, $autocomplete_field2);
$resource2_field_value_field2 = get_data_by_field($resource2, $autocomplete_field2);

if ($resource2_field_value_field2 != $autocomplete_macro2_value) {
    echo "autocomplete_blank_fields didn't overwrite data with force option.";
    return false;
}

if (($resource1_field_value_field2 != $resource2_field_value_field2) || ($resource2_field_value_field2 != $autocomplete_macro2_value) || ($resource1_field_value_field2 != $autocomplete_macro2_value)) {
    echo "autocomplete_blank_fields failed to update blank fields with force option.";
    return false;
}

if (($resource1_field_value_field1_old != $resource1_field_value_field1) || ($resource2_field_value_field1_old != $resource2_field_value_field1)) {
    echo "autocomplete_blank_fields applied the wrong autocomplete macro.";
    return false;
}

if (!is_array($returned_changes) || count($returned_changes) != 2) {
    echo "autocomplete_blank_fields failed to return results affected.";
    return false;
}

return true;

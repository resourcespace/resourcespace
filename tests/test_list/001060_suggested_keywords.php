<?php

// Check that get_Suggested keywords does not return data from fields that user does not have access to
command_line_only();

clear_query_cache("schema");

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$hidefield = create_resource_type_field("Sensitive data", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "sensitive", true);

// Set text for resource
$sensitivename = "Lordlucan";
update_field($resourcea, $hidefield, $sensitivename);

// Ensure that field is not visible to user
$userpermissions[] = 'f-' . $hidefield;

// Set variables that could cause false results
unset($hidden_fields_cache);
$autocomplete_search_min_hitcount = 1;
clear_query_cache("schema");

$input = substr($sensitivename, 0, 5);
$keywords = get_suggested_keywords($input);
if (in_array(strtolower($sensitivename), $keywords)) {
    echo " - Subtest A ";
    return false;
}

// Same test but for node field
$hidenodefield = create_resource_type_field("Sensitive node data", 0, FIELD_TYPE_CHECK_BOX_LIST, "sensitivenode", true);
$sensitivenodename = "Chupacabra";
$sensitivenode = set_node(null, $hidenodefield, $sensitivenodename, null, null);
add_resource_nodes($resourceb, array($sensitivenode));

// Ensure that this new field is not visible to user
$userpermissions[] = 'f-' . $hidenodefield;

// Set variables that could cause false results
unset($hidden_fields_cache);
$autocomplete_search_min_hitcount = 1;
clear_query_cache("schema");

$input = substr($sensitivenodename, 0, 5);
$keywords = get_suggested_keywords($input);

if (in_array(strtolower($sensitivenodename), $keywords)) {
    echo " - Subtest B ";
    return false;
}

return true;

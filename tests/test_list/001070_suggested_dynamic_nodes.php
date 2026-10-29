<?php

// Check that suggest_dynamic_keyword_nodes() returns expected values
command_line_only();

clear_query_cache("schema");
$run_id = test_generate_random_ID(5);
$test1750field = create_resource_type_field("dynamic" . $run_id);

// Set nodes for field
set_node(null, $test1750field, "Anaée", null, "");
set_node(null, $test1750field, "Anais", null, "");

// A - Test that both matches are returned
$results = suggest_dynamic_keyword_nodes($test1750field, "ana", false);
if (count($results) !== 3) {
    echo " - Subtest A ";
    return false;
}

// B - Test that only exact match is returned
$results = suggest_dynamic_keyword_nodes($test1750field, "anaé", false);
if (count($results) !== 2) {
    echo " - Subtest B ";
    return false;
}

// C - Test that no option to add is present if read only mode
$results = suggest_dynamic_keyword_nodes($test1750field, "ana", true);
if (count($results) !== 2) {
    echo " - Subtest C ";
    return false;
}

// D - Test that no option to add is present if no edit access
$userpermissions[] = 'F' . $test1750field;
$results = suggest_dynamic_keyword_nodes($test1750field, "ana", false);
if (count($results) !== 2) {
    echo " - Subtest D ";
    return false;
}

// E - Test that no options are present if field not visible to user
$userpermissions[] = 'f-' . $test1750field;
$results = suggest_dynamic_keyword_nodes($test1750field, "ana", true);
if (!empty($results)) {
    echo " - Subtest E ";
    return false;
}

return true;

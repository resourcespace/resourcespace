<?php

command_line_only();

// Check $wildcard_always_applied search e.g. "cat" will always match "catch", "catalogue", "category"
$wildcard_always_applied_cache = $wildcard_always_applied;
$wildcard_always_applied = false;

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// Add new node to field, use dummy countries in case dbstruct changes and avoid possible stems
$trdpqtskcnode = set_node(null, 3, "trdpqtskc", '', 1000);
// Add trdpqtskc node to resource a
add_resource_nodes($resourcea, array($trdpqtskcnode));

// SUBTEST A
// Do search for 'trdpqts' (should return 0 results)
$results = do_search('trdpqts');
if (is_array($results)) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// SUBTEST B
// Now with wildcard applied
$wildcard_always_applied = true;
// Do search for 'trdpqts' (should return resource a as really searching for 'trdpqts*')
$results = do_search('trdpqts');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// SUBTEST C
// Add plain text to caption field for resource b
update_field($resourceb, 8, "cvwqnthywdd");
$wildcard_always_applied = false;
// Do search for 'cvwqnth' (should return 0 results)
$results = do_search('cvwqnth');
if (is_array($results)) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// SUBTEST D
$wildcard_always_applied = true;
// Do search again for 'cvwqnth' - should now return resource b)
$results = do_search('cvwqnth');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

$wildcard_always_applied = $wildcard_always_applied_cache;

return true;

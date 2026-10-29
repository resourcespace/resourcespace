<?php

command_line_only();

// -Check search for field specific node e.g. 'country:france'

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// Add new nodes to field, use dummy countries in case dbstruct changes
$summerlandnode = set_node(null, 3, "Summerland", '', 1000);
$winterlandnode = set_node(null, 3, "Winterland", '', 1000);
$springlandnode = set_node(null, 3, "Springland", '', 1000);

// Add both  nodes to resource a
add_resource_nodes($resourcea, array($summerlandnode, $winterlandnode));
// Add summerlandnode node to resource b
add_resource_nodes($resourceb, array($summerlandnode));
// Add winterlandnode and springlandnode nodes to resource c
add_resource_nodes($resourcec, array($winterlandnode,$springlandnode));

// Do field specific search for 'Summerland' (should return both resources a and b)
$results = do_search('country:summerland');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb)
) {
    return false;
}

// Do field specific search for 'winterland' (should return resource c)
$results = do_search('country:springland');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec) {
    return false;
}

// Add plain text to caption field for resource c
$results = do_search($resourcea);
update_field($resourcec, 8, "Summerland");

// Confirm that a non-field linked search for 'Summerland' returns all resources a, b and c
$results = do_search('summerland');
if (
    count($results) != 3 || !isset($results[0]['ref']) || !isset($results[1]['ref']) || !isset($results[2]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea && $results[2]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb && $results[2]['ref'] != $resourceb) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb && $results[2]['ref'] != $resourcec)
) {
    return false;
}

// Do field specific search for 'Summerland' again (should still return only both resources a and b)
$results = do_search('country:summerland');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb)
) {
    return false;
}

return true;

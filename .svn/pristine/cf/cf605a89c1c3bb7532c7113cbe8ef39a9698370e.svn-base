<?php

command_line_only();

// -------------- Both old resource_keyword and node_keyword lookups ------------

// create 3 new resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(2, 0);
$resourcec = create_resource(2, 0);
debug("Resource A: " . $resourcea);
debug("Resource B: " . $resourceb);
debug("Resource C: " . $resourcec);

// Add new nodes to field
$zebranode = set_node(null, 73, "zebra", '', 1000);
$giraffenode = set_node(null, 73, "giraffe", '', 1000);
$capybaranode = set_node(null, 73, "capybara", '', 1000);
$mammalnode = set_node(null, 73, "mammal", '', 1000);
$threeblindmicenode = set_node(null, 73, "three blind mice", '', 1000);
$firstthirdsecondnode = set_node(null, 73, "first third second lovely", '', 1000);
debug("node1: " . $zebranode . "\n");
debug("node2: " . $giraffenode . "\n");
debug("node3: " . $capybaranode . "\n");
debug("node4: " . $mammalnode . "\n");

// Add nodes to resource a
add_resource_nodes($resourcea, array($zebranode, $mammalnode));
// Add giraffe  node to resource b
add_resource_nodes($resourceb, array($giraffenode, $mammalnode,$threeblindmicenode));
// Add capybara node to resource c
add_resource_nodes($resourcec, array($capybaranode, $firstthirdsecondnode));

// Add data to title field
update_field($resourcea, $view_title_field, 'Zebedee jumping, first second third lovely'); # "Lovely" checks stemming.
update_field($resourceb, $view_title_field, 'Geoffrey swimming, second first third');
update_field($resourcec, $view_title_field, 'Clifford sleeping, third first second');

// Add data to keyword field that matches an existing node keyword
update_field($resourcea, 1, 'stripy, animal');
update_field($resourceb, 1, 'long, neck, animal');
update_field($resourcec, 1, 'large, rodent, Hydrochoerus, mammal, animal');

// -------------------- Useful SQL: ---------------

// new style keyword lookup:
// select * from resource_node left outer join node_keyword on resource_node.node=node_keyword.node left outer join keyword on node_keyword.keyword=keyword.ref where resource >= 950 and resource <=955

// traditional keyword lookup:
// select * from resource_keyword left outer join keyword on resource_keyword.keyword=keyword.ref where resource >= 950 and resource <=955

// search for 'mammal' which will return resource a, b and c (from keywords and nodes)

$results = do_search('mammal');
if (
    count($results) != 3 || !isset($results[0]['ref']) || !isset($results[1]['ref']) || !isset($results[2]['ref']) ||
    (
        ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea && $results[2]['ref'] != $resourcea) &&
        ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb && $results[2]['ref'] != $resourceb) &&
        ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec && $results[2]['ref'] != $resourcec)
    )
) {
    return false;
}

// search for 'rodent' which will produce 1 result (via resource_keyword)
$results = do_search('rodent');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec) {
    return false;
}

// search for 'capybara' which will produce 1 result (via resource_node->node_keyword)
$results = do_search('giraffe');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    return false;
}

// search for mammal without 'swimming' which will produce 2 results (omit via resource_keyword)
$results = do_search('mammal -swimming');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    (
        ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) &&
        ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
    )
) {
    return false;
}

// search for animal without 'giraffe' which will produce 2 results (omit via resource_node->node_keyword)
$results = do_search('animal -giraffe');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    (
        ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) &&
        ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
    )
) {
    return false;
}

// quoted search via resource_keyword
$results = do_search('"first second third lovely"');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    return false;
}

// quoted search via node_keyword
$results = do_search('"three blind mice"');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    return false;
}

// negative test case to check that incorrect order does not return a match
$results = do_search('"mice blind"');  // this would typically return a suggestion string
if (is_array($results)) {
    return false;
}

// Test node searches after truncating resource_keyword
ps_query("truncate resource_keyword");

// search for 'capybara' which will produce 1 result (via resource_node->node_keyword)
$results = do_search('giraffe');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    return false;
}

// quoted search via node_keyword
$results = do_search('"first third second lovely"');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec) {
    return false;
}

return true;

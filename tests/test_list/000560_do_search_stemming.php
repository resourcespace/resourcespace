<?php

command_line_only();


// Check stemming search e.g.  'fox' should return 'foxes', 'dance' should return 'dancing'
$stemming = true;

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);

// Add new nodes to field, doesn't matter that it is the country field
$foxesnode = set_node(null, 3, "foxes", '', 1000);
$dancingnode = set_node(null, 3, "dancing", '', 1000);

// Get baseline to compare in case pre-existing data changes
$pre_search_fox = do_search('fox');
$foxcount = is_array($pre_search_fox) ? count($pre_search_fox) : 0;
$pre_search_dance = do_search('dance');
$dancecount = is_array($pre_search_dance) ? count($pre_search_dance) : 0;

// Add foxesnode node to resource a
add_resource_nodes($resourcea, array($foxesnode));
// Add dancingnode node to resource b
add_resource_nodes($resourceb, array($dancingnode));

//SUBTEST A
// Do search for 'fox' (should return resource a)
$results = do_search('fox');
if (count($results) != $foxcount + 1 || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}


//SUBTEST B
// Do search for 'dance' (should return resource b)
$results = do_search('dance');
if (count($results) != $dancecount + 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

$stemming = false;

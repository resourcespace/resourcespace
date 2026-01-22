<?php

command_line_only();


// Set up
$user_a = new_user("coffee", 2);
$resource_a = create_resource(1, 0, $user_a);
$resource_b = create_resource(1, 0, $user_a);

$node_a = set_node(null, 8, "coffee filter", '', 1000);
add_resource_nodes($resource_b, array($node_a));

$config_cache = $index_contributed_by;


// TEST A
$index_contributed_by = false;
$results = do_search("coffee");
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resource_b) {
    echo "TEST A - ";
    return false;
}

// TEST B
$index_contributed_by = true;
$results = do_search("coffee", "", "resourceid", 0, -1, 'asc');
if (
    count($results) != 2
    || (!isset($results[0]['ref'])
    || $results[0]['ref'] != $resource_a)
    || (!isset($results[1]['ref'])
    || $results[1]['ref'] != $resource_b)
) {
    echo "TEST B - ";
    return false;
}

// Teardown
$index_contributed_by = $config_cache;
unset($config_cache);

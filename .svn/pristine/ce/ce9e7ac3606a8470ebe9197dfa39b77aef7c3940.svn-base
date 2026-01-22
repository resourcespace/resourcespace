<?php

command_line_only();

// Create arrays of fake words
$password_min_length = 8;
$password_min_alpha = 8;
$password_min_numeric = 0;
$password_min_special = 0;
$shortnodewords = [];
for ($n = 0; $n < 1000; $n++) {
    $shortnodewords[$n] = make_password();
}
$longnodewords = [];
for ($n = 0; $n < 1000; $n++) {
    $longnodewords[$n] = make_password();
}

// Create short nodes
$nodecount = 1000;
$shortnodeids = [];
for ($n = 0; $n < $nodecount; $n++) {
    shuffle($shortnodewords);
    $nodelength = mt_rand(1, 5);
    $nodename = implode(" ", array_slice($shortnodewords, 0, $nodelength));
    $shortnodeids[] = set_node(null, 8, $nodename, null, 0);
}

// Create long nodes
$nodecount = 500;
$longnodeids = [];
for ($n = 0; $n < $nodecount; $n++) {
    shuffle($longnodewords);
    $nodelength = mt_rand(100, 250);
    $nodename = implode(" ", array_slice($longnodewords, 0, $nodelength));
    $longnodeids[] = set_node(null, 8, $nodename, null, 0);
}

// Create resources
$rescount = 1000;
$resources = [];
for ($n = 0; $n < $rescount; $n++) {
    $resources[] = create_resource(1, 0, -1);
}

// Add short nodes
foreach ($resources as $resource) {
    shuffle($shortnodeids);
    // Add 5 short nodes to each resource
    add_resource_nodes($resource, array_slice($shortnodeids, 5));
}

// Add long nodes
foreach ($resources as $resource) {
    $randnode = array_rand($longnodeids);
    // Add 1 long node to each resource
    add_resource_nodes($resource, [$longnodeids[$randnode]]);
}


$start_time = microtime(true);

$searches = [];
$search_repeat = 50;
for ($n = 0; $n < $search_repeat; $n++) {
    clear_query_cache("schema");
    shuffle($longnodeids);
    shuffle($shortnodewords);
    shuffle($longnodewords);

    // Add search for node id
    $searches[] = NODE_TOKEN_PREFIX . $longnodeids[0];

    // Add search for multiple node ids
    $searches[] = NODE_TOKEN_PREFIX . $longnodeids[1] . " " . NODE_TOKEN_PREFIX . $longnodeids[2];

    // Add search for short node names
    $searches[] = implode(" ", array_slice($shortnodewords, 0, 2));

    // Add search for long node names
    $searches[] = implode(" ", array_slice($longnodewords, 0, 2));

    // Add search for quoted node names
    $searches[] = "\"" . implode(" ", array_slice($shortnodewords, 2, 2)) . "\"";
}

foreach ($searches as $search) {
    $results = do_search($search);
}

echo str_pad("Time to perform multiple simple searches (seconds):", 60) . round(microtime(true) - $start_time, 2) . " - ";

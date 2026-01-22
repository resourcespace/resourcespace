<?php

command_line_only();

// Create array of fake words
$password_min_length = 8;
$password_min_alpha = 8;
$password_min_numeric = 0;
$password_min_special = 0;
$words = [];
for ($n = 0; $n < 1000; $n++) {
    $words[$n] = make_password();
}

// Create nodes
$nodecount = 1000;
$nodeids = [];
$start_time = microtime(true);
for ($n = 0; $n < $nodecount; $n++) {
    shuffle($words);
    $nodelength = mt_rand(1, 25);
    $nodename = implode(" ", array_slice($words, 0, $nodelength));
    $nodeids[] = set_node(null, 8, $nodename, null, 0);
}

// Create resources
$rescount = 1000;
$resources = [];
for ($n = 0; $n < $rescount; $n++) {
    $resources[] = create_resource(1, 0, -1);
}
foreach ($resources as $resource) {
    shuffle($nodeids);
    // Add 5 nodes to each resource
    add_resource_nodes($resource, array_slice($nodeids, 5));
}

echo str_pad("Time to add nodes to " . $rescount . " resources (seconds):", 60) . round(microtime(true) - $start_time, 2) . " - ";

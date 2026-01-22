<?php

include "../../include/boot.php";
command_line_only();

// Insert a number of dummy resource records into the database. Useful to create a test database for performance testing.

$rows = $argv[1] ?? 0;
if ($rows == 0) {
    exit("Usage: php dummy_data.php <number of dummy resources to insert>\n");
    exit();
}

// Fetch all nodes.
$nodes = get_nodes();

for ($n = 1; $n <= $rows; $n++) {
    $resource = create_resource(1, 0, 1);

    // Add a fake title based on a random node
    update_field($resource, 8, $nodes[rand(0, count($nodes) - 1)]["name"]);

    // Connect some random nodes.
    $rnodes = [];
    for ($m = 0; $m < 50; $m++) {
        $random = $nodes[rand(0, count($nodes) - 1)]["ref"];
        if (!in_array($random, $rnodes)) {
            $rnodes[] = $random;
        }
    }
    add_resource_nodes($resource, $rnodes);

    if ($n % 10 == 0) {
        echo $n . " resources inserted\n";
        flush();
        ob_flush();
    }
}

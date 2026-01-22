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

$start_time = microtime(true);

// Create nodes
$nodecount = 1000;
for ($n = 0; $n < $nodecount; $n++) {
    shuffle($words);
    $nodelength = mt_rand(1, 25);
    $nodename = implode(" ", array_slice($words, 0, $nodelength));
    set_node(null, 8, $nodename, null, 0);
}

echo str_pad("Time to create " . $nodecount . " nodes (seconds):", 60) . round(microtime(true) - $start_time, 2) . " - ";

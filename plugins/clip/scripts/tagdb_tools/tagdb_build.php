<?php

include_once dirname(__FILE__, 5) . '/include/boot.php';
include_once dirname(__FILE__, 3) . '/include/clip_functions.php';

command_line_only();

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

if (!isset($argv[1]) || $argv[1] == "") {
    exit("Usage: php tagdb_build.php [dictionary name] [prefix]\n");
}
$dict = $argv[1];

if (!isset($argv[2]) || $argv[2] == "") {
    exit("Usage: php tagdb_build.php [dictionary name] [prefix]\n");
}
$prefix = $argv[2];


// blank file
file_put_contents($dict . ".tagdb", "");

$words = explode("\n", file_get_contents($dict . ".txt"));
foreach ($words as $word) {
    $word = trim($word);
    echo str_pad($word, 15);
    $vector = get_vector(true, $prefix . " " . $word, -1);
    $vector = array_map('floatval', $vector); // ensure float values

  // Write vector to file.
    file_put_contents($dict . ".tagdb", $word . " " . implode(' ', array_map('strval', $vector)) . "\n", FILE_APPEND | LOCK_EX);

    echo vector_visualise($vector) . "\n";
}

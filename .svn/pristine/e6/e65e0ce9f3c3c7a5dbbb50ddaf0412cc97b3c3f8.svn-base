<?php

include_once dirname(__FILE__, 5) . '/include/boot.php';
include_once dirname(__FILE__, 3) . '/include/clip_functions.php';

command_line_only();

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// Blank file
file_put_contents("titles_textonly.tagdb", "");
$prefix = "A photo of a ";

// Assuming field8 is OK - this is just a dev tool to build the .tagdb file hosted on the ResourceSpace website.
$titles = ps_array("select distinct field8 value from resource");
$c = 0;
foreach ($titles as $title) {
    $c++;
    if (!preg_match('/^[a-zA-Z -]+$/', $title)) {
        echo "SKIP NON ALPHA $title\n";
        continue;
    }

    echo str_pad($title, 50);
    $vector = get_vector(true, $prefix . " " . $title, -1);
    $vector = array_map('floatval', $vector); // ensure float values

    // Write vector to file.
    file_put_contents("titles_textonly.tagdb", urlencode($title) . " " . implode(' ', array_map('strval', $vector)) . "\n", FILE_APPEND | LOCK_EX);

    echo vector_visualise($vector) . " (" . $c . "/" . count($titles) . ")\n";
}

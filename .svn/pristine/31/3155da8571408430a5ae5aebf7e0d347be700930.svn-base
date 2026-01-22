<?php

include_once dirname(__FILE__, 5) . '/include/boot.php';
include_once dirname(__FILE__, 3) . '/include/clip_functions.php';

command_line_only();

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$done_titles = [];

// Blank file
file_put_contents("titles.tagdb", "");

// Assuming field8 is OK - this is just a dev tool to build the .tagdb file hosted on the ResourceSpace website.
$resources = ps_query("select field8 title,vector_blob from resource join resource_clip_vector on resource.ref=resource_clip_vector.resource");
foreach ($resources as $resource) {
    $title = $resource["title"];
    if (in_array($title, $done_titles)) {
        echo "SKIP DUPLICATE $title\n";
        continue;
    }
    if (!preg_match('/^[a-zA-Z ]+$/', $title)) {
        echo "SKIP NON ALPHA $title\n";
        continue;
    }
    $done_titles[] = $title;

    $vector = unpack('f512', $resource["vector_blob"]);

    // Write vector to file.
    file_put_contents("titles.tagdb", urlencode($title) . " " . implode(' ', array_map('strval', $vector)) . "\n", FILE_APPEND | LOCK_EX);

    echo str_pad($title, 40) . " " . vector_visualise($vector) . "\n";
}

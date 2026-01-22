<?php

// Command line only script to process smart collections when $smart_collections_async is enabled.

include __DIR__ . "/../../include/boot.php";
command_line_only();

if (empty($_SERVER['argv'][1])) {
    exit();
}

$smartsearch_ref = (int) $_SERVER['argv'][1];
update_smart_collection($smartsearch_ref);

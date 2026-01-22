<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions

include_once dirname(__FILE__, 4) . '/include/boot.php';
include_once dirname(__FILE__, 2) . '/include/clip_functions.php';

command_line_only();

if (!in_array("clip", $plugins)) {
    exit("Clip plugin not enabled. Exiting.\n");
    }

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// Get the current database name
global $mysql_db;

// Limit the number of resources processed in one run (optional safety limit). Default is 10000 resources.
// Supply parameter --limit to vary limit e.g. for 50000 resources: php generate_vectors.php --limit 50000
$parameters = getopt('', ['limit:']);
$limit = $parameters["limit"] ?? 10000;

$count = clip_generate_missing_vectors($limit);

if ($count == 0) {
    echo "No resources needing vector update.\n";
} else {
    echo "Done.\n";
}

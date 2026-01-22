<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions
#
# Reindex.php
#
# Reindexes all nodes. This should be unnecessary unless the node_keyword table has been corrupted.
#

include "../../include/boot.php";

if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    $indexfield = getval('field', 0, true);
} elseif (isset($argv[1]) && is_int_loose($argv[1])) {
    $indexfield = $argv[1];
    if (isset($argv[2]) && $argv[2] == "nodebug") {
        $debug_log = false; // This will hobble performance
    }
}

// Disable sql_logging
$mysql_log_transactions = false;

$sql = '';
$params = [];

set_time_limit(0);
echo "<pre>" . PHP_EOL;
$time_start = microtime(true);

// Reindex nodes, by field to minimise chance of memory issues
$allfields = get_resource_type_fields();
foreach ($allfields as $field) {
    if (isset($indexfield) && $indexfield != $field["ref"]) {
        continue;
    }

    if (PHP_SAPI == 'cli') {
        echo "Indexing nodes for field# " . $field["ref"] . " (" . $field["title"] . ")\n";
    }
    // node query
    $query = "SELECT n.ref, n.name, n.resource_type_field, f.partial_index FROM node n JOIN resource_type_field f ON n.resource_type_field=f.ref WHERE n.resource_type_field = ?";
    $params = ["i",$field["ref"]];

    $nodes = ps_query($query, $params);
    $count = count($nodes);

    echo "Found " . $count . " nodes for field #" . $field["ref"] . " (" . $field["title"] . ")\n";
    $start = 0;
    $batchsize = 100;
    $indexed = 0;

    while ($indexed < $count) {
        db_begin_transaction("reindex_field_nodes");
        for ($n = $start; $n < ($start + $batchsize) && $indexed < $count; $n++) {
            // Remove any existing keywords for this field first
            remove_all_node_keyword_mappings($nodes[$n]['ref']);
            if ($field["keywords_index"] == 1) {
                // Populate node_keyword table only if indexing enabled
                add_node_keyword_mappings($nodes[$n], $nodes[$n]["partial_index"]);
            }
            $indexed++;
        }
        db_end_transaction("reindex_field_nodes");
        if (PHP_SAPI == 'cli') {
            echo round(($indexed / $count * 100), 2) . "% completed " . $indexed . "/" . $count . " nodes for field #" . $field["ref"] . " (" . $field["title"] . ")\n";
            ob_flush();
        }
        $start += $batchsize;
    }
}

$time_end = microtime(true);
$time     = $time_end - $time_start;

echo "Reindex took $time seconds" . PHP_EOL;

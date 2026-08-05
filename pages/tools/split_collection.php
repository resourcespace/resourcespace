<?php

# Split a collection into multiple collections
# Source collection ref must be supplied. The number of collections to create is optional, the default is 2.
# From browser:
# pages/tools/split_collection.php?col=<source collection>,&num=<number of collections to create>
# e.g. pages/tools/split_collection.php?col=123&num=3
# From command line:
# php split_collection.php <source collection> <number of collections to create> 
# e.g. php split_collection.php 123 3

include __DIR__ . '/../../include/boot.php';

if (PHP_SAPI != 'cli') {
    include_once "../../include/authenticate.php";

    if (!checkperm("a")) {
        exit("Permission denied");
    }

    $collectionid = (int) getval("col", 0, true);
    $numcollections = (int) getval("num", 2, true);

    $line_end = '<br>';
} else {
    $collectionid = 0;
    $numcollections = 2;

    if (isset($argv[1]) && is_int_loose($argv[1])) {
        $collectionid = (int) $argv[1];
    }
    if (isset($argv[2]) && is_int_loose($argv[2])) {
        $numcollections = (int) $argv[2];
    }

    $line_end = PHP_EOL;
}

if ($collectionid === 0) {
    echo "Collection ID not supplied";
    die();
}

$collectionresources = get_collection_resources($collectionid);
$collectionname = ps_value("SELECT name AS value FROM collection WHERE ref = ?", array("i",$collectionid), "Collection");
$collectionuser = ps_value("SELECT user AS value FROM collection WHERE ref = ?", array("i",$collectionid), 1);

if (!is_array($collectionresources)) {
    echo "Collection " . escape($collectionid) . " contains no resources.";
    die();
}

$countresources = count($collectionresources);
$percollection = floor($countresources / $numcollections);
$newcollectionIDs = array();

echo "Splitting collection " . (int) $collectionid . " into " . (int) $numcollections . " collections roughly " . escape($percollection) . " resources in size." . $line_end;

# Create the new collections
for ($i = 0; $i < $numcollections; $i++) {
    $newcollectionIDs[] = create_collection($collectionuser, $collectionname . "_split_" . ($i + 1));
    echo "Created collection " . escape($collectionname) . "_split_" . ($i + 1) . $line_end;
}

$currentcollection = 0;

# Loop through the new collections adding one resource at a time
for ($x = 0; $x < $countresources; $x++) {
    add_resource_to_collection($collectionresources[$x], $newcollectionIDs[$currentcollection], false, '', '', true);
    $currentcollection++;

    if ($currentcollection >= $numcollections) {
        $currentcollection = 0;
    }
}

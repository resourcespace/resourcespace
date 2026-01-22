<?php

# Split a collection into multiple collections

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

$collectionid = getval("col", false);
$numcollections = getval("num", 2, true);

if (!$collectionid) {
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

echo "Splitting collection " . $collectionid . " into " . $numcollections . " collections roughly " . $percollection . " resources in size.<br>";

# Create the new collections
for ($i = 0; $i < $numcollections; $i++) {
    $newcollectionIDs[] = create_collection($collectionuser, $collectionname . "_split_" . ($i + 1));
    echo "Created collection " . $collectionname . "_split_" . ($i + 1) . "<br>";
}

$currentcollection = 0;

# Loop through the new collections adding one resource at a time
for ($x = 0; $x < $countresources; $x++) {
    add_resource_to_collection($collectionresources[$x], $newcollectionIDs[$currentcollection]);
    $currentcollection++;

    if ($currentcollection >= $numcollections) {
        $currentcollection = 0;
    }
}

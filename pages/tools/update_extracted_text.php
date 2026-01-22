<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions

# This script can be used to update all extracted text for supported file types
# Passing -c/--col <collection_id> will only process resources in that collection
# Passing -u/--update-all will run the extraction even if there is existing data, and overwrite the old value

include_once __DIR__ . "/../../include/boot.php";
include_once __DIR__ . "/../../include/image_processing.php";
command_line_only();

set_time_limit(0);

$shortopts = "c:u";
$longopts = array("col:","update-all");
$clargs = getopt($shortopts, $longopts);

$collectionid = (isset($clargs["col"]) && is_numeric($clargs["col"])) ? $clargs["col"] : ((isset($clargs["c"]) && is_numeric($clargs["c"])) ? $clargs["c"] : 0);
$updateall = isset($clargs["update-all"]) || isset($clargs["u"]);

if (!isset($extracted_text_field)) {
    echo 'No $extracted_text_field set - exiting' . PHP_EOL;
    exit();
}

$join = "";
$condition = "";
$params = [];

if ($collectionid != 0) {

    echo "Filtering to collection ref: $collectionid" . PHP_EOL;

    $join = " INNER JOIN collection_resource cr ON cr.resource=r.ref ";
    $condition = " AND cr.collection = ?";
    $params = ['i', $collectionid];
}

$resources = ps_query("SELECT r.ref, r.file_extension 
                        FROM resource r
                        $join
                        WHERE r.ref > 0
                        AND LOWER(r.file_extension) IN ('doc','docx','xlsx','odt','ods','odp','pdf','ai','html','htm','txt','zip')
                        $condition
                        ORDER BY r.ref ASC;", $params);


$edit_count = 0;

foreach ($resources as $resource) {
    $current_extracted_text = get_data_by_field($resource['ref'], $extracted_text_field);

    if (!empty($current_extracted_text) && !$updateall) {
        echo "Ref: " . $resource['ref'] . " - already has extracted text - skipping" . PHP_EOL;
    } else {
        $result = extract_text($resource['ref'], $resource['file_extension']);

        if ($result) {
            echo "Ref: " . $resource['ref'] . " - updating extracted text" . PHP_EOL;
            $edit_count++;
        } else {
            echo "Ref: " . $resource['ref'] . " - error extracting text" . PHP_EOL;
        }
    }
}

echo $edit_count . " of " . count($resources)  .  " resources processed" . PHP_EOL;

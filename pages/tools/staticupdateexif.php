<?php

# This script is useful for initial imports when you're working out metadata mappings. However, be aware that
# local ResourceSpace field edits could be overwritten by original file metadata during this process
# if embedded data is present.
# Collection or resource type can be be passed in to filter the resources that are processed.

include "../../include/boot.php";

include_once "../../include/image_processing.php";
$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    if (getval("h", "") != "") {
        echo "Usage: php staticupdateexif.php?[OPTIONS]" . PHP_EOL . PHP_EOL;
        echo "Resources will have any exif fields updated, existing data will be overwritten if embedded metadata is present." . PHP_EOL;
        echo "Optional arguments:-" . PHP_EOL;
        echo "col               ID of collection. If specified Only resouces in this collection will be updated" . PHP_EOL;
        echo "types             Comma separated list of resource types. If specified Only resouces in this resource type will be updated" . PHP_EOL;
        echo "Examples:-" . PHP_EOL;
        echo "   php staticupdateexif.php?col=456 " . PHP_EOL;
        echo "   - This will update resources in collection 456." . PHP_EOL;
        echo "   php staticupdateexif.php?types=1,2 " . PHP_EOL;
        echo "   - This will update resources of type 1 and 2." . PHP_EOL;
        exit();
    }
    header("Content-type: text/plain");
    $collectionid = getval("col", 0, true);
    $resource_types = array_filter(explode(",", getval("types", "")), "is_int_loose");
    echo "collection: $collectionid resource types: " . implode(",", $resource_types) . PHP_EOL;
} else {
    $shortopts = "c:t:";
    $longopts = array("col:","types:");
    $clargs = getopt($shortopts, $longopts);

    if (getopt("h", ["help"])) {
        echo "Usage: php staticupdateexif.php [OPTIONS]" . PHP_EOL . PHP_EOL;
        echo "Resources will have any exif fields updated, existing data will be overwritten if embedded metadata is present." . PHP_EOL;
        echo "Optional arguments:-" . PHP_EOL;
        echo "-c, --col               ID of collection. If specified Only resouces in this collection will be updated" . PHP_EOL;
        echo "-t, --types             Comma separated list of resource types. If specified Only resouces in this resource type will be updated" . PHP_EOL;
        echo "Examples:-" . PHP_EOL;
        echo "   php staticupdateexif.php --col=456 " . PHP_EOL;
        echo "   - This will update resources in collection 456." . PHP_EOL;
        echo "   php staticupdateexif.php --types=1,2 " . PHP_EOL;
        echo "   - This will update resources of type 1 and 2." . PHP_EOL;
        exit();
    }

    if (isset($clargs["col"]) && is_int_loose($clargs["col"])) {
        $collectionid = (int) $clargs["col"];
    } elseif (isset($clargs["c"]) && is_int_loose($clargs["c"])) {
        $collectionid = (int) $clargs["c"];
    } else {
        $collectionid = 0;
    }

    if (isset($clargs["types"])) {
        $resource_types = array_filter(explode(",", $clargs["types"]), "is_int_loose");
    } elseif (isset($clargs["t"])) {
        $resource_types = array_filter(explode(",", $clargs["types"]), "is_int_loose");
    } else {
        $resource_types = [];
    }
}

set_time_limit(60 * 60 * 40);

echo "Updating EXIF/IPTC...\n";

$sql = new PreparedStatementQuery("SELECT ref, file_extension FROM resource WHERE has_image > ?", ["i",RESOURCE_PREVIEWS_NONE]);

if ($collectionid > 0) {
    echo "Collection ID: " . $collectionid . PHP_EOL;
    $sql->sql .= " AND ref IN (SELECT resource FROM collection_resource WHERE collection = ?)";
    $sql->parameters = array_merge($sql->parameters, ["i",$collectionid]);
}

if (count($resource_types) > 0) {
    echo "Resource types: " . implode(",", $resource_types) . PHP_EOL;
    $sql->sql .= " AND resource_type IN (" . ps_param_insert(count($resource_types)) . ")";
    $sql->parameters = array_merge($sql->parameters, ps_param_fill($resource_types, "i"));
}

$resources = ps_query($sql->sql, $sql->parameters);

foreach ($resources as $resource) {
    $ref = $resource["ref"];
    echo $resource["ref"] . "... ";
    $GLOBALS["use_error_exception"] = true;
    try {
        extract_exif_comment($resource["ref"], $resource["file_extension"]);
    } catch (throwable $e) {
        echo "ERROR: " . $e;
        exit();
    }
    unset($GLOBALS["use_error_exception"]);
    echo "done\n";
}
echo "Process complete\n";

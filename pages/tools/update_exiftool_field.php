<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions

# This script is useful if you've added an exiftool field mapping and would like to update RS fields with the original file information
# for all your resources.

include "../../include/boot.php";
include_once "../../include/image_processing.php";

$sapi_type = php_sapi_name();

if (substr($sapi_type, 0, 3) != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    header("Content-type: text/plain");
    set_time_limit(0);
    # ex. pages/tools/update_exiftool_field.php?fieldrefs=75,3&blanks=true
    $fieldrefs = getval("fieldrefs", 0);

    if ($fieldrefs == 0) {
        echo "Please add a list of field IDs to the fieldrefs url parameter, which are the ref numbers of the fields that you would like exiftool to extract from." . PHP_EOL . PHP_EOL;
        echo "Examples:-" . PHP_EOL . PHP_EOL;
        echo "   pages/tools/update_exiftool_field.php?fieldrefs=18" . PHP_EOL;
        echo "   - This will update field 18 (usually description/caption) for all resources." . PHP_EOL;
        echo "     If metadata is already present for a resource it will be left unchanged." . PHP_EOL . PHP_EOL;
        echo "   pages/tools/update_exiftool_field.php?fieldrefs=8&col=678&blanks=true&overwrite " . PHP_EOL;
        echo "   - This will update field 8 (usually title) for resources in collection 678. Existing values will be overwritten." . PHP_EOL;
        echo "     If no embedded metadata is present the field will be cleared." . PHP_EOL . PHP_EOL;
        echo "   pages/tools/update_exiftool_field.php?fieldrefs=75,3&blanks=false&overwrite=true " . PHP_EOL;
        echo "   - This will update fields 3 and 75 for all resources. Existing values will be overwritten only if there is embedded metadata present." . PHP_EOL;
        exit();
    }

    $blanks = getval("blanks", "true") == "true"; // if new value is blank, it will replace the old value.
    $fieldrefs = explode(",", $fieldrefs);
    $collectionid = getval("col", 0, true);
    $overwrite = getval("overwrite", "") != "";  // If true and field already has value it will overwrite the existing value
} else {
    $shortopts = "f:c:b:o";
    $longopts = array("fieldrefs:", "blanks::", "col::", "overwrite");
    $clargs = getopt($shortopts, $longopts);

    if (!isset($clargs["fieldrefs"]) && !isset($clargs["f"])) {
        echo "Usage: php update_exiftool_field.php [FIELD REFS] [OPTIONS]" . PHP_EOL . PHP_EOL;
        echo "Required arguments" . PHP_EOL;
        echo "-f, --fieldrefs         A list of field IDs as the fieldrefs arguments i.e. --fieldrefs <comma separated list of numbers>" . PHP_EOL;
        echo "                        These are the ref numbers of the fields that you would like exiftool to extract from." . PHP_EOL;
        echo "Optional arguments:-" . PHP_EOL;
        echo "-c, --col               ID of collection. If specified Only resouces in this collection will be updated" . PHP_EOL;
        echo "-b, --blanks            true|false. Should existing data be wiped for resources where the file has no metadata present for the associated tag?" . PHP_EOL;
        echo "-o, --overwrite         Overwrite existing data by embedded metadata? Wil be false if not passed" . PHP_EOL . PHP_EOL;
        echo "Examples:-" . PHP_EOL;
        echo "   php update_exiftool_field.php --fieldrefs 18" . PHP_EOL;
        echo "   - This will update field 18 (usually description/caption) for all resources." . PHP_EOL;
        echo "     If metadata is already present for a resource it will be left unchanged." . PHP_EOL . PHP_EOL;
        echo "   php update_exiftool_field.php --fieldrefs 8 --col=678 --blanks=true --overwrite " . PHP_EOL;
        echo "   - This will update field 8 (usually title) for resources in collection 678. Existing values will be overwritten." . PHP_EOL;
        echo "     If no embedded metadata is present the field will be cleared." . PHP_EOL . PHP_EOL;
        echo "   php update_exiftool_field.php --fieldrefs 75,3 --blanks=false --overwrite " . PHP_EOL;
        echo "   - This will update fields 3 and 75 for all resources. Existing values will be overwritten only if there is embedded metadata present." . PHP_EOL;
        exit();
    }

    $fieldrefs = isset($clargs["fieldrefs"]) ? explode(",", $clargs["fieldrefs"]) : explode(",", $clargs["f"]);
    $collectionid = (isset($clargs["col"]) && is_numeric($clargs["col"])) ? $clargs["col"] : ((isset($clargs["c"]) && is_numeric($clargs["c"])) ? $clargs["c"] : 0);
    $blanks = ((isset($clargs["blanks"]) && strtolower($clargs["blanks"]) == "false") || isset($clargs["b"]) && strtolower($clargs["b"]) == "false") ? false : true;
    $overwrite = isset($clargs["overwrite"]) || isset($clargs["o"]);
}

$exiftool_fullpath = get_utility_path("exiftool");
if (!$exiftool_fullpath) {
    die("Could not find Exiftool.");
}

foreach ($fieldrefs as $fieldref) {
    $fieldref_info = get_resource_type_field($fieldref);

    if (!$fieldref_info) {
        die("field " . (int) $fieldref . " doesn't exist");
    }

    $title = (string) $fieldref_info["title"];
    $name = (string) $fieldref_info["name"];
    $type = (int) $fieldref_info["type"];
    $exiftool_filter = (string) $fieldref_info["exiftool_filter"];
    $exiftool_tag = (string) $fieldref_info["exiftool_field"];
    $restypes = !is_null($fieldref_info["resource_types"]) ? explode(",", (string) $fieldref_info["resource_types"]) : [];

    if ($exiftool_tag == "") {
        die("Please add an exiftool mapping to your " . escape($title) . " Field");
    }

    echo PHP_EOL . "Updating RS Field " . (int) $fieldref . " - " . escape($title) . ", with exiftool extraction of: " . escape($exiftool_tag) . PHP_EOL;

    $join = "";
    $condition = "";
    $conditionand = "";
    $params = [];

    if ($collectionid != 0) {
        $join = " INNER JOIN collection_resource ON collection_resource.resource=resource.ref ";
        $condition = "WHERE collection_resource.collection = ?";
        $conditionand = "AND collection_resource.collection = ?";
        $params = ['i', $collectionid];
    }

    if ($fieldref_info["global"] === 1) {
        $rd = ps_query("SELECT ref,file_extension FROM resource $join $condition ORDER BY ref", $params);
    } elseif (empty($restypes)) {
        echo "Field " . (int) $fieldref . " not assigned to any fields or global, skipping...\n";
        continue;
    } else {
        $rd = ps_query("SELECT ref,file_extension FROM resource $join WHERE resource_type IN(" . ps_param_insert(count($restypes)) . ") $conditionand ORDER BY ref", array_merge(ps_param_fill($restypes, "i"), $params));
    }

    $exiftool_tags = explode(",", $exiftool_tag);

    for ($n = 0; $n < count($rd); $n++) {
        $ref = $rd[$n]['ref'];
        $extension = $rd[$n]['file_extension'];

        $image = get_resource_path($ref, true, "", false, $extension);

        if (file_exists($image)) {
            echo "Checking Resource " . (int) $ref . PHP_EOL;

            if (!$overwrite) {
                $existing = get_data_by_field($ref, $fieldref);
                if (trim($existing) != "") {
                    echo "Resource " . (int) $ref . " already has data present in the field " . (int) $fieldref . ": " . escape($existing) . ", Skipping.." . PHP_EOL;
                    continue;
                }
            }

            $value = "";
            $exiftool_tag = "";

            foreach ($exiftool_tags as $current_exiftool_tag) {
                if (strpos(trim($current_exiftool_tag), " ") !== false) {
                    exit("ERROR: exiftool tags do not use spaces please check the tags used in the fields options for Field " . (int) $fieldref);
                }

                $command = $exiftool_fullpath . " -s -s -s -f -m -d \"%Y-%m-%d %H:%M:%S\" -" . escapeshellarg(trim($current_exiftool_tag)) . " " . escapeshellarg($image);

                if (PHP_SAPI == "cli") {
                    echo escape($command) . PHP_EOL;
                }

                $current_value = iptc_return_utf8(trim(run_command($command)));

                if ($current_value != "-") {
                    # exiftool returned hyphen for unset tag.
                    $value = $current_value;
                    $exiftool_tag = $current_exiftool_tag;
                }

                $plugin = "../../plugins/exiftool_filter_" . safe_file_name($name) . ".php";

                if ($exiftool_filter != "") {
                    eval(eval_check_signed($exiftool_filter));
                }
                if (file_exists($plugin)) {
                    include $plugin;
                }
            }

            if ($blanks) {
                if (trim($value) != "") {
                    if ($type == FIELD_TYPE_DATE) {
                        $invalid_date = check_date_format($value);

                        if (!empty($invalid_date)) {
                            $invalid_date = str_replace("%field%", $name, $invalid_date);
                            $invalid_date = str_replace("%row% ", "", $invalid_date);

                            echo "-Exiftool " . escape($invalid_date) . PHP_EOL . PHP_EOL;
                            continue;
                        }
                    }

                    update_field($ref, $fieldref, $value);
                    echo "-Exiftool found \"" . escape($value) . "\" embedded in the -" . escape($exiftool_tag) . " tag and applied it to Resource " . (int) $ref . " Field " . (int) $fieldref . PHP_EOL . PHP_EOL;
                } else {
                    update_field($ref, $fieldref, $value);
                    echo "-Exiftool found no value embedded in the " . escape(implode(", ", $exiftool_tags)) . " tag/s and applied \"\" to Resource " . (int) $ref . " Field " . (int) $fieldref . PHP_EOL;
                }
            } else {
                if (trim($value) != "") {
                    if ($type == FIELD_TYPE_DATE) {
                        $invalid_date = check_date_format($value);

                        if (!empty($invalid_date)) {
                            $invalid_date = str_replace("%field%", $name, $invalid_date);
                            $invalid_date = str_replace("%row% ", "", $invalid_date);

                            echo "-Exiftool " . escape($invalid_date) . PHP_EOL . PHP_EOL;
                            continue;
                        }
                    }

                    update_field($ref, $fieldref, $value);
                    echo "-Exiftool found \"" . escape($value) . "\" embedded in the -" . escape($exiftool_tag) . " tag and applied it to Resource " . (int) $ref . " Field " . (int) $fieldref . PHP_EOL . PHP_EOL;
                } else {
                    echo "-Exiftool found no value embedded in the " . escape(implode(", ", $exiftool_tags)) . " tag/s and has made no changes for Resource " . (int) $ref . PHP_EOL;
                }
            }
        }
        echo PHP_EOL;
    }
}

echo "...done." . PHP_EOL;

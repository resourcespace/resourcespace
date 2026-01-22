<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions

# This is based on pages/tools/update_previews.php but for use on the server backend to avoid browser timeouts etc.
# previewbased is an option that can help preserve alternative previews,
# Recreating previews would normally use the original file and overwrite alternative previews that have been uploaded,
# but with previewbased=true, it will try to find a suitable large preview image to generate the smaller versions from.
# If you want to recreate preview for a single resource, you can pass ref=[ref]&only=true
# also includes optional -videoupdate to cater for systems moving from old flv videos to HTML5 compatible video

include_once __DIR__ . "/../include/boot.php";

include_once __DIR__ . "/../include/image_processing.php";

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
        exit("Command line execution only.");
}
if (isset($argv[1]) && strtolower($argv[1]) == "collection" && isset($argv[2]) && is_numeric($argv[2])) {
    $collectionid = $argv[2];
} elseif (isset($argv[1]) && strtolower($argv[1]) == "resource" && isset($argv[2]) && is_numeric($argv[2])) {
    $ref = $argv[2];
    if (isset($argv[3]) && is_numeric($argv[3])) {
        $max = $argv[3];
    }
} else {
    echo "recreate_previews.php - update previews for all/selected resources\n\n";
    echo "- extra options to use existing uploaded previews or to force recreation of video previews e.g. when changing to mp4 previews\n";
    echo "USAGE:\n";
    echo "php recreate_previews.php [collection|resource] [id] [maxref] [sizes] [comma separated size ids] [types] [comma separated type ids] [-previewbased] [-videoupdate]\n\n";
    echo "examples\n";
    echo "php recreate_previews.php collection 247\n";
    echo "- this will update previews for all resources in collection #247\n\n";
    echo "php recreate_previews.php collection 380 -previewbased\n";
    echo "- this will update previews for all resources in collection #380, utilising any existing uploaded previews\n\n";
    echo "php recreate_previews.php resource 19564\n";
    echo "- this will update previews for all resources starting with resource ID #19564\n\n";
    echo "php recreate_previews.php resource 19564 19800\n";
    echo "- this will update previews for resources starting with resource ID #19564 and ending with resource 19800\n\n";
    echo "php recreate_previews.php resource 1 -videoupdate\n";
    echo "- this will update previews for all video resources that do not have the required '\$ffmpeg_preview_extension' extension\n\n";
    echo "php recreate_previews.php collection 247 sizes scr,col\n";
    echo "- this will update only the col and scr preview sizes for all resources in collection #247\n\n";
    echo "php recreate_previews.php resource 110 types 1,2\n";
    echo "- this will start at resource 110 and recreate previews for resource types 1 and 2\n\n";
    echo "php recreate_previews.php collection 247 -delete\n";
    echo "- this will remove all existing previews before recreating all preview sizes for all resources in collection #247\n";
    echo "- the -delete option cannot be used with options -videoupdate, -previewbased, sizes, or types\n\n";
    exit();
}

if (in_array("sizes", $argv)) {
    $sizes  = explode(",", $argv[array_search("sizes", $argv) + 1]);
} else {
    $sizes = array();
}

if (in_array("types", $argv)) {
    $resource_types = explode(",", $argv[array_search("types", $argv) + 1]);
} else {
    $resource_types = array();
}

$previewbased = in_array("-previewbased", $argv);
$videoupdate = in_array("-videoupdate", $argv);
$delete_existing = in_array("-delete", $argv) && !$previewbased && !$videoupdate && count($sizes) == 0;

function update_preview($ref, $previewbased, $sizes, $delete_existing)
{
    $resourceinfo = ps_query("select file_path, file_extension from resource where ref = ?", array("i", (int)$ref));
    if (count($resourceinfo) > 0 && !hook("replaceupdatepreview", '', array($ref, $resourceinfo[0]))) {
        if (!empty($resourceinfo[0]['file_path'])) {
            $ingested = false;
        } else {
            $ingested = true;
        }
        if ($delete_existing) {
            delete_previews($ref);
        }
        create_previews(
            $ref, 
            false, 
            ($previewbased || in_array($resourceinfo[0]["file_extension"], NON_PREVIEW_EXTENSIONS) ? "jpg" : $resourceinfo[0]["file_extension"]), 
            false, 
            $previewbased || in_array($resourceinfo[0]["file_extension"], NON_PREVIEW_EXTENSIONS), 
            -1, 
            true, 
            $ingested, 
            true, 
            $sizes
        );
        hook("afterupdatepreview", "", array($ref));
        update_disk_usage($ref);
        return true;
    }
    return false;
}

if (!isset($collectionid)) {
    $conditions = array();
    $conditions_params = array("i", (int)$ref);
    if (isset($max)) {
        $conditions[] = "ref <= ?";
        $conditions_params = array_merge($conditions_params, array("i", $max));
    }
    if ($videoupdate) {
        $conditions[] = "file_extension in (" . ps_param_insert(count($ffmpeg_supported_extensions)) . ")";
        $conditions_params = array_merge($conditions_params, ps_param_fill($ffmpeg_supported_extensions, "s"));
    }
    if (isset($resource_deletion_state)) {
        $conditions[] = "archive <> ?";
        $conditions_params = array_merge($conditions_params, array("i", $resource_deletion_state));
    }
    if (!empty($resource_types)) {
        $conditions[] = "resource_type in (" . ps_param_insert(count($resource_types)) . ")";
        $conditions_params = array_merge($conditions_params, ps_param_fill($resource_types, "i"));
    }
    $resources = ps_array("SELECT ref value FROM resource WHERE ref >= ?" . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") . " ORDER BY ref asc", $conditions_params, 0);
} else {
    $resources = get_collection_resources($collectionid);
}

if (is_array($resources) && count($resources) > 0) {
    hook('beforescriptaction');
    foreach ($resources as $resource) {
        if ($videoupdate) {
            $checkflvpreview = get_resource_path($resource, true, 'pre', false, 'flv', true, 1, false, '');
            $correctvideo_preview = get_resource_path($resource, true, 'pre', false, $ffmpeg_preview_extension, true, 1, false);
            echo "Checking for video preview of resource #" . $resource .  ".....";
            if (file_exists($correctvideo_preview)) {
                echo "...already exists, skipping\n";
                continue;
            }
        }

        echo "Recreating previews for resource #" . $resource . "...";
        ob_flush();
        if (update_preview($resource, $previewbased, $sizes, $delete_existing)) {
            echo "....completed\n";
        } else {
            echo "FAILED - skipping\n";
        }
        ob_flush();
    }
} else {
    echo "No resources found\n";
}
echo "\nFinished\n";

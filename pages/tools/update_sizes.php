<?php
#
#
# Quick 'n' dirty script to update all preview images.
# It's done one at a time via the browser so progress can be monitored.
#
#
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

include_once "../../include/image_processing.php";

$max = ps_value("select max(ref) value from resource", array(), 0);
$ref = getval("ref", 1);

$resourceinfo = ps_query("select ref,file_extension from resource where ref= ?", ['i', $ref]);
if (count($resourceinfo) > 0) {
    $extension = $resourceinfo[0]['file_extension'];
    $file = get_resource_path($ref, true, "", false, $extension);
    $filesize = @filesize_unlimited($file);
    if (isset($imagemagick_path)) {
        # Check ImageMagick identify utility.
        $identify_fullpath = get_utility_path("im-identify");
        if (!$identify_fullpath) {
            exit("Could not find ImageMagick 'identify' utility.");
        }

        $prefix = '';
        # Camera RAW images need prefix
        if (preg_match('/^(dng|nef|x3f|cr2|crw|mrw|orf|raf|dcr)$/i', $extension, $rawext)) {
            $prefix = $rawext[0] . ':';
        }

        # Get image's dimensions.
        $identcommand = $identify_fullpath . ' -format %wx%h ' . escapeshellarg($prefix . $file) . '[0]';
        $identoutput = run_command($identcommand);
        preg_match('/^([0-9]+)x([0-9]+)$/ims', $identoutput, $smatches);
        @list(,$sw,$sh) = $smatches;
        if (($sw != '') && ($sh != '')) {
            $size_db = ps_query("select 'true' from resource_dimensions where resource = ?", ['i', $ref]);
            if (count($size_db)) {
                ps_query("update resource_dimensions set width= ?, height= ?, file_size= ? where resource= ?", ['i', $sw, 'i', $sh, 'i', $filesize, 'i', $ref]);
            } else {
                ps_query("insert into resource_dimensions (resource, width, height, file_size) values(?, ?, ?, ?)", ['i', $ref, 'i', $sw, 'i', $sh, 'i', $filesize]);
            }
        }
    } else {
        # fetch source image size, if we fail, exit this function (file not an image, or file not a valid jpg/png/gif).
        if ((@list($sw, $sh) = @getimagesize($file)) !== false) {
            $size_db = ps_query("select 'true' from resource_dimensions where resource = ?", ['i', $ref]);
            if (count($size_db)) {
                ps_query("update resource_dimensions set width= ?, height= ?, file_size= ? where resource= ?", ['i', $sw, 'i', $sh, 'i', $filesize, 'i', $ref]);
            } else {
                ps_query("insert into resource_dimensions (resource, width, height, file_size) values(?, ?, ?, ?)", ['i', $ref, 'i', $sw, 'i', $sh, 'i', $file_size]);
            }
        }
    }
    ?>
    <img alt="" src="<?php echo get_resource_path($ref, false, "pre", false)?>">
    <?php
} else {
    echo "Skipping " . escape($ref);
}

if ($ref < $max && getval("only", "") == "") {
    ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $baseurl?>/pages/tools/update_sizes.php?ref=<?php echo $ref + 1?>"/>
    <?php
} else {
    ?>
    Done.   
    <?php
}

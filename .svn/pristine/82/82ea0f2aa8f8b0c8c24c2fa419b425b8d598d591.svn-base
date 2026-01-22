<?php
#
#
# Quick 'n' dirty script to update all images paths with new scrambled filenames.
# It's done one at a time via the browser so progress can be monitored.
#
#
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

include "../../include/image_processing.php";

$max = ps_value("select max(ref) value from resource", [], 0);
$ref = getval("ref", 1);
$ps = ps_query("select * from preview_size"); // select * fine here as no parameters
$resourceinfo = ps_query("select ref,file_extension from resource where ref= ?", ['i', $ref]);
if (count($resourceinfo) > 0) {
    $extension = $resourceinfo[0]['file_extension'];
    get_resource_path($ref, true, "", false, $extension);
    for ($n = 0; $n < count($ps); $n++) {
        $id = $ps[$n]["id"];
        get_resource_path($ref, true, $id, false);
    }
    ?>
    <img alt="" src="<?php echo get_resource_path($ref, false, "pre", false)?>">
    <?php
} else {
    echo "Skipping " . escape($ref);
}

if ($ref < $max && getval("only", "") == "") {
    ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $baseurl?>/pages/tools/update_paths.php?ref=<?php echo $ref + 1?>"/>
    <?php
} else {
    ?>
    Done.   
    <?php
}

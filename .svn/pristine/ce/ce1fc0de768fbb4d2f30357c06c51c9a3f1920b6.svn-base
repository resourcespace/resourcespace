<?php
#
#
# Quick 'n' dirty script to update all alternative file preview images.
# It's done one at a time via the browser so progress can be monitored.
#
#
include "../../include/boot.php";

if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
}

include_once "../../include/image_processing.php";

$max = ps_value("select max(ref) value from resource_alt_files", array(), 0);

if (PHP_SAPI != 'cli') {
    $ref = getval("ref", 1);
    $previewbased = getval("previewbased", "") != "";
} else {
    $help_text = "NAME
        update_previews_alternative.php.php - update all alternative file preview images.
    
    SYNOPSIS
        php /path/to/pages/tools/update_previews_alternative.php [OPTIONS...]
    
    DESCRIPTION
        Used to update all alternative file preview images, can be recreated fom uploaded previews
    
    OPTIONS SUMMARY
    
        --help          Display this help text and exit
        --start         Ref of alternative resource to start from
        --end         Ref of alternative resource to end
        --previewbased  Use uploaded previews?
    
    EXAMPLES
        php update_previews_alternative.php --ref=546  --previewbased=true
        php update_previews_alternative.php -r 23452 -p 
    ";

    // CLI options check
    $cli_long_options  = array(
        'start:',
        'end:',
        'previewbased',
    );
    $previewbased = false;
    $start = 1;
    $end   = 0;
    foreach (getopt('', $cli_long_options) as $option_name => $option_value) {
        if (in_array($option_name, array('h', 'help'))) {
            echo '' . PHP_EOL;
            exit(1);
        }
        if ($option_name == 'start') {
            $start =  (int)$option_value;
            echo "Starting with alternative ref #" . $start . PHP_EOL;
        }
        if ($option_name == 'end') {
            $end =  (int)$option_value;
            echo "Ending with alternative ref #" . $end . PHP_EOL;
        }
        if ($option_name == 'previewbased') {
            $previewbased =  true;
            echo "Setting previewbased to true" . PHP_EOL;
        }
    }
}

if (PHP_SAPI == 'cli') {
    $condition = "a.ref >= ?";
    $params = ["i",$start];
    if ($end > 0) {
        $condition .= " AND a.ref <= ?";
        $params[] = "i";
        $params[] = $end;
    }
    $resources = ps_query("SELECT a.ref, a.resource, a.file_extension FROM resource_alt_files a JOIN resource r ON a.resource = r.ref WHERE " . $condition . " AND length(a.file_extension) > 0", $params);

    foreach ($resources as $resource) {
        $success = create_previews($resource["resource"], false, ($previewbased ? "jpg" : $resource["file_extension"]), false, $previewbased, $resource["ref"]);
        $message = $success ? "Preview created successfully" : "Preview creation failed";
        echo "Alternative ref #" . $resource["ref"] . ", resource #" . $resource["resource"] . " - " . $message . PHP_EOL;
        ob_flush();
        flush();
    }
} else {
    $resourceinfo = ps_query("SELECT a.ref, a.resource, a.file_extension FROM resource_alt_files a JOIN resource r ON a.resource = r.ref WHERE a.ref = ? AND length(a.file_extension) > 0", ["i", $ref]);
    if (count($resourceinfo) > 0) {
        create_previews($resourceinfo[0]["resource"], false, ($previewbased ? "jpg" : $resourceinfo[0]["file_extension"]), false, $previewbased, $ref);
        ?>
        <img alt="" src="<?php echo get_resource_path($resourceinfo[0]["resource"], false, "pre", false, "jpg", -1, 1, false, "", $ref)?>">
        <?php
    } else {
        echo "Skipping " . escape($ref);
    }

    if ($ref < $max && getval("only", "") == "") {
        ?>
        <meta http-equiv="refresh" content="1;url=<?php echo $baseurl?>/pages/tools/update_previews_alternative.php?ref=<?php echo $ref + 1?>&previewbased=<?php echo $previewbased ? "true" : "" ?>"/>
        <?php
    } else {
        ?>
        Done.
        <?php
    }
}
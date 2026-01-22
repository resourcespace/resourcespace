<?php

#
# Script to update resource_dimensions table for all resources.

include "../../include/boot.php";
if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";

    if (!checkperm("a")) {
        exit("Permission denied");
    } elseif (!$exiftool_resolution_calc) {
        die("Please turn on the exiftool resolution calculator in your config.php file.");
    }

    $eol = "<br/>";
    $start  = getval("start", 0, true);
    $end    = getval("end", 0, true);
    $col    = getval("col", 0, true);
} else {
    if (!$exiftool_resolution_calc) {
        die("Please turn on the exiftool resolution calculator in your config.php file.");
    }
    $help_text = "NAME
        update_resource_dimensions.php - update all image dimensions for resources.
    
    SYNOPSIS
        php /path/to/pages/tools/update_resource_dimensions.php [OPTIONS...]
    
    DESCRIPTION
        Used to update all values in the resource_dimensions table for specified resources.
    
    OPTIONS SUMMARY
    
        -h, --help          Display this help text and exit
        -s, --start         Ref of resource to start from
        -e, --end           Ref of resource to end
        -c, --collection    Ref of Collection to run on
    
    EXAMPLES
        php update_resource_dimensions.php --start=123
        php update_resource_dimensions.php -s123 -e456
        php update_resource_dimensions.php -c111
    ";

    $eol = "\n";
    // CLI options check
    $cli_short_options = 'hs:e:c:';
    $cli_long_options  = ['help', 'start:', 'end:', 'collection'];

    $previewbased = false;
    $start = 0;
    $end   = 0;
    $col   = 0;
    foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
        if (in_array($option_name, array('h', 'help'))) {
            echo $help_text . $eol;
            exit(0);
        }
        if ($option_name == 'start' || $option_name == 's') {
            $start =  (int) $option_value;
            echo "Starting with ref #" . $start . PHP_EOL;
        }
        if ($option_name == 'end' || $option_name == 'e') {
            $end =  (int) $option_value;
            echo "Ending with ref #" . $end . PHP_EOL;
        }
        if ($option_name == 'collection' || $option_name == 'c') {
            $col = (int) $option_value;
            echo "Running on collection ref #" . $col . PHP_EOL;
        }
    }
}

set_time_limit(0);


$exiftool_fullpath = get_utility_path("exiftool");
if (!$exiftool_fullpath) {
    die("Could not find exiftool. Aborting...");
} else {
    $filter = new PreparedStatementQuery(" FROM resource WHERE ref>=?", ["i",$start]);
    if ($end > 0) {
        $filter->sql .= " AND ref<=?";
        $filter->parameters = array_merge($filter->parameters, ["i", $end]);
    }
    if ($col > 0) {
        if (get_collection($col) === false) {
            die("Collection $col not found. Aborting...");
        }
        $filter->sql .= " AND ref IN (SELECT resource FROM collection_resource WHERE collection = ?)";
        $filter->parameters = array_merge($filter->parameters, ["i", $col]);
    }
    $resources_count = ps_value("SELECT COUNT(*) value" . $filter->sql, $filter->parameters, 0);

    # $view_title_field is not user provided
    $resources_sql = new PreparedStatementQuery(
        "SELECT ref,field$view_title_field,file_extension {$filter->sql} ORDER BY ref LIMIT ?,5000",
        $filter->parameters
    );

    ob_start();
    $counter = 0;
    while ($counter <= $resources_count) {
        $resources = ps_query($resources_sql->sql, array_merge($resources_sql->parameters, ["i",$counter]));

        foreach ($resources as $resource) {
            $resource_path = get_resource_path($resource['ref'], true, "", false, $resource['file_extension']);
            if (file_exists($resource_path) && !in_array($resource['file_extension'], $exiftool_no_process)) {
                $resource = get_resource_data($resource['ref']);
                exiftool_resolution_calc($resource_path, $resource['ref'], true);
                $output =  "Ref: {$resource['ref']} - {$resource['field' . $view_title_field]}";
                $output .= " - updating resource_dimensions record.$eol";
                echo $output;
            }
        }
        ob_flush();
        $counter += 5000;
    }
}
ob_end_flush();
echo "Finished updating resource_dimensions.$eol";

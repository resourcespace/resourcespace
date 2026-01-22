<?php

#
#
# Quick 'n' dirty script to update all filesizes (if affected by du inconsistency)
# This puts values gained from filesize_unlimited into resource_dimensions and resource_alt_files
# also updates disk usage in case cron job isn't running.

include "../../include/boot.php";

if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
}

set_time_limit(0);

# get all resources in the DB
$resources = ps_query("select ref,field" . (int)$view_title_field . ",file_extension from resource where ref>0 order by ref DESC");

//loop:
foreach ($resources as $resource) {
    $resource_path = get_resource_path($resource['ref'], true, "", false, $resource['file_extension']);
    if (file_exists($resource_path)) {
        $filesize = filesize_unlimited($resource_path);

        ps_query("update resource_dimensions set file_size= ? where resource= ?", ['i', $filesize, 'i', $resource['ref']]);

        echo "Ref: " . $resource['ref'] . " - " . $resource['field' . $view_title_field] . " - updating resource_dimensions file_size column - ";
        if (PHP_SAPI == 'cli') {
            echo str_replace('&nbsp;', ' ', formatfilesize($filesize)) . PHP_EOL;
        } else {
            echo formatfilesize($filesize) . "<br />";
        }
    }

    $alt_files = ps_query("select file_extension,file_name,ref from resource_alt_files where resource= ?", ['i', $resource['ref']]);
    if (count($alt_files) > 0) {
        foreach ($alt_files as $alt) {
            $alt_path = get_resource_path($resource['ref'], true, "", false, $alt['file_extension'], -1, 1, false, "", $alt['ref']);
            if (file_exists($alt_path)) {
                // allow to re-run script without re-copying files
                $filesize = filesize_unlimited($alt_path);
                ps_query("update resource_alt_files set file_size= ? where resource= ? and ref= ?", ['i', $filesize, 'i', $resource['ref'], 'i', $alt['ref']]);
                echo "ALT - " . $alt['file_name'] . " - updating alt file size - ";
                if (PHP_SAPI == 'cli') {
                    echo str_replace('&nbsp;', ' ', formatfilesize($filesize)) . PHP_EOL;
                } else {
                    echo formatfilesize($filesize) . "<br />";
                }
            }
        }
    }

    update_disk_usage($resource['ref']);
    echo "updating disk usage";
    if (PHP_SAPI == 'cli') {
        echo PHP_EOL . PHP_EOL;
    } else {
        echo "<br />";
        echo "<br />";
    }

    flush();
    ob_flush();
}

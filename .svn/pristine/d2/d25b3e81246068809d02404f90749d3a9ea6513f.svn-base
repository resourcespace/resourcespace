<?php

/**
* Utility script to import files from a remote ResourceSpace system that is only accessible over HTTP/HTTPS
*
* Either a collection id or a minimum resource ID (and optionally a maximum) can be passed to test the behaviour for a set of resources
*
* Add $remotebaseurl in include/config.php as below to point to the old server
*
* e.g.
*
* $remotebaseurl = 'https://old.resourcespaceurl.com';
*
* Add $retrievefilestestmode = true; to run script in testing mode to ensure script working as expected
* $retrievefilestestmode=|true;

* Optionally set the following to replace strings in calculated URLs
*
* $findurlpart = "filestore/acmecorp";
* $replaceurlpart = "filestore";
*
*  Also set the following to true if the remote server has $hide_real_filepath = true; set (i.e. access to resoure files require authentication)
*
* $remote_hidden_paths = true;
*
*  If this is set then the auto_login plugin can be used to enable temporary unauthenticated access from the receiving server's public IP
*  **** CAUTION SHOULD BE USED IF SERVER IS ON AN INTERNAL NETWORK ****
*/

include __DIR__ . '/../../include/boot.php';
command_line_only();
set_time_limit(0);
$use_error_exception = true;

$orderby = "ASC";

if (isset($argv[1]) && strtolower($argv[1]) == "collection" && isset($argv[2]) && is_numeric($argv[2])) {
    $collectionid = $argv[2];
} elseif (isset($argv[1]) && strtolower($argv[1]) == "resource" && isset($argv[2]) && is_numeric($argv[2])) {
    $min = $argv[2];
    if (isset($argv[3]) && is_numeric($argv[3])) {
        if ($argv[3] < $min) {
            $min = $argv[3];
            $max = $argv[2];
            $orderby = "DESC";
        } else {
            $max = $argv[3];
        }
    }
} else {
    echo "retrieve_files.php - fetch resource files for all/selected resources\n\n";
    echo "- intended for use after database import when resource files are located on a server accessible ResourceSpace installation and the scramble key is known\n";
    echo "USAGE:\n";
    echo "php retrieve_files.php [collection|resource] [id] [maxref] \n\n";
    echo "examples\n";
    echo "php retrieve_files.php collection 4563\n";
    echo "- this will retrieve files for all resources in collection #4563\n\n";
    echo "php retrieve_files.php resource 19564\n";
    echo "- this will retrieve resource files for all resources starting with resource ID #19564\n\n";
    echo "php retrieve_files.php resource 19564 19800\n";
    echo "- this will retrieve resource files for resources starting with resource ID #19564 and ending with resource 19800\n\n";
    exit();
}

$filtersql = "";
$joinsql = "";
$params = [];

if (!isset($collectionid)) {
    $filtersql .= " r.ref >= ?";
    $params[] = 'i';
    $params[] = $min;
    if (isset($max)) {
        $filtersql .= " AND r.ref <= ?";
        $params[] = 'i';
        $params[] = $max;
    }
} else {
    $joinsql .= "RIGHT JOIN collection_resource cr ON cr.resource=r.ref";
    $filtersql .= "cr.collection= ?";
    $params[] = 'i';
    $params[] = $collectionid;
}

$resources = ps_query("SELECT r.ref, r.file_path, r.file_extension  FROM resource r {$joinsql} WHERE {$filtersql} ORDER BY r.ref $orderby", $params);

$errors = array();
$missingfiles = array();
$copied = array();
$sizearray = ps_array("select id value from preview_size", [], false);
$sizearray[] = ""; // Add jpg version of original if present

$hide_real_filepath = isset($remote_hidden_paths) && $remote_hidden_paths;

foreach ($resources as $resource) {
    $resfiles = array();
    $n = 0;

    // Primary resource file
    $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, '', true, $resource["file_extension"]);
    $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, '', false, $resource["file_extension"]);
    $n++;

    // Previews
    foreach ($sizearray as $size) {
        $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, $size, false, "jpg");
        $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, $size, false, "jpg");
        $n++;
    }

    // Video previews
    $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, $size, false, $ffmpeg_preview_extension);
    $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, $size, false, $ffmpeg_preview_extension);
    $n++;

    // Alternative files
    $altfiles = get_alternative_files($resource["ref"]);
    foreach ($altfiles as $altfile) {
        // Primary alternative file
        $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, '', false, $altfile["file_extension"], true, 1, false, '', $altfile["ref"]);
        $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, '', false, $altfile["file_extension"], true, 1, false, '', $altfile["ref"]);
        $n++;

        $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, '', false, $altfile["file_extension"], true, 1, true, '', $altfile["ref"]);
        $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, '', false, $altfile["file_extension"], true, 1, true, '', $altfile["ref"]);
        $n++;

        $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, '', false, 'icc', true, 1, false, '', $altfile["ref"]);
        $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, '', false, 'icc', true, 1, false, '', $altfile["ref"]);
        $n++;

        $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, '', false, 'icc', true, 1, true, '', $altfile["ref"]);
        $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, '', false, 'icc', true, 1, true, '', $altfile["ref"]);
        $n++;

        foreach ($sizearray as $size) {
            $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, $size, false, "jpg", true, 1, false, '', $altfile["ref"]);
            $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, $size, false, "jpg", true, 1, false, '', $altfile["ref"]);
            $n++;

            $resfiles[$n]["local"]  = get_resource_path($resource["ref"], true, $size, false, "jpg", true, 1, true, '', $altfile["ref"]);
            $resfiles[$n]["url"]    = get_resource_path($resource["ref"], false, $size, false, "jpg", true, 1, true, '', $altfile["ref"]);
            $n++;
        }
    }

    // Now check if files exist
    $i = 0;
    for ($i = 0; $i < $n; $i++) {
        $localfile = $resfiles[$i]["local"];
        echo "Checking for presence of file - " . $localfile . "\n";
        if (!file_exists($localfile)) {
            $local_url = $resfiles[$i]["url"];
            $remote_url = str_replace($baseurl, $remotebaseurl, $local_url);

            if (isset($findurlpart) && isset($replaceurlpart)) {
                $remote_url = str_replace($findurlpart, $replaceurlpart, $remote_url);
            }

            echo " - File missing - checking URL:-\n" . $remote_url . "\n";
            $file_headers = @get_headers($remote_url);
            if (strpos($file_headers[0], "200")  !== false) {
                echo " - Found remote file at " . $remote_url . ", ingesting\n";
                if (!$retrievefilestestmode) {
                    echo str_pad(" - Copying file to " . $localfile . "\n", 30);
                    $success = @copy($remote_url, $localfile);
                    if ($success) {
                        echo str_pad(" - Copied ok\n", 30);
                    } else {
                        $errors[] = $resource["ref"] . "Failed to copy from " . $remote_url . "\n";
                    }
                }
                $copied[] = $localfile;
            } elseif ($i == 0) {
                // Main file is missing - record this
                if ($file_headers[0] == 'HTTP/1.0 404 Not Found') {
                    echo " - The remote file " . $resource["ref"]  . " does not exist\n";
                    $missingfiles[] = $localfile;
                } elseif ($file_headers[0] == 'HTTP/1.0 302 Found' && $file_headers[7] == 'HTTP/1.0 404 Not Found') {
                    echo " - The file  " . $resource["ref"]  . " does not exist, and I got redirected to a custom 404 page..\n";
                    $missingfiles[] = $localfile;
                } else {
                    echo " - The remote file " . $resource["ref"]  . " could not be accessed\n";
                    $missingfiles[] = $localfile;
                }
            }
        }
    }
    ob_flush();
    flush();
}

echo "\nCopied files: -\n";
echo implode("\n", $copied) . "\n";

echo "\nMissing files: -\n";
echo implode("\n", $missingfiles) . "\n\n";

echo "\nERRORS: -\n";
echo implode("\n", $errors) . "\n\n";

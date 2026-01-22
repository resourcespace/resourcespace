<?php

include __DIR__ . "/../../include/boot.php";
command_line_only();

// This script renumbers resources using the provided offset to shift up/down. Files are moved accordingly.
if (!isset($argv[1])) {
    exit("Usage: php renumber_resources.php [offset +/-]\n");
}
$offset = $argv[1];

function migrate_files_renumber($ref, $newref, $alternative, $extension, $sizes)
{
    global $scramble_key, $scramble_key_old, $migratedfiles, $filestore_evenspread, $syncdir;
    echo "Checking Resource ID: " . $ref . ", alternative: " . $alternative . PHP_EOL;
    $resource_data = get_resource_data($ref);
    $pagecount = get_page_count($resource_data, $alternative);
    for ($page = 1; $page <= $pagecount; $page++) {
        for ($m = 0; $m < count($sizes); $m++) {
            // Get the new path for each file
            $path = get_resource_path($ref, true, $sizes[$m]["id"], false, $sizes[$m]["extension"], true, $page, false, '', $alternative);
            $newpath = get_resource_path($newref, true, $sizes[$m]["id"], true, $sizes[$m]["extension"], true, $page, false, '', $alternative);

            echo " - Size: " . $sizes[$m]["id"] . ", extension: " . $sizes[$m]["extension"] . " Snew path: " . $newpath . PHP_EOL;
            echo " - Checking old path: " . $path . PHP_EOL;
            if (file_exists($path) && !($sizes[$m]["id"] == "" && ((trim($syncdir) != "") && strpos($path, $syncdir) !== false))) {
                echo " - Found file at old path : " . $path . PHP_EOL;
                if (!file_exists($newpath)) {
                    echo " - Moving resource file for resource #" . $ref  . " - old path= " . $path  . ", new path=" . $newpath . PHP_EOL;
                    if (!file_exists(dirname($newpath))) {
                        mkdir(dirname($newpath), 0777, true);
                    }
                    rename($path, $newpath);
                    $migratedfiles++;
                } else {
                    echo " - Resource file for resource #" . $ref  . " - already exists at new path= " . $newpath  . PHP_EOL;
                }
            }
        }
    }

    // Clear old directory if empty
    $delfolder = dirname($path);
    $newfolder = dirname($newpath);
    if (file_exists($delfolder) && $delfolder != $newfolder && count(scandir($delfolder)) == 2 && is_writable($delfolder)) {
        echo "Deleting folder $delfolder \n";
        rmdir($delfolder);
    }
}

$resources = ps_query("SELECT ref,file_extension FROM resource WHERE ref>0 ORDER BY ref DESC");
$migratedfiles = 0;
$totalresources = count($resources);

for ($n = 0; $n < $totalresources; $n++) {
    $ref = $resources[$n]["ref"];
    $newref = $ref + $offset;
    $extension = $resources[$n]["file_extension"];
    if ($extension == "") {
        $extension = "jpg";
    }

    $sizes = get_image_sizes($ref, true, $extension, false);

    // Add in original resource files, jpg preview, ffmpeg previews and other non-size files
    $sizes[] = array("id" => "", "extension" => $extension);
    $sizes[] = array("id" => "pre", "extension" => $ffmpeg_preview_extension);
    $sizes[] = array("id" => "", "extension" => "jpg");
    $sizes[] = array("id" => "", "extension" => "xml");
    $sizes[] = array("id" => "", "extension" => "icc");

    migrate_files_renumber($ref, $newref, -1, $extension, $sizes);

    // Migrate the alternatives
    $alternatives = get_alternative_files($ref);
    foreach ($alternatives as $alternative) {
        $sizes = get_image_sizes($ref, true, $alternative["file_extension"], false);
        $sizes[] = array("id" => "", "extension" => $alternative["file_extension"]);
        migrate_files_renumber($ref, $newref, $alternative["ref"], $alternative["file_extension"], $sizes);
    }

    # The following tables are not subjected to the renumbering process and were never intended to because this is a one-off script:-
    # collection_log, resource
    # external_access_keys, resource
    # grant_edit, resource
    # propose_changes_data, resource
    # slideshow, resource_ref

    $parameters = array("i",$newref, "i",$ref);
    # Update the ref on following tables
    ps_query("UPDATE resource SET ref=? WHERE ref=?", $parameters);
    ps_query("UPDATE annotation SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE collection_resource SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE comment SET resource_ref=? WHERE resource_ref=?", $parameters);
    ps_query("UPDATE resource_license SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_alt_files SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_consent SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_custom_access SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_dimensions SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_keyword SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_log SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_node SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_related SET resource=? WHERE resource=?", $parameters);
    ps_query("UPDATE resource_related SET related=? WHERE related=?", $parameters);
}

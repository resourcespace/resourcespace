<?php

/*
Job handler to fetch files from a local path  for batch replacement of resources

Requires the following job data:-
$job_data['import_path'] - Folder to scan for files to import
*/

global $lang, $baseurl_short, $offline_job_delete_completed, $fstemplate_alt_threshold;
global $notify_on_resource_change_days, $replace_batch_existing;

$local_path     = $job_data['import_path'];
$minref         = $job_data['batch_replace_min'];
$maxref         = $job_data['batch_replace_max'];
$collectionid   = $job_data['batch_replace_col'];
$filename_field = $job_data['filename_field'];
$no_exif         = ($job_data['no_exif'] == "yes") ? true : false ;

if (!file_exists($local_path)) {
    job_queue_update($jobref, $job_data, STATUS_ERROR);
}

$logtext = array();

include_once __DIR__ . '/../image_processing.php';

if (!isset($collectionid) || $collectionid == 0) {
    $conditions = array();
    $minref = max((int)($minref), $fstemplate_alt_threshold);
    $firstref = max($fstemplate_alt_threshold, $minref);

    $sql_params = array("i", $minref);
    $sql_condition = "";

    if ($maxref > 0) {
        $sql_condition = " AND ref <= ?";
        $sql_params = array_merge($sql_params, array("i", (int)$maxref));
    }

    $replace_resources = ps_array("SELECT ref value FROM resource WHERE ref >= ? " . $sql_condition . " ORDER BY ref ASC", $sql_params, 0);
    $logtext[] = "Replacing files for resource IDs. Min ID: " . $minref  . (($maxref > 0) ? " Max ID: " . $maxref : "");
} else {
    $replace_resources = get_collection_resources($collectionid);
    $logtext[] = "Replacing resources within collection " . $collectionid . " only";
}


$logtext[] = "Embedded (EXIF) data extraction: " . ($no_exif ? "OFF" : "ON");
$replaced = array();
$errors = array();

$foldercontents = new DirectoryIterator($local_path);
foreach ($foldercontents as $objectindex => $object) {
    if ($object->isDot() || $object->isDir() || !($object->isReadable())) {
        continue;
    }

    $filename   = $object->getFilename();
    $extension  = $object->getExtension();
    $full_path = $local_path . DIRECTORY_SEPARATOR . $filename;

    // get resource by $filename_field
    if ($filename_field != 0) {
        $target_resources = ps_array("SELECT resource value FROM resource_node rn LEFT JOIN node n ON n.ref = rn.node WHERE n.resource_type_field = ? AND n.name = ?", ["i", $filename_field, "s", $filename], "");

        $valid_resources = array_values(array_intersect($target_resources, $replace_resources));

        if (count($valid_resources) == 1) {
            $valid_resource = $valid_resources[0];
            // A single resource has been found with the same filename
            $rsfile = get_resource_path($valid_resource, true, '', true, $extension);
            try {
                copy($full_path, $rsfile);
                $success = filesize_unlimited($full_path) == filesize_unlimited($rsfile);
            } catch (Exception $e) {
                $success = false;
                $errors[] = "ERROR - Copy operation failed for {$full_path} : " . $e->getMessage();
            }
            if ($success) {
                ps_query("update resource set file_extension = lower(?) where ref = ?", array("s", $extension, "i", $valid_resource));
                resource_log($valid_resource, "u", 0);
                if (!$no_exif) {
                    extract_exif_comment($valid_resource, $extension);
                }
                create_previews($valid_resource, false, $extension);
                if ($notify_on_resource_change_days != 0) {
                    notify_resource_change($valid_resource);
                }
                $replaced[] = $valid_resource;
                try_unlink($full_path);
            } else {
                $errors[] = "Failed to copy file from : " .  $full_path;
            }
        } elseif (count($valid_resources) == 0) {
            // No resource found with the same filename
            $errors[] = "Failed to find matching file for: " .  $filename;
        } else {
            // Multiple resources found with the same filename
            if ($replace_batch_existing) {
                $copy_error = false;
                foreach ($valid_resources as $valid_resource) {
                    $rsfile = get_resource_path($valid_resource, true, '', true, $extension);
                    try {
                        copy($full_path, $rsfile);
                        $success = filesize_unlimited($full_path) == filesize_unlimited($rsfile);
                    } catch (Exception $e) {
                        $success = false;
                        $errors[] = "ERROR - Copy operation failed for {$full_path} : " . $e->getMessage();
                    }
                    if ($success) {
                        ps_query("update resource set file_extension = lower(?) where ref = ?", array("s", $extension, "i", $valid_resource));
                        resource_log($valid_resource, "u", 0);
                        if (!$no_exif) {
                            extract_exif_comment($valid_resource, $extension);
                        }
                        create_previews($valid_resource, false, $extension);
                        if ($notify_on_resource_change_days != 0) {
                            notify_resource_change($valid_resource);
                        }
                        $replaced[] = $valid_resource;
                    } else {
                        $errors[] = "Failed to copy file from : " .  $full_path;
                        $copy_error = true;
                    }
                }
                // Attempt to delete
                if (!$copy_error) {
                    try_unlink($full_path);
                }
            } else {
                // Multiple resources found with the same filename
                $resourcelist = implode(",", $valid_resources);
                $errors[] = "ERROR - multiple resources found with filename '" . $filename . "'. Resource IDs : " . $resourcelist;
            }
        }
    } else {
        # Overwrite an existing resource using the number from the filename.
        $targetresource = $object->getBasename("." . $extension);
        if ((string)(int)($targetresource) == (string)$targetresource && in_array($targetresource, $replace_resources) && !resource_file_readonly($targetresource)) {
            $rsfile = get_resource_path($targetresource, true, '', true, $extension);
            try {
                copy($full_path, $rsfile);
                $success = filesize_unlimited($full_path) == filesize_unlimited($rsfile);
            } catch (Exception $e) {
                $success = false;
                $errors[] = "ERROR - Copy operation failed for {$full_path} : " . $e->getMessage();
            }
            if ($success) {
                ps_query("update resource set file_extension = lower(?) where ref = ?", array("s", $extension, "i", $targetresource));
                resource_log($targetresource, "u", 0);
                if (!$no_exif) {
                    extract_exif_comment($targetresource, $extension);
                }
                create_previews($targetresource, false, $extension);
                if ($notify_on_resource_change_days != 0) {
                    notify_resource_change($targetresource);
                }
                $replaced[] = $targetresource;
                unlink($full_path);
            } else {
                $errors[] = "Failed to copy file from : " .  $full_path;
            }
        } else {
            // No resource found with the same filename
            $errors[] = "ERROR - no ref matching filename: '" . $filename . "', id: " . $targetresource;
        }
    }
}

$logtext[] = "Replaced " . count($replaced) . " resource files: -";

if (count($replaced) > 0) {
    $logtext[] = "Replaced resource files for IDs:";
    $logtext[] = implode(",", $replaced);
}

if (count($errors) > 0) {
    $logtext[] = "ERRORS: -";
    $logtext = array_merge($logtext, $errors);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
} else {
    if ($offline_job_delete_completed) {
        job_queue_delete($jobref);
    } else {
        job_queue_update($jobref, $job_data, STATUS_COMPLETE);
    }
    $jobsuccess = true;
}

echo " --> " . implode("\n --> ", $logtext) . "\n";
message_add($job["user"], implode("<br />", $logtext), (count($replaced) > 0) ? $baseurl_short . "pages/search.php?search=!list" . implode(":", $replaced) : "");

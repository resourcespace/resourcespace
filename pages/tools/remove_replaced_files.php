<?php
# Script to permanently delete from the filestore all alt files created by the replace file feature.
# Warning: This cannot be undone!
# Once the alt files have been removed, it will not be possible to revert a resource to an earlier version of the file
# in the resource log.

# Script default will be a dry run with no deletions.
# Review the output carefully before running again with 'delete' to actually remove the files e.g. 
# pages/tools/remove_replaced_files.php delete

include __DIR__ . '/../../include/boot.php';
command_line_only();

$dry_run = true;
if (isset($argv[1]) && $argv[1] == 'delete') {
    $dry_run = false;
}

# Get resource data for removal of alt files.
# The following are considered to determine if a resource / alt file should be processed:
# 1. Resource must exist i.e. exclude resource_log entries for fully deleted resources.
# 2. Resource log entry must contain preview_file_alt_ref - this is only set when replacing an original file.
# 3. Resource alt file data should have blank name, description, file_name and 0 file_size - as set by the code
#    used to create the alt file by the replace file feature.

$resource_alt_files = ps_query("SELECT 
        r.ref AS 'resource',
        rl.ref AS 'resource_log_ref',
        rl.previous_file_alt_ref,
        raf.file_extension AS 'alt_file_extension'
    FROM
        resource_log rl
            RIGHT JOIN
        resource r ON rl.resource = r.ref
            RIGHT JOIN
        resource_alt_files raf ON rl.previous_file_alt_ref = raf.ref
    WHERE
        rl.previous_file_alt_ref IS NOT NULL
            AND raf.file_name = ''
            AND raf.name = ''
            AND raf.file_size = 0
            AND raf.description = ''
    ORDER BY r.ref ASC , rl.previous_file_alt_ref ASC;");

echo 'Found ' . count($resource_alt_files) . ' alt files to be deleted' . PHP_EOL;

if (count($resource_alt_files) === 0) {
    exit();
}

if ($dry_run) {
    echo 'Running in dry run mode - no changes will be made' . PHP_EOL;
}

$update_disk_usage_resources = array();

# Process the resources found with alt files that were created by replace file.
# Print output path in dry run mode. Make the deletion too if running with 'delete' option.

foreach ($resource_alt_files as $alt_file) {
    $alt_file_location = $alt_file_thm_location = '';
    $update_disk_usage = false;

    $alt_file_location = get_resource_path($alt_file['resource'], true, '', true, $alt_file['alt_file_extension'], -1, 1, false, "", $alt_file['previous_file_alt_ref']);
    if (file_exists($alt_file_location)) {
        if ($dry_run) {
            echo "Resource {$alt_file['resource']} - Found alternative file $alt_file_location" . PHP_EOL;
        } else {
            $unlink_result = try_unlink($alt_file_location);
            if ($unlink_result === true) {
                echo "Resource {$alt_file['resource']} - Deleted alternative file $alt_file_location" . PHP_EOL;
                ps_query("UPDATE resource_log SET previous_file_alt_ref = NULL WHERE ref = ?;" , array('i', $alt_file['resource_log_ref'])); # Don't show revert option in resource log.
                ps_query("DELETE FROM resource_alt_files WHERE ref = ?;", array('i', $alt_file['previous_file_alt_ref']));
                $update_disk_usage = true;
            } else {
                echo "ERROR - Resource {$alt_file['resource']} - An error occurred when deleting alternative file $alt_file_location" . PHP_EOL;
                if (!is_bool($unlink_result)) { 
                    echo "---- $unlink_result" . PHP_EOL;
                }
            }
        }
    }

    $alt_file_thm_location = get_resource_path($alt_file['resource'], true, 'thm', true, 'jpg', -1, 1, false, "", $alt_file['previous_file_alt_ref']);
    if (file_exists($alt_file_thm_location)) {
        if ($dry_run) {
            echo "Resource {$alt_file['resource']} - Found alternative file thumb $alt_file_thm_location" . PHP_EOL;
        } else {
            $unlink_result_thm = try_unlink($alt_file_thm_location);
            if ($unlink_result_thm === true) {
                echo "Resource {$alt_file['resource']} - Deleted alternative file thumb $alt_file_thm_location" . PHP_EOL;
                $update_disk_usage = true;
            } else {
                echo "ERROR - Resource {$alt_file['resource']} - An error occurred when deleting alternative file thumb $alt_file_thm_location" . PHP_EOL;
                if (!is_bool($unlink_result_thm)) { 
                    echo "---- $unlink_result_thm" . PHP_EOL;
                }
            }
        }
    }

    if ($update_disk_usage && !in_array($alt_file['resource'], $update_disk_usage_resources)) {
       $update_disk_usage_resources[] = $alt_file['resource'];
    }
} 

# Only after deleting files (not for dry run), recalculate disk usage to account for the deleted files.

if (!$dry_run && count($update_disk_usage_resources) > 0) {
    echo 'Updating disk usage for ' . count($update_disk_usage_resources) . ' resources' . PHP_EOL;

    foreach ($update_disk_usage_resources as $update_resource) {
        update_disk_usage($update_resource);
        echo "Disk usage updated for resource $update_resource" . PHP_EOL;
    }
}

echo 'Script completed' . PHP_EOL;

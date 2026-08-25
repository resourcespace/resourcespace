<?php

# Deletes ALL alternative files with a description that contains "Automatically generated..."
# and file extensions "txt", "srt" or "vtt". These are automatically created by the Whisper plugin.
# Run first with dry run to verify changes before permanently deleting the files.
# e.g. php plugins/whisper/scripts/delete_extracted_subtitles.php -d

include_once dirname(__FILE__, 4) . '/include/boot.php';
command_line_only();

set_time_limit(0);

$shortopts = "d";
$longopts = array("dry-run");
$clargs = getopt($shortopts, $longopts);

$dryrun = isset($clargs["dry-run"]) || isset($clargs["d"]);

$alt_files = ps_query('SELECT raf.resource, raf.file_name, raf.ref 
                        FROM resource_alt_files raf 
                        WHERE raf.file_extension in ("txt", "srt", "vtt") AND raf.description LIKE "Automatically generated%"
                        ORDER BY raf.resource, raf.ref;');

$alt_file_count = count($alt_files);

if ($alt_file_count <= 0) {
    logScript("There are no matching alternative files to delete. Exiting");
    exit();
}

logScript(($dryrun ? "[DRY RUN] ": "") . "$alt_file_count matching alternative files to be deleted.");

$last_resource_ref = 0;
foreach ($alt_files as $alt_file) {
    if ($dryrun) {
        logScript("[DRY RUN] Alternative file " .  $alt_file['ref'] . " " . $alt_file['file_name'] . " for resource " . $alt_file['resource']);
    } else {
        $deleted = delete_alternative_file($alt_file['resource'], $alt_file['ref']);
        if ($deleted) {
            logScript("Deleted alternative file " .  $alt_file['ref'] . " for resource " . $alt_file['resource']);

            if ($last_resource_ref != $alt_file['resource']) {
                $last_resource_ref = $alt_file['resource'];
                $directory = dirname(get_resource_path($alt_file['resource'], true, "pre"));
                $unlink_result = false;
                foreach (glob($directory . "/*") as $filetoremove) {
                    if (strpos($filetoremove, 'whisper') !== false) {
                        $unlink_result = try_unlink($filetoremove);
                        }
                }

                if (!is_bool($unlink_result)) { 
                    logScript("Error deleting whisper temp files for resource " . $alt_file['resource']);
                }
            }
        } else {
            logScript("Error deleting alternative file " .  $alt_file['ref'] . " for resource " . $alt_file['resource']);
        }
    }
}
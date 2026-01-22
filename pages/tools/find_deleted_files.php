<?php

/**
* Utility script to check which resources don't have an original file.
*
* Note: the script does not care about previews since these can be recreated
*/

include __DIR__ . '/../../include/boot.php';
command_line_only();

ob_end_clean();
restore_error_handler();

$resources = ps_query("SELECT ref, file_extension FROM resource WHERE ref > 0 AND archive != ?", array("i",$resource_deletion_state));

foreach ($resources as $resource) {
    $file_path = get_resource_path($resource['ref'], true, '', false, $resource['file_extension']);

    if (file_exists($file_path) && filesize_unlimited($file_path) > 0) {
        continue;
    }

    echo $file_path . PHP_EOL;
}

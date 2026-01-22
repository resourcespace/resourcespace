<?php
# Checking is for file presence only. For validation of file checksums, use pages/tools/validate_resource_files.php

include_once __DIR__ . "/../../include/boot.php";

if (!$file_integrity_checks) {
    echo "Skipping file integrity checks.\n";
    return;
}

function check_valid_cron_time()
{
    // Check if in a valid time period
    $validtime = true;
    $curhour = date('H');
    if ($GLOBALS["file_integrity_verify_window"][0] <= $GLOBALS["file_integrity_verify_window"][1]) {
        // Second time is later than first or times are the same (off). Ensure time is not before the first or later than the second
        if ($curhour < $GLOBALS["file_integrity_verify_window"][0] || $curhour >= $GLOBALS["file_integrity_verify_window"][1]) {
            $validtime = false;
        }
    } else {
        // First time is later than second (running through midnight). Ensure time is not before the first and after the second
        if ($curhour < $GLOBALS["file_integrity_verify_window"][0] && $curhour >= $GLOBALS["file_integrity_verify_window"][1]) {
            $validtime = false;
        }
    }
    return $validtime;
}

if (is_process_lock("file_integrity_check")) {
    echo " - File integrity process lock is in place.Skipping.\n";
    return;
}

if (!check_valid_cron_time()) {
    if ('cli' == PHP_SAPI) {
        echo " - Outside of valid time period. Set times are between " . $file_integrity_verify_window[0] . ":00 and " . $file_integrity_verify_window[1] . ":00 hours. Current hour: " . date('H') . ":00" . $LINE_END;
    }
    return;
}
set_process_lock("file_integrity_check");

$resources = get_resources_to_validate(1);

$allfailures = [];
foreach (array_chunk($resources, 1000) as $resources_chunk) {
    $failures = check_resources($resources_chunk, true);
    $allfailures = array_merge($allfailures, $failures);
    if (check_valid_cron_time() === false) {
        // Reached end of window, quit
        break;
    }
}

if (count($allfailures) > 0) {
    send_integrity_failure_notices($allfailures);
}

clear_process_lock("file_integrity_check");

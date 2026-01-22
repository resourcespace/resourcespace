<?php

// Script to purge all resources in the deletion state (status 3)
// set following line to true to enable this script
command_line_only();

include __DIR__ . "/../../include/boot.php";
ob_end_clean(); // Discard output buffering as it's causing the script to stop working
include __DIR__ . "/../../include/image_processing.php";

if (ini_get('max_execution_time') < 1800 || $php_time_limit < 1800) {
    echo "Script maximum execution time should be set to at least 1800 seconds! Edit the config.php file and set \$php_time_limit.";
    exit;
}

// restore the default system error handler
// so that we can handle things like permission errors
// on our own.
restore_error_handler();

// make darn sure the user knows what they are doing!
echo "\nResourceSpace Purge Script\n\n";
echo "   This script will purge all files marked for deletion\n";
echo "   in the system. This is permanent, and cannot be undone\n";
echo "   \n\n";
echo "WARNING: THIS SCRIPT IS DANGEROUS AND MAY PERMANENTLY DELETE A LARGE NUMBER OF FILES!\nContinue at your own risk!\n";
echo "Are you sure you want to do this? Type \"yes\" to continue: \n> ";
$line = trim(fgets(STDIN));
if (strtolower($line) <> 'yes') {
        echo "Aborting...\n";
        exit;
}
echo "\n------------------------------------------------------------\n";

// override resource deletion state
unset($resource_deletion_state);

$topurge = ps_array("select ref as value from resource where archive = '3'", array());

$purgecount = 0;

foreach ($topurge as $ref) {
    echo "Purging $ref\n";
    delete_resource($ref);
    $purgecount++;
}

echo "\n\nComplete. $purgecount resources purged.\n\n";

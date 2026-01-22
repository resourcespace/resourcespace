<?php

/*

Go from resource 0 to highest resource in system looking for directories
in the filestore without associated resource table entries. If they exist,
delete them.

*/

include __DIR__ . "/../../include/boot.php";
include __DIR__ . "/../../include/authenticate.php";
if (!checkperm("a")) {
    exit("Permission denied");
}

$dryrun = getval('dryrun', '');
if (strlen($dryrun) > 0) {
    $dryrun = true;
} else {
    $dryrun = false;
}

$start_id = 1;
$max_id = ps_value("select max(ref) value from resource", array(), 1);
echo "\n<pre>\n";

for ($checking = $start_id; $checking <= $max_id; $checking++) {
    $thedir = dirname(get_resource_path($checking, true, '', false));
    if (!file_exists($thedir)) {
        continue;
    }
    $exists = ps_value("select count(ref) value from resource where ref = ?", array("i",$checking), 0);
    if ($exists == 0) {
        // No database record for this directory!
        echo "$checking: checking $thedir\n";
        echo "    DATABASE RECORD NOT FOUND!\n";
        rrmdir($thedir);
    }
}

# recursively remove directory
function rrmdir($dir)
{
    global $dryrun;
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            rrmdir($file);
        } else {
            if ($dryrun) {
                echo "    would be unlinking $file\n";
            } else {
                echo "    unlinking $file\n";
                 unlink($file);
            }
        }
    }
    if ($dryrun) {
        echo "    would be removing $dir\n";
    } else {
        echo "    removing $dir\n";
        if (file_exists($dir . "/.DS_Store")) {
            echo "    unlinking " . $dir . "/.DS_Store\n";
            unlink($dir . "/.DS_Store");
        }
        rmdir($dir);
    }
}

echo "\n-----------------------------------\nRun complete.";
echo "\n-----------------------------------\n";
echo "</pre>\n";

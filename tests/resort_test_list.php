<?php
include "../include/boot.php";
command_line_only();

$dir = __DIR__ . "/test_list"; 
$files = glob($dir . "/*.php");


// Renumber the test list

usort($files, function($a, $b) {
    $anum = intval(basename($a));
    $bnum = intval(basename($b));
    return $anum <=> $bnum;
});

$counter = 10;
foreach ($files as $file) {
    $basename = basename($file);

    // Extract current numeric prefix and the rest
    if (preg_match('/^(\d{6})(_.+)$/', $basename, $matches)) {
        $newprefix = str_pad($counter, 6, "0", STR_PAD_LEFT);
        $newname   = $newprefix . $matches[2];
        $newpath   = $dir . DIRECTORY_SEPARATOR . $newname;

        if ($newname !== $basename) {
            echo "svn rename \"$basename\" \"$newname\"\n";
            $cmd = sprintf('svn rename %s %s',
                escapeshellarg($file),
                escapeshellarg($newpath)
            );
            system($cmd, $retval);
            if ($retval !== 0) {
                echo "  ERROR: svn rename failed for $basename\n";
            }
        }

        $counter += 10;
    }
}

<?php

include_once __DIR__ . "/../../include/slideshow_functions.php";

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Checking current slideshow images in homeanim folder");

$web_root = dirname(dirname(__DIR__));
$homeanim_folder_path = "{$web_root}/{$homeanim_folder}";

if (!is_dir($homeanim_folder_path)) {
    // May be a newly configured system that has not followed setup.php
    return;
}

$found_files = array();
$files = new \DirectoryIterator($homeanim_folder_path);
foreach ($files as $file) {
    if ($file->isDot() || !$file->isFile()) {
        continue;
    }

    $found_files[] = $file->getFilename();
}

// Sort ASC the files before inserting into database
natsort($found_files);
$found_files = array_values($found_files);

foreach ($found_files as $index => $file) {
    $login_show = 0;
    if ($index == 0) {
        $login_show = 1;
    }

    // Check if slideshow image is linked to a resource
    $resource_ref = null;
    $filename = pathinfo($file, PATHINFO_FILENAME);
    $txt_file = "{$filename}.txt";
    $txt_file_path = "{$homeanim_folder_path}/{$txt_file}";

    if ($file === $txt_file) {
        unlink($txt_file_path);

        continue;
    }

    if (in_array($txt_file, $found_files)) {
        $txt_file_content = file_get_contents($txt_file_path);
        if ($txt_file_content !== false) {
            $resource_ref = $txt_file_content;
        }
    }

    $new_slideshow_image = set_slideshow($filename, 1, 0, $login_show);
    if (!$new_slideshow_image) {
        $log = PHP_EOL . "Warning - could not create a new slideshow record for {$file}" . PHP_EOL;
        set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $log);
        echo $cli ? $log : nl2br(str_pad($log, 4096));
        ob_flush();
        flush();

        continue;
    }

    $log = PHP_EOL . "Created a new slideshow record (ID #{$new_slideshow_image}) based on {$file}" . PHP_EOL;
    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $log);
    echo $cli ? $log : nl2br(str_pad($log, 4096));
    ob_flush();
    flush();
}

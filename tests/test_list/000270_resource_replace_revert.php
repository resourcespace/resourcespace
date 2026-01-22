<?php

command_line_only();

include_once __DIR__ . "/../../include/image_processing.php";

$resourcea = create_resource(1, 0);

// Create a file upload
$resource_file_path = get_temp_dir() . DIRECTORY_SEPARATOR . "testfile.jpg";
$replacefile = imagecreate(140, 50);
$bg_col = imagecolorallocate($replacefile, 221, 124, 6);
$text_col = imagecolorallocate($replacefile, 255, 255, 255);
imagestring($replacefile, 5, 20, 15, 'Test file', $text_col);
imagejpeg($replacefile, $resource_file_path, 50);
upload_file($resourcea, false, false, false, $resource_file_path, false, true);

// Subtest A - ensure upload has been logged correctly
$log = get_resource_log($resourcea);
$logok = false;
foreach ($log["data"] as $logentry) {
    if ($logentry["type"] == LOG_CODE_UPLOADED && $logentry["resource"] == $resourcea) {
        $logok = true;
    }
}

if (!$logok) {
    echo "Subtest A";
    return false;
}

// Simulate uploading a replacement file
$replacement_path = get_temp_dir() . DIRECTORY_SEPARATOR . "replacement.jpg";
$replacefile = imagecreate(140, 50);
$bg_col = imagecolorallocate($replacefile, 136, 204, 119);
$text_col = imagecolorallocate($replacefile, 255, 255, 255);
imagestring($replacefile, 5, 20, 15, 'Replacement', $text_col);
imagejpeg($replacefile, $replacement_path, 50);
upload_file($resourcea, false, false, false, $replacement_path, false, true);

// Subtest B - attempt revert
$log = get_resource_log($resourcea);
$logok = false;
foreach ($log["data"] as $logentry) {
    if ($logentry["type"] == LOG_CODE_UPLOADED && $logentry["resource"] == $resourcea) {
        $success = revert_resource_file($resourcea, $logentry, false);
        if (!$success) {
            echo "Subtest B";
            return false;
        }
    }
}

return true;

<?php
include __DIR__ . '/../../../include/boot.php';

command_line_only();
$errors = offline_archive_run_jobs(true);
if(is_array($errors) && PHP_SAPI == "cli")
    {
    echo "Errors: " . PHP_EOL . implode(PHP_EOL,$errors) . PHP_EOL;
    }
else
    {
    echo "Offline archive job complete" . PHP_EOL;
    }


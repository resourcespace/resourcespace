<?php
// Do the include and authorization checking ritual
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}


$resource = getval("resource", 0, true);

if($resource == 0)
    {
    exit($lang["error"]);
    }

include __DIR__ . "/../include/google_vision_functions.php";

google_visionProcess($resource);
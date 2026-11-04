<?php

include '../../../include/boot.php';

$ref = (int) getval('ref', 0, true);
$k = getval('k', '');
if ($k === '' || !check_access_key($ref, $k)) {
    include '../../../include/authenticate.php';
}

include_once dirname(__DIR__) . '/include/video_scene_functions.php';
video_scene_serve_thumbnail();

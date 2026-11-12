<?php

include_once dirname(__DIR__) . '/include/panoramic_viewer_functions.php';

$panoramic_viewer_restypes = [];
$panoramic_viewer_autopan = true;
$panoramic_viewer_vr_mode_off = false;
$panoramic_viewer_projection_field = 0;
$panoramic_viewer_projection_value = 'equirectangular';
$panoramic_viewer_stereo_field = 0;
$panoramic_viewer_stereo_value = '';
$panoramic_viewer_yaw_only_field = 0;
$panoramic_viewer_yaw_only_value = '';
$panoramic_viewer_orig_image = true;
$panoramic_viewer_orig_video = true;

// Register metadata field settings so fields in use cannot be deleted.
$panoramic_viewer_fieldvars = [
    'panoramic_viewer_projection_field',
    'panoramic_viewer_stereo_field',
    'panoramic_viewer_yaw_only_field',
];

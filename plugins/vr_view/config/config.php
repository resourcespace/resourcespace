<?php
$vr_view_google_hosted = true;
$vr_view_js_url = "";
$vr_view_restypes = array();
$vr_view_autopan = true;
$vr_view_vr_mode_off = false;

$vr_view_projection_field = 0;
$vr_view_projection_value = 'equirectangular';
$vr_view_stereo_field = 0;
$vr_view_stereo_value = '';
$vr_view_yaw_only_field = 0;
$vr_view_yaw_only_value = '';

$vr_view_orig_image = true;
$vr_view_orig_video = false;

// Add any new vars that specify metadata fields to this array to stop them being deleted if plugin is in use
// These are added in hooks/all.php
$vr_view_fieldvars = array("vr_view_projection_field","vr_view_stereo_field","vr_view_yaw_only_field");

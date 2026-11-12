<?php

$lang['panoramic_viewer_title'] = 'Panoramic Viewer';
$lang['panoramic_viewer_configuration'] = 'Panoramic Viewer configuration';
$lang['panoramic_viewer_restypes'] = 'Resource types to display using Panoramic Viewer';
$lang['panoramic_viewer_autopan'] = 'Enable automatic panning';
$lang['panoramic_viewer_vr_mode_off'] = 'Disable mobile stereo view button';
$lang['panoramic_viewer_condition'] = 'Panoramic Viewer condition';
$lang['panoramic_viewer_condition_detail'] =
    'Optionally select a metadata field and exact value to enable panoramic previews. For '
    . 'EXIF-based activation, map the exiftool ProjectionType tag to a metadata field and '
    . 'match equirectangular. Without a field condition, all selected resource types are '
    . 'treated as panoramas. Images and videos must be equirectangular.';
$lang['panoramic_viewer_projection_field'] = 'Projection type metadata field';
$lang['panoramic_viewer_projection_value'] = 'Required value to enable the viewer';
$lang['panoramic_viewer_additional_options'] = 'Additional options';
$lang['panoramic_viewer_additional_options_detail'] =
    'Optional metadata conditions control packed stereo source handling and '
    . 'horizontal-only movement. Top/bottom stereo sources display the left-eye image from '
    . 'the upper half, without stereoscopic depth. Mobile stereo view requires device '
    . 'orientation support and HTTPS; it does not provide separate left/right source images.';
$lang['panoramic_viewer_stereo_field'] = 'Field identifying top/bottom stereo images or videos (optional)';
$lang['panoramic_viewer_stereo_value'] = 'Value identifying a top/bottom stereo source';
$lang['panoramic_viewer_yaw_only_field'] = 'Field restricting movement to the horizontal axis (optional)';
$lang['panoramic_viewer_yaw_only_value'] = 'Value enabling horizontal-only movement';
$lang['panoramic_viewer_orig_image'] = 'Use original resource file for image preview?';
$lang['panoramic_viewer_orig_video'] = 'Use original resource file for video preview?';
$lang['panoramic_viewer_loading'] = 'Loading panorama...';
$lang['panoramic_viewer_error'] =
    'Unable to display this panorama. The file may be unavailable, unsupported, or your '
    . 'browser may not support WebGL. Close the preview to return to the resource.';
$lang['panoramic_viewer_stereo_notice'] =
    'This top/bottom stereo source is displayed using the left-eye image from the upper '
    . 'half. Stereoscopic depth is unavailable.';
$lang['plugin-panoramic_viewer-title'] = 'Panoramic Viewer';
$lang['plugin-panoramic_viewer-desc'] = '360 degree image and video previews using Photo Sphere Viewer.';
$lang['page-title_panoramic_viewer_setup'] = 'Configure Panoramic Viewer';
$lang['panoramic_viewer_control_zoom'] = 'Zoom';
$lang['panoramic_viewer_control_zoomOut'] = 'Zoom out';
$lang['panoramic_viewer_control_zoomIn'] = 'Zoom in';
$lang['panoramic_viewer_control_moveUp'] = 'Move up';
$lang['panoramic_viewer_control_moveDown'] = 'Move down';
$lang['panoramic_viewer_control_moveLeft'] = 'Move left';
$lang['panoramic_viewer_control_moveRight'] = 'Move right';
$lang['panoramic_viewer_control_fullscreen'] = 'Fullscreen';
$lang['panoramic_viewer_control_loading'] = 'Loading...';
$lang['panoramic_viewer_control_menu'] = 'Menu';
$lang['panoramic_viewer_control_close'] = 'Close';
$lang['panoramic_viewer_control_twoFingers'] = 'Use two fingers to navigate';
$lang['panoramic_viewer_control_ctrlZoom'] = 'Use Ctrl and scroll to zoom';
$lang['panoramic_viewer_control_autorotate'] = 'Automatic panning';
$lang['panoramic_viewer_control_gyroscope'] = 'Device orientation';
$lang['panoramic_viewer_control_stereo'] = 'Mobile stereo view';
$lang['panoramic_viewer_control_stereoNotification'] = 'Tap anywhere to exit stereo view.';
$lang['panoramic_viewer_control_pleaseRotate'] = 'Please rotate your device';
$lang['panoramic_viewer_control_tapToContinue'] = '(or tap to continue)';
$lang['panoramic_viewer_control_videoPlay'] = 'Play/Pause';
$lang['panoramic_viewer_control_videoVolume'] = 'Volume';

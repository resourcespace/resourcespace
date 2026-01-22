<?php
$flickr_api_key="b18347aba9149fc9a2c878fabd0f4a1a";
$flickr_api_secret="64e4b1b4daacae06";

$flickr_title_field = $GLOBALS['view_title_field'] ?? 0;
$flickr_caption_field=18;
$flickr_keywords_field=1;

$flickr_prefix_id_title=true;
$flickr_default_size="scr";
# option to try the next largest jpg preview size if for some reason a scr isn't avaialable and includes up to original jpgs.
$flickr_scale_up=false;
$flickr_alt_image_sizes=array("lpr","hpr","original");

$flickr_nice_progress=false;
$flickr_nice_progress_previews=true;
$flickr_nice_progress_metadata=true;
$flickr_nice_progress_min_timeout=500;
$flickr_nice_progress_max_timeout=2000;

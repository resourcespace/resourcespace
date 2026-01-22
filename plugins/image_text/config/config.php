<?php
$image_text_field_select=8;
$image_text_override_groups=array();
$image_text_default_text="";
$image_text_restypes=array();
$image_text_filetypes=array('TIF','TIFF','JPG','JPEG','PNG','GIF','BMP');
$image_text_max_height=0;
$image_text_min_height=0;
$image_text_height_proportion=.05;
$image_text_font="Arial-Regular";
$image_text_position="east";
$image_text_banner_position="bottom";

// Add any new vars that specify metadata fields to this array to stop them being deleted if plugin is in use
// These are added in hooks/all.php
$image_text_fieldvars = array("image_text_field_select");
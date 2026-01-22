<?php
# English
# Language File for the Transform Plugin
# -------
#
#
# Resource log - actions
$lang['transform']="Transform";
$lang['transformimage']="Transform Image";
$lang['transformed']="Transform";
$lang['imagetools']="Image tools";
$lang['imagetoolstransform']="Image tools - Transform";
$lang['imagetoolstransformoriginal']="Image tools - Transform Original";
$lang['transformblurb']="Select the required action and then use the icons alongside the image to edit the image. To crop the image, drag your pointer across the image. You can move and resize the crop box once you have created it. If you don't specify a width and height, the original height and width of the crop area will be used.";
$lang['transformblurb-original']="To crop the image, drag your pointer across the image. You can move and resize the crop box once you have created it. When you are done, press transform original. Optionally, you can choose a width and/or height to rescale the cropped image.<br/><br/><strong>All the options below can be left blank.</strong> If you don't provide a width and height, the original height and width of the crop area will be used.";
$lang['width']="New width";
$lang['height']="New height";
$lang['px']="px";
$lang['noimagefound'] = "Error: No image found.";
$lang['scaled'] = "Scaled";
$lang['cropped'] = "Cropped";
$lang['tweaked'] = "Tweaked";
$lang['nonnumericcrop'] = "Error: Non-numeric crop requested.";
$lang['description_for_alternative_file'] = "Description (for alternative file)";
$lang['errorspecifiedbiggerthanoriginal'] = "Error: Specified width or height is greater than the original image.";
$lang['errormustchoosecropscale'] = "Error: You must choose a crop and/or enter scaling values.";
$lang['savealternative']="Save as alternative file";
$lang['rotation']="Rotation";
$lang['rotation0']="No Rotation";
$lang['rotation90']="90 degrees CW";
$lang['rotation180']="180 degrees";
$lang['rotation270']="90 degrees CCW";
$lang['fliphorizontal']="Flip horizontally?";
$lang['transform_original']="Transform original";
$lang['priorversion']="Prior version";
$lang['replaced']="Replaced";
$lang['replaceslideshowimage']="Replace slideshow image";
$lang['slideshowsequencenumber']="Sequence number (1, 2, 3 etc.)";
$lang['transformcrophelp']="Click and drag on the image to the left to select a crop area.";
$lang['originalsize']="Original Size";
$lang['allow_upscale']="Allow upscale?";
$lang['batchtransform'] = "Batch transform";
$lang['batchtransform-introtext']="WARNING: This will permanently change the original resource files. Use caution!";
$lang['error-crop-imagemagick-not-configured']="Error: ImageMagick must be configured for crop functionality. Please contact your system administrator.";
$lang['error-dimension-zero'] = "Error: transform preview image has a calculated width/height of zero.";
$lang['no_resources_found']="no resources found";
$lang['batch_transforming_collection']="Batch transforming collection %col"; # %col will be replaced with the collection id
$lang['not-transformed']="was not transformed: Access Denied.";
$lang['error-unable-to-rename']="Error: unable to rename transformed file for resource %res."; # %res will be replaced with the resource id
$lang['error-transform-failed']="Error: Transform of resource %res failed."; # %res will be replaced with the resource id
$lang['transform_summary']="Summary";
$lang['resources_in_collection-1']="1 resource in collection.";
$lang['resources_in_collection-2']="%qty resources in collection."; # %qty will be replaced with the quantity of resources in collection
$lang['resources_transformed_successfully-0']="0 resources transformed successfully.";
$lang['resources_transformed_successfully-1']="1 resource transformed successfully.";
$lang['resources_transformed_successfully-2']="%qty resources transformed successfully."; # %qty will be replaced with the quantity of transformed resources
$lang['errors-1']="1 error.";
$lang['errors-2']="%qty errors."; # %qty will be replaced with the quantity of errors
$lang["transform_preview_gen_error"] = "Error generating transform preview.";

$lang['transform_configuration']="Transform Configuration";
$lang['cropper_debug']="Cropper Debug";
$lang['output_formats']="Output Formats";
$lang['input_formats']="Input Formats";
$lang['custom_filename']="Custom Filename";
$lang['allow_rotation']="Allow Rotation";
$lang['allow_transform_original']="Allow Transform Original";
$lang['use_repage']="Use 'repage'";
$lang['enable_batch_transform']="Enable Batch Transform";
$lang['cropper_enable_alternative_files']='Enable storing alternative files';
$lang['enable_replace_slideshow']='Enable replacing the slideshow';
$lang['cropper_restricteduse_groups']='Restrict to rescale only (no rotate or crop options) for the selected groups';
$lang['transformblurbrestricted']="Choose a width and/or height to rescale the image. When you are done, choose a name and format for the new file and click download.<br/><br/>";
$lang['cropper_resolutions']="Preset resolution changes e.g 72,300. If set the values here will be available for users to select as the embedded file resolution";
$lang['cropper_resolution_select']="Select from the preset resolution (PPI).<br>Leave blank to use the original resolution value";
$lang['cropper_quality_select'] = "Allow user to select the quality of resultant file (JPG/PNG only)";
$lang['cropper_srgb_option'] = "Add option to force use of sRGB profile";
$lang['cropper_jpeg_rgb'] = "Force sRGB (overrides user option)";
$lang['cropper_use_srgb'] = "Use sRGB";
$lang['transform-recrop'] = "Re-crop image";
$lang['use_system_icc_profile_config'] = "Use system configuration setting for ICC profile processing. Will override sRGB options above.";
$lang['transform_update_preview']   = "Update preview";
$lang['transform_preset_sizes']     = "Select from predefined target size";
$lang['error_crop_invalid'] = "Please select a region of the image";

$lang['cropper_preset_sizes'] = "Predefined sizes";

$lang["page-title_transform_collection_transform"]  = "Transform Resource";
$lang["page-title_transform_crop"]                  = "Crop Resource";
$lang["page-title_transform_setup"]                 = "Setup Transform Plugin";

$lang["use_filename_as_title"] = "Use filename as title";
<?php

function HookCustom_filenameAllUploadfilesuccess($resource_ref)
{
    global $filename_field, $cf_field, $cf_keep_extension;

    $filename = get_data_by_field($resource_ref, $filename_field);

    if (!is_string($filename) || trim($filename) == '') {
        return;
    }

    $filename_path_parts = pathinfo($filename);
    $filename_custom = trim($filename_path_parts['filename'] ?? '');
    $filename_extension = trim($filename_path_parts['extension'] ?? '');
    
	if ($filename_extension == '' || $filename_custom == '') {
		return;
	}
    
	if ($cf_keep_extension && $filename_extension != '') {
        $filename_custom .= ".{$filename_extension}";
    }
    
    $cf_errors = array();
    update_field($resource_ref, $cf_field, $filename_custom, $cf_errors);
    
    if (!empty($cf_errors)) {
        debug("CUSTOM_FILENAME - Uploadfilesuccess hook: Errors when updating field '{$cf_field}':");
        foreach ($cf_errors as $error) {
            debug("CUSTOM_FILENAME: {$error}");
        }
    }
}

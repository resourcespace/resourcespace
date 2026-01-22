<?php

function HookCustom_filenameAllUploadfilesuccess($resource_ref)
{
    global $filename_field, $cf_field;

    $filename = get_data_by_field($resource_ref, $filename_field);

    if (!is_string($filename) || trim($filename) == '') {
        return;
    }

    $filename_path_parts = pathinfo($filename);

    if (trim($filename_path_parts['extension']) != '') {
        $cf_errors = array();
        update_field($resource_ref, $cf_field, $filename_path_parts['filename'], $cf_errors);
    }

    if (!empty($cf_errors)) {
        debug("CUSTOM_FILENAME - Uploadfilesuccess hook: Errors when updating field '{$cf_field}':");
        foreach ($cf_errors as $error) {
            debug("CUSTOM_FILENAME: {$error}");
        }
    }
}

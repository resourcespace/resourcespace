<?php

command_line_only();

// --- Set up
$original_scramble_key = $scramble_key;
$scramble_key = 'c8cf6994c288cf9d75c64017c57d16b24a0fdb4f0b826c66bfa7da19541178e9';
$path = get_resource_path(1, true, '');
$dirname_path = "{$storagedir}/1_6326bb8314c6c21";
$dirname_url = "{$storageurl}/1_6326bb8314c6c21";

if (0 >= strlen($path)) {
    return false;
}


// For most of these cases, we are expecting either a physical path/ URL to filestore,
// so set this config option to false. Enable it only when testing hiding the real file path
$hide_real_filepath = false;
$file_modified =  1234; # Dummy value to use for modified time.
// --- End of Set up


// Original file path/ URL
if (
"{$dirname_path}/1_71a3211b5d04a88.jpg" != get_resource_path(1, true, '')
    || "{$dirname_url}/1_71a3211b5d04a88.jpg?v={$file_modified}" != get_resource_path(1, false, '', true, 'jpg', true, 1, false, $file_modified)
) {
    echo 'Case: Original file path/ URL -- ';
    return false;
}

// Get specific size (e.g: Preview) path/ URL
if (
"{$dirname_path}/1pre_cf33a61f47b5982.jpg" != get_resource_path(1, true, 'pre')
    || "{$dirname_url}/1pre_cf33a61f47b5982.jpg?v={$file_modified}" != get_resource_path(1, false, 'pre', true, 'jpg', true, 1, false, $file_modified)
) {
    echo 'Case: Get specific size (e.g: Preview) path/ URL -- ';
    return false;
}

if ("{$dirname_path}/1_71a3211b5d04a88.jpg" != get_resource_path(1, true, null)) {
    echo 'Case: null size is identical to requesting original (historically true) -- ';
    return false;
}

// Check Generation of folder
if (!file_exists(get_resource_path(100000, true, '', false))) {
    $generated_path = get_resource_path(100000, true, '', true);
    copy(__DIR__ . '/../../gfx/homeanim/1.jpg', $generated_path);

    if (!file_exists($generated_path)) {
        echo 'Case: Check Generation of folder -- ';
        return false;
    }
}

// Check looking for specific preview by extension (like videos/ audio)
if ("{$dirname_path}/1pre_cf33a61f47b5982.mp4" != get_resource_path(1, true, 'pre', false, 'mp4')) {
    echo 'Case: Check looking for specific preview by extension (like videos/ audio) -- ';
    return false;
}

if ("{$dirname_path}/1pre_cf33a61f47b5982.jpg" != get_resource_path(1, true, 'pre', false, null)) {
    echo 'Case: null extension is identical to empty string (historically true) -- ';
    return false;
}

// Check getting a scrambled version of the path/ URL
if (
"{$dirname_path}/1pre_cf33a61f47b5982.jpg" != get_resource_path(1, true, 'pre', false, 'jpg') ||
    "{$dirname_url}/1pre_cf33a61f47b5982.jpg?v={$file_modified}" != get_resource_path(1, false, 'pre', false, 'jpg', true, 1, false, $file_modified)
) {
    echo 'Case: Check getting a scrambled version of the path/ URL -- ';
    return false;
}

// Check getting a non-scrambled version of the path/ URL
if ("{$storagedir}/1/1pre.jpg" != get_resource_path(1, true, 'pre', false, 'jpg', false, 1, false, $file_modified)) {
    echo 'Case: Check getting a non-scrambled version of the path/ URL -- ';
    return false;
}

// Check getting a page preview of a document
if ("{$dirname_path}/1pre_3_0092923182f54ea.jpg" != get_resource_path(1, true, 'pre', false, 'jpg', true, 3, false, $file_modified)) {
    echo 'Case: Check getting a page preview of a document -- ';
    return false;
}

// Check getting the watermarked version of a preview
if ("{$dirname_path}/1pre_wm_349473228947cd0.jpg" != get_resource_path(1, true, 'pre', false, 'jpg', true, 1, true, $file_modified)) {
    echo 'Case: Check getting the watermarked version of a preview -- ';
    return false;
}

// Check getting alternative paths/ URLs
if ("{$dirname_path}/1pre_alt_10_9ef1f74b56b64ed.jpg" != get_resource_path(1, true, 'pre', false, 'jpg', true, 1, false, '', 10)) {
    echo 'Case: Check getting alternative paths/ URLs -- ';
    return false;
}

// Check not including file_modified in URLs
// NOTE: including it has been tested previously since this parameter is set to TRUE by default
if ("{$dirname_url}/1pre_cf33a61f47b5982.jpg" != get_resource_path(1, false, 'pre', false, 'jpg', true, 1, false, '', 0, false)) {
    echo 'Case: Check not including file_modified in URLs -- ';
    return false;
}

$bad_value = '/../../../some/file';
if ("{$dirname_path}/1somefile_8b100eaa994a003.jpg" != get_resource_path(1, true, $bad_value)) {
    echo 'Case: malicious size value -- ';
    return false;
}

if ("{$dirname_path}/1pre_cf33a61f47b5982.somefile" != get_resource_path(1, true, 'pre', false, $bad_value)) {
    echo 'Case: malicious extension value -- ';
    return false;
}

// Check file path can be returned for banned extension
if ("{$dirname_path}/1pre_cf33a61f47b5982.php" != get_resource_path(1, true, 'pre', false, 'php', true)) {
    echo 'Case: Check getting banned extension path -- ';
    return false;
}
// Teardown
$scramble_key = $original_scramble_key;

return true;

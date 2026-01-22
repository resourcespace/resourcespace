<?php

include "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include "../include/file_functions.php";

$ref = getval("ref", 0, true);
$resource = getval("resource", 0, true);
$file_path = get_license_file_path((int) $ref);

# Check access
if (is_positive_int_loose($resource)) {
    $edit_access = get_edit_access($resource);
    if (!$edit_access && !checkperm("a") && !checkperm("lm")) {
        # Should never arrive at this page without edit access
        exit("Access denied");
    }
} else {
    # Editing all license via Manage Licenses - admin only
    if (!checkperm("a") && !checkperm("lm")) {
        exit("Access denied");
    }
}

// Load license details
$license = ps_query(
    "SELECT outbound, holder, license_usage, description, expires, file FROM license WHERE ref=?",
    array("i",$ref)
);
if (count($license) == 0) {
    exit("License record not found.");
}
$license = $license[0];

// Get the file extension (convert to lowercase for case-insensitive comparison)
$file_extension = strtolower(parse_filename_extension($license["file"]));

if (array_key_exists($file_extension, INLINE_VIEWABLE_TYPES)) {
    // Set the Content-Type header for inline viewing
    header('Content-Type: ' . INLINE_VIEWABLE_TYPES[$file_extension]);
    // Set the Content-Disposition header to inline
    header('Content-Disposition: inline; filename="' . $license["file"]  . '"');
    // Set Content-Length for better browser handling
    header('Content-Length: ' . filesize($file_path));
    // Prevent caching
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // A date in the past
    readfile($file_path);
    exit;
} else {
    // For other file types, force download (your original behavior)
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $license["file"] . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit; // Important to stop further script execution
}

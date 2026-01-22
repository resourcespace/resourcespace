<?php

include "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include_once "../include/file_functions.php";

$ref = getval("ref", "", true);
$resource = getval("resource", "", true);
$file_path = get_consent_file_path($ref);

# Check access
if ($resource != "") {
    $edit_access = get_edit_access($resource);
    if (!$edit_access && !checkperm("cm")) {
        exit("Access denied");
    } # Should never arrive at this page without edit access
} else {
    # Editing all consents via Manage Consents - admin only
    if (!checkperm("a") && !checkperm("cm")) {
        exit("Access denied");
    }
}

// Load consent details
$consent = ps_query("select name,email,telephone,consent_usage,notes,expires,file from consent where ref= ?", ['i', $ref]);

if (count($consent) == 0) {
    exit("Consent record not found.");
}


$consent = $consent[0];

// Get the file extension (convert to lowercase for case-insensitive comparison)
$file_extension = strtolower(pathinfo($consent["file"], PATHINFO_EXTENSION));

if (array_key_exists($file_extension, INLINE_VIEWABLE_TYPES)) {
    // Set the Content-Type header for inline viewing
    header('Content-Type: ' . INLINE_VIEWABLE_TYPES[$file_extension]);
    // Set the Content-Disposition header to inline
    header('Content-Disposition: inline; filename="' . $consent["file"]  . '"');
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
    header('Content-Disposition: attachment; filename="' . $consent["file"] . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit; // Important to stop further script execution
}

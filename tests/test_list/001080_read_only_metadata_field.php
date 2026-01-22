<?php

command_line_only();

$webroot = dirname(dirname(__DIR__));
include_once "{$webroot}/include/image_processing.php";

// ExifTool can be missing which is considered OK for test purposes
if (get_utility_path("exiftool") === false) {
    echo 'ExifTool not installed - ';
    return true;
}

// Set up
$exiftool_write                = true;
$force_exiftool_write_metadata = true;
$exiftool_write_option         = true;
$exiftool_remove_existing      = true;

// Change "Credit" and "Camera make / model" fields to be read-only
ps_query("UPDATE resource_type_field SET `read_only` = 1 WHERE ref IN (10, 52)");

function teardown_002000($tmpfile)
{
    if (is_string($tmpfile) && trim($tmpfile) !== '' && file_exists($tmpfile)) {
        unlink($tmpfile);
    }

    ps_query("UPDATE resource_type_field SET `read_only` = 0 WHERE ref IN (10, 52)");
}


// Create a resource and give it an original file
$resource = create_resource(1, 0);
if ($resource === false) {
    teardown_002000(null);

    echo 'Unable to create resource - ';
    return false;
}
$resource_path = get_resource_path($resource, true, '');
copy("{$webroot}/gfx/homeanim/1.jpg", $resource_path);

// Extract embedded metadata
extract_exif_comment($resource, '');

// Change some of the metadata fields
$title = "Read-only feature testing";
update_field($resource, 8, $title);
$credit = "Unit test #002000";
update_field($resource, 10, $credit);
$camera_make = "New Camera make +";
update_field($resource, 52, $camera_make);

$tmpfile = write_metadata($resource_path, $resource);
if (false === $tmpfile || !file_exists($tmpfile)) {
    teardown_002000($tmpfile);

    echo 'No temp file - ';
    return false;
}

$specific_tags = "-IPTC:Credit -EXIF:Model -XMP:Title";
$command = get_utility_path("exiftool") . " {$specific_tags} -s -s -f -m -php " . escapeshellarg($tmpfile);
$output  = run_command($command);

try {
    $metadata_check = eval("return {$output};");
} catch (ParseError $e) {
    teardown_002000($tmpfile);

    echo "ParseError: {$e->getMessage()} - ";
    return false;
}

// @todo: remove this block once PHP 7 is supported by ResourceSpace - @see: https://www.php.net/manual/en/function.eval.php
if ($metadata_check === false) {
    teardown_002000($tmpfile);

    echo 'Metadata check failed - ';
    return false;
}

$metadata_check = $metadata_check[0];

// Credit and Camera model should be "-" since we are using "-f" option
if (
    $metadata_check['Credit'] === $credit
    && $metadata_check['Model'] === $camera_make
    && $metadata_check['Title'] === $title
) {
    teardown_002000($tmpfile);
    return false;
}

// Teardown
teardown_002000($tmpfile);

return true;

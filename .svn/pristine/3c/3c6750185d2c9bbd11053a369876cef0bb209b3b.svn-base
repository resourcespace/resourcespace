<?php

command_line_only();

include_once __DIR__ . '/../../include/image_processing.php';

// Validate that EXIF data is extracted and prioritised correctly
$exiftool_fullpath = get_utility_path("exiftool");
if (!$exiftool_fullpath) {
    echo "INFO: Exiftool not found - unable to test.\n";
    return;
}

$iptc_name = "IPTC:Keywords";
$iptc_data = "Apples";
$xmp_name = "XMP:Keywords";
$xmp_data = "Oranges";

// Create a new field with exif prioritising XMP
$exif_field = create_resource_type_field("Exif test", 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, "exiftest");
ps_query("UPDATE resource_type_field SET exiftool_field=? WHERE ref=?", array("s",$iptc_name . "," . $xmp_name,"i",$exif_field));

// Create a test resource
$exifresource = create_resource(1, 0);
$resource_path = get_resource_path($exifresource, true, '');
copy(__DIR__ . "/../../gfx/homeanim/1.jpg", $resource_path);

// Update embedded metadata in file with both values
$command = $exiftool_fullpath . " -m -overwrite_original -" . $iptc_name . "='" . $iptc_data . "' " . $resource_path;
run_command($command);
$command = $exiftool_fullpath . " -m -overwrite_original -" . $xmp_name . "='" . $xmp_data . "' " . $resource_path;
run_command($command);

// Test A. Extract data and check the XMP data is in the new field
extract_exif_comment($exifresource, "jpg");
$resdata = get_data_by_field($exifresource, $exif_field);

if ($resdata != $xmp_data) {
    echo "ERROR SUBTEST A - ";
    return false;
}

// Test B. Update embedded metadata in file with multiple values and check extracted ok to separate nodes
$xmp_data = "Apples, Oranges";
$command = $exiftool_fullpath . " -m -overwrite_original -" . $xmp_name . "='" . $xmp_data . "' " . $resource_path;
run_command($command);
extract_exif_comment($exifresource, "jpg");
$resdata = get_data_by_field($exifresource, $exif_field);
$addednodes = get_resource_nodes($exifresource, $exif_field);
if ($resdata != $xmp_data) {
    echo "ERROR SUBTEST B - ";
    return false;
}

// Test C. Check that mapping the IPTC keyword data only works
ps_query("UPDATE resource_type_field SET exiftool_field=? WHERE ref=?", array("s",$iptc_name,"i",$exif_field));
// Extract metadata again and check that the IPTC data has been discovered
extract_exif_comment($exifresource, "jpg");
$resdata = get_data_by_field($exifresource, $exif_field);
if ($resdata != $iptc_data) {
    echo "ERROR SUBTEST C - ";
    return false;
}

return true;

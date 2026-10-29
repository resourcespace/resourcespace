<?php

command_line_only();

include_once __DIR__ . '/../../include/metadata_functions.php';

// ExifTool is required by FITS
if (get_utility_path("exiftool") === false) {
    echo 'ExifTool not installed';
    return false;
}

function FITS_test($f_xml, $f_field, $f_expect)
{
    $fitsdata = getFitsMetadataFieldValue($f_xml, $f_field);
    if ($fitsdata == $f_expect) {
        return true;
    }
    echo "FAILED FITS field={$f_field} expected={$f_expect} returned={$fitsdata}" . PHP_EOL;
    return false;
}

$fitsXml = '<?xml version="1.0" encoding="UTF-8"?> <fits xmlns="http://hul.harvard.edu/ois/xml/ns/fits/fits_output" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://hul.harvard.edu/ois/xml/ns/fits/fits_output http://hul.harvard.edu/ois/xml/xsd/fits/fits_output.xsd" version="1.4.1" timestamp="9/4/19 4:09 AM">   <identification status="SINGLE_RESULT">     <identity format="JPEG EXIF" mimetype="image/jpeg" toolname="FITS" toolversion="1.4.1">       <tool toolname="NLNZ Metadata Extractor" toolversion="3.6GA" />       <version toolname="NLNZ Metadata Extractor" toolversion="3.6GA">1.1</version>     </identity>   </identification>   <fileinfo>     <size toolname="Jhove" toolversion="1.20.1">80874</size>     <filepath toolname="OIS File Information" toolversion="0.2" status="SINGLE_RESULT">c:\users\acota\downloads\351479.jpg</filepath>     <filename toolname="OIS File Information" toolversion="0.2" status="SINGLE_RESULT">351479.jpg</filename>     <md5checksum toolname="OIS File Information" toolversion="0.2" status="SINGLE_RESULT">62cca194b10f1d81f56d206103108573</md5checksum>     <fslastmodified toolname="OIS File Information" toolversion="0.2" status="SINGLE_RESULT">1567593837389</fslastmodified>   </fileinfo>   <filestatus />   <metadata>     <image>       <imageWidth toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">1008</imageWidth>       <imageHeight toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">567</imageHeight>       <YCbCrPositioning toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">1</YCbCrPositioning>       <samplingFrequencyUnit toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">in.</samplingFrequencyUnit>       <xSamplingFrequency toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">72</xSamplingFrequency>       <ySamplingFrequency toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">72</ySamplingFrequency>       <bitsPerSample toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">8 8 8</bitsPerSample>       <lightSource toolname="NLNZ Metadata Extractor" toolversion="3.6GA" status="SINGLE_RESULT">unknown</lightSource>       <standard>         <mix:mix xmlns:mix="http://www.loc.gov/mix/v20">           <mix:BasicDigitalObjectInformation />           <mix:BasicImageInformation>             <mix:BasicImageCharacteristics>               <mix:imageWidth>1008</mix:imageWidth>               <mix:imageHeight>567</mix:imageHeight>               <mix:PhotometricInterpretation />             </mix:BasicImageCharacteristics>           </mix:BasicImageInformation>           <mix:ImageCaptureMetadata>             <mix:GeneralCaptureInformation />             <mix:DigitalCameraCapture>               <mix:DigitalCameraModel />               <mix:CameraCaptureSettings>                 <mix:ImageData>                   <mix:lightSource>unknown</mix:lightSource>                 </mix:ImageData>               </mix:CameraCaptureSettings>             </mix:DigitalCameraCapture>           </mix:ImageCaptureMetadata>           <mix:ImageAssessmentMetadata>             <mix:SpatialMetrics>               <mix:samplingFrequencyUnit>in.</mix:samplingFrequencyUnit>               <mix:xSamplingFrequency>                 <mix:numerator>72</mix:numerator>                 <mix:denominator>1</mix:denominator>               </mix:xSamplingFrequency>               <mix:ySamplingFrequency>                 <mix:numerator>72</mix:numerator>                 <mix:denominator>1</mix:denominator>               </mix:ySamplingFrequency>             </mix:SpatialMetrics>             <mix:ImageColorEncoding>               <mix:BitsPerSample>                 <mix:bitsPerSampleValue>8</mix:bitsPerSampleValue>                 <mix:bitsPerSampleValue>8</mix:bitsPerSampleValue>                 <mix:bitsPerSampleValue>8</mix:bitsPerSampleValue>                 <mix:bitsPerSampleUnit>integer</mix:bitsPerSampleUnit>               </mix:BitsPerSample>             </mix:ImageColorEncoding>           </mix:ImageAssessmentMetadata>         </mix:mix>       </standard>     </image>   </metadata>   <statistics fitsExecutionTime="4063">     <tool toolname="MediaInfo" toolversion="0.7.75" status="did not run" />     <tool toolname="OIS Audio Information" toolversion="0.1" status="did not run" />     <tool toolname="ADL Tool" toolversion="0.1" status="did not run" />     <tool toolname="VTT Tool" toolversion="0.1" status="did not run" />     <tool toolname="Droid" toolversion="6.4" executionTime="1563" />     <tool toolname="Jhove" toolversion="1.20.1" executionTime="3641" />     <tool toolname="file utility" toolversion="5.03" executionTime="3969" />     <tool toolname="Exiftool" toolversion="11.14" executionTime="3906" />     <tool toolname="NLNZ Metadata Extractor" toolversion="3.6GA" executionTime="3734" />     <tool toolname="OIS File Information" toolversion="0.2" executionTime="906" />     <tool toolname="OIS XML Metadata" toolversion="0.2" status="did not run" />     <tool toolname="ffident" toolversion="0.2" executionTime="3546" />     <tool toolname="Tika" toolversion="1.19.1" executionTime="2453" />   </statistics> </fits>  ';
$xmlobj = new SimpleXMLElement($fitsXml);

if (
    FITS_test($xmlobj, 'fileinfo.size', 80874)
    &&  FITS_test($xmlobj, 'fileinfo.size/@toolname', 'Jhove')
    &&  FITS_test($xmlobj, 'identification.identity/@mimetype', 'image/jpeg')
    &&  FITS_test($xmlobj, 'fileinfo.filename', '351479.jpg')
    &&  FITS_test($xmlobj, 'metadata.image.imageWidth', 1008)
    &&  FITS_test($xmlobj, 'metadata.image.bitsPerSample/@toolversion', '3.6GA')
) {
    return true;
}

echo "FAILED FITS TEST" . PHP_EOL;
return false;

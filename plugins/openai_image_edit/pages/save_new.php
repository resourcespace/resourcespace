<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";
// Save the submitted file as an alternative file to the resource record

$ref=getval("ref",0,true);
$access=get_resource_access($ref);
$edit_access=get_edit_access($ref);

if ($access!=0 || !$edit_access || !checkperm("c"))
    {
    // They shouldn't arrive here
    exit("Access denied");
    }

// Extract the base64-encoded image data
$imageData = getval('imageData','');
// Remove the data URL scheme part (e.g., 'data:image/jpeg;base64,')
$imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
$imageData = str_replace('data:image/png;base64,', '', $imageData);
$imageData = str_replace('data:image/webp;base64,', '', $imageData);

// Replace any spaces with '+', as they may have been incorrectly encoded
$imageData = str_replace(' ', '+', $imageData);

// Decode the base64-encoded image data
$imageData = base64_decode($imageData);

$imageType = getval('imageType','');
$extension = explode("/",$imageType)[1];

// Create resource
$resource = create_resource(1, get_default_archive_state(), -1, "OpenAI", $extension);

// Save file
$path=get_resource_path($resource,true,'',true,$extension);
file_put_contents($path,$imageData);

// Create previews
create_previews($resource,false,$extension);

header('Content-Type: application/json');
echo json_encode(["status"=>"OK", "resource"=>$resource]);

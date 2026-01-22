<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";
// Save the submitted file as an alternative file to the resource record

$ref=getval("ref",0,true);
$access=get_resource_access($ref);
$edit_access=get_edit_access($ref);

if ($access!=0 || !$edit_access)
    {
    // They shouldn't arrive here
    exit("Access denied");
    }

set_processing_message($lang["openai_image_edit__saving_alternative"]);

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

$alt=add_alternative_file($ref,$lang["openai_image_edit__filename"] . " (" . $username . ", " . strtoupper($extension). ")","",$imageType,$extension,strlen($imageData));

// Save file
$path=get_resource_path($ref,true,'',true,$extension,true,1,false,'',$alt);
file_put_contents($path,$imageData);

// Create previews
set_processing_message($lang["openai_image_edit__generating_alternative_previews"]);
create_previews($ref,false,$extension,false,false,$alt);

header('Content-Type: application/json');
echo json_encode(["status"=>"OK"]);

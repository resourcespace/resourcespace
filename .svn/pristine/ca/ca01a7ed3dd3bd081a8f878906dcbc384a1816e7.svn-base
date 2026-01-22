<?php

include_once "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";

// Ensure POST request.
enforcePostRequest(false);

$save=(getval("save","0")==1);
$svg=getval("svg","");
$filename=getval("filename","");
$filetype=strtolower(getval("filetype",""));

if (in_array($filetype,$stencilvg_rsvg_supported_output_formats))
    {
    // Convert the SVG data into the requested format using librsvg
    $filename.="." . $filetype;

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
     );
     $process = proc_open(
             'rsvg-convert -d ' . $stencilvg_dpi . ' -p ' . $stencilvg_dpi . ' --format=' . escapeshellarg($filetype),
             $descriptorspec,
             $pipes
     );
     fwrite($pipes[0],$svg);fclose($pipes[0]); // Write the SVG data and close the pipe.
     $svg=stream_get_contents($pipes[1]); // Read all output data and replace the download with it.
    }

if ($save)
    {
    // Save as a new resource

    // Create the resource record
    $ref = create_resource($stencilvg_resource_type_for_new, get_default_archive_state(), $userref, $lang["stencilvg_createdfromstencilvg"]);

    // Dump the supplied SVG data to the file and process it.
    $svg_path=get_resource_path($ref,true,'',true,$filetype);
    file_put_contents($svg_path,$svg);
    upload_file($ref,true,false,false,$svg_path);
    create_previews($ref,false,"svg");
    update_field($ref,$view_title_field,$filename); // Filename as default title.

    // Send the user to the newly created resource
    redirect("pages/view.php?ref=" . $ref);
    }
else
    {
    // Downloading so just send the posted contents as a file.
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Content-Type: application/octet-stream");
    echo $svg;
    }

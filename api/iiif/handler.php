<?php

$suppress_headers = true;
$disable_browser_check = true;

include_once __DIR__ . "/../../include/boot.php";
include_once __DIR__ . "/../../include/image_processing.php";
include_once __DIR__ . "/../../include/video_functions.php";

// Some viewer pre-flight checks seem to require this to be explicitly set
header("Access-Control-Allow-Origin: *");

if (!$iiif_enabled || !isset($iiif_identifier_field) || !is_numeric($iiif_identifier_field) || !isset($iiif_userid) || !is_numeric($iiif_userid) || !isset($iiif_description_field)) {
    exit($lang["iiif_disabled"]);
}

include_once "../../include/api_functions.php";

if ($iiif_version === "2") {
    // Older version of the standard. Needed if clients don't support v3.0 - see https://iiif.io/api/presentation/3.0/change-log/
    include __DIR__ . "/handler2.php";
    exit();
}

// Set up request object
$iiif_options["rootlevel"] = $baseurl_short . "iiif/";
$iiif_options["rooturl"] = $baseurl . "/iiif/";
$iiif_options["rootimageurl"] = $baseurl . "/iiif/image/";
$iiif_options["identifier_field"] = $iiif_identifier_field;
$iiif_options["description_field"] = $iiif_description_field;
$iiif_options["sequence_field"] = $iiif_sequence_field ?? 0;
$iiif_options["license_field"] = (int) ($iiif_license_field ?? 0);
$iiif_options["title_field"] = $view_title_field;
$iiif_options["max_width"] = $iiif_max_width ?? 1024;
$iiif_options["max_height"] = $iiif_max_height ?? 1024;
$iiif_options["custom_sizes"] = (bool) $iiif_custom_sizes ?? true;
$iiif_options["preview_tiles"] = (bool) $preview_tiles ?? true;
$iiif_options["preview_tile_size"] = $preview_tile_size ?? 1024;
$iiif_options["preview_tile_scale_factors"] = $preview_tile_scale_factors ?? [1,2,4];
$iiif_options["download_chunk_size"] = $download_chunk_size;
$iiif_options["rights"] = $iiif_rights_statement ?? "";
$iiif_options["media_extensions"] = $iiif_media_extensions;
$iiif_options["only_power_of_two_sizes"] = $iiif_only_power_of_two_sizes;
if (isset($iiif_sequence_prefix)) {
    $iiif_options["iiif_sequence_prefix"] = $iiif_sequence_prefix;
}

$iiif = new IIIFRequest($iiif_options);

$iiif_user = get_user($iiif_userid);
if ($iiif_user === false) {
    $iiif->errors[] = 'Invalid \$iiif_userid.';
    $iiif->triggerError(500);
}

// Creating $userdata for use in do_search()
$userdata[0] = $iiif_user;
setup_user($iiif_user);

// Extract request details
$iiif->parseUrl($_SERVER["REQUEST_URI"] ?? "");

if ($iiif->getRequest("api") == "root") {
    # Root level request - send information file only
    $iiif->infodoc();
} elseif ($iiif->getRequest("api") == "image") {
    $iiif->processImageRequest();
} elseif ($iiif->getRequest("api") == "presentation") {
    $iiif->processPresentationRequest();
} else {
    $iiif->errorcode = 404;
    $iiif->errors[] = "Bad request. Valid options are 'manifest', 'sequence' or 'canvas' e.g. ";
    $iiif->errors[] = "For the manifest: " . $iiif->rooturl . $iiif->getRequest("id") . "/manifest";
    $iiif->errors[] = "For a sequence : " . $iiif->rooturl . $iiif->getRequest("id") . "/sequence";
    $iiif->errors[] = "For a canvas : " . $iiif->rooturl . $iiif->getRequest("id") . "/canvas/<identifier>";
}

// Send the response
if ($iiif->isValidRequest()) {
    if (function_exists("http_response_code")) {
        http_response_code(200); # Send OK
    }
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Accept");
    if ($iiif->is_image_response()) {
        $iiif->renderImage();
    } else {
        header('Content-Type: application/ld+json;profile="http://iiif.io/api/image/3/context.json"');
        foreach ($iiif->headers as $iiif_header) {
            header($iiif_header);
        }
        if (defined('JSON_PRETTY_PRINT')) {
            echo json_encode($iiif->getResponse(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode($iiif->getResponse());
        }
    }
} elseif (count($iiif->errors) > 0) {
    $iiif->triggerError();
}

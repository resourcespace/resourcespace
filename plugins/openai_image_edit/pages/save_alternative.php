<?php

use function Montala\ResourceSpace\Plugins\OpenAiImageEdit\process_encoded_file;

include "../../../include/boot.php";
include "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";
include_once '../../../include/ajax_functions.php';
include_once '../include/openai_image_edit_functions.php';
// Save the submitted file as an alternative file to the resource record

header('Content-Type: application/json');

$ref=getval("ref",0,true);
$access=get_resource_access($ref);
$edit_access=get_edit_access($ref);

if ($access != RESOURCE_ACCESS_FULL || !$edit_access)
    {
    ajax_send_response(403, ajax_response_fail(ajax_build_message(text('error-permissiondenied'))));
    }

set_processing_message($lang["openai_image_edit__saving_alternative"]);

$process_encoded_file = process_encoded_file(getval('imageData', ''), getval('imageType', ''));
if ($process_encoded_file['status'] !== 'success') {
    ajax_send_response($process_encoded_file['code'], array_diff_key($process_encoded_file, ['code' => null]));
}

$processed_tmp_file = new SplFileInfo($process_encoded_file['data']['file_path']);
$extension = $processed_tmp_file->getExtension();

$alt = add_alternative_file(
    $ref,
    $lang["openai_image_edit__filename"] . " (" . $username . ", " . strtoupper($extension). ")",
    "",
    $processed_tmp_file->getFilename(),
    $extension,
    $processed_tmp_file->getSize()
);

$process_file_upload = process_file_upload(
    $processed_tmp_file,
    new SplFileInfo(get_resource_path($ref, true, '', true, $extension, true, 1, false, '', $alt)),
    ['allow_extensions' => ['jpg', 'png', 'webp']]
);
if (!$process_file_upload['success']) {
    ajax_send_response(
        403,
        ajax_response_fail(ajax_build_message($process_file_upload['error']->i18n($GLOBALS['lang'])))
    );
}

set_processing_message($lang["openai_image_edit__generating_alternative_previews"]);
create_previews($ref,false,$extension,false,false,$alt);

ajax_send_response(200, ajax_response_ok_no_data());

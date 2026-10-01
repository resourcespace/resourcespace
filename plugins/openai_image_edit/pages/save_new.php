<?php

use function Montala\ResourceSpace\Plugins\OpenAiImageEdit\process_encoded_file;

include "../../../include/boot.php";
include "../../../include/authenticate.php";
include_once "../../../include/image_processing.php";
include_once '../../../include/ajax_functions.php';
include_once '../include/openai_image_edit_functions.php';

header('Content-Type: application/json');

$ref=getval("ref",0,true);
$access=get_resource_access($ref);
$edit_access=get_edit_access($ref);

if ($access != RESOURCE_ACCESS_FULL || !$edit_access || !checkperm("c")) {
    ajax_send_response(403, ajax_response_fail(ajax_build_message(text('error-permissiondenied'))));
}

$process_encoded_file = process_encoded_file(getval('imageData', ''), getval('imageType', ''));
if ($process_encoded_file['status'] !== 'success') {
    ajax_send_response($process_encoded_file['code'], array_diff_key($process_encoded_file, ['code' => null]));
}

$processed_tmp_file = new SplFileInfo($process_encoded_file['data']['file_path']);
$extension = $processed_tmp_file->getExtension();

$resource = create_resource(1, get_default_archive_state(), -1, "OpenAI", $extension);
if ($resource === false) {
    ajax_send_response(200, ajax_response_fail(ajax_build_message(text('error_fail_save'))));
}

$process_file_upload = process_file_upload(
    $processed_tmp_file,
    new SplFileInfo(get_resource_path($resource, true, '', true, $extension)),
    ['allow_extensions' => ['jpg', 'png', 'webp']]
);
if (!$process_file_upload['success']) {
    ajax_send_response(
        403,
        ajax_response_fail(ajax_build_message($process_file_upload['error']->i18n($GLOBALS['lang'])))
    );
}

create_previews($resource,false,$extension);

ajax_send_response(200, ajax_response_ok(['resource' => $resource]));

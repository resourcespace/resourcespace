<?php

include      __DIR__ . '/../../include/boot.php';
include      __DIR__ . '/../../include/authenticate.php';
include_once __DIR__ . '/../../include/facial_recognition_functions.php';

$return   = array();
$action   = getval('action', '');
$resource = getval('resource', 0, true);

header('Content-Type: application/json');

if (
    !$facial_recognition_active ||
    !get_edit_access($resource) ||
    !metadata_field_edit_access($facial_recognition_tag_field)
) {
    header('HTTP/1.1 401 Unauthorized');
    $return['error'] = array(
        'status' => 401,
        'title'  => 'Unauthorized',
        'detail' => $lang['error-permissiondenied']);

    echo json_encode($return);
    exit();
}

if ('prepare_selected_area' == $action) {
    $shape = getval('shape', [], false, 'is_array');

    if (
        !isset($shape['geometry']['x']) ||
        !isset($shape['geometry']['y']) ||
        !isset($shape['geometry']['width']) ||
        !isset($shape['geometry']['height'])
    ) {
        $return['error'] = array(
            'status' => 400,
            'title'  => 'Bad Request',
            'detail' => 'The shape provided was not correctly formatted!');
    }

    $image_path = get_resource_path(
        $resource,
        true,
        'pre',
        true,
        ps_value("SELECT preview_extension AS `value` FROM resource WHERE ref = ?", ['i', $resource], 'jpg')
    );
    $prepared_image_path = get_resource_path(
        $resource,
        true,
        FACIAL_RECOGNITION_CROP_SIZE_PREFIX . 'test',
        true,
        FACIAL_RECOGNITION_PREPARED_IMAGE_EXT
    );

    $return['data'] = prepareFaceImage(
        $image_path,
        $prepared_image_path,
        $shape['geometry']['x'],
        $shape['geometry']['y'],
        $shape['geometry']['width'],
        $shape['geometry']['height']
    );
}

if ('predict_label' == $action) {
    $model_file_path     = "{$facial_recognition_face_recognizer_models_location}/lbph_model.xml";
    $prepared_image_path = get_resource_path(
        $resource,
        true,
        FACIAL_RECOGNITION_CROP_SIZE_PREFIX . 'test',
        true,
        FACIAL_RECOGNITION_PREPARED_IMAGE_EXT
    );

    $prediction = faceRecognizerPredict($model_file_path, $prepared_image_path);

    if (false === $prediction && file_exists($model_file_path) && file_exists($prepared_image_path)) {
        $return['error'] = array(
            'status' => 500,
            'title'  => 'Internal Server Error',
            'detail' => 'ResourceSpace was not able to predict a label.');

        echo json_encode($return);
        exit();
    }
    // When facial recognition has never been trained, it won't have lbph model states so faceRecognizerPredict() will
    // return false because the files do not exist. Basically this should be seen as an unknown person rather than a
    // system error
    elseif (false === $prediction && (!file_exists($model_file_path) || !file_exists($prepared_image_path))) {
        $prediction = [];
        $prediction[0] = -1;
    }

    // Unknown
    if (-1 === $prediction[0]) {
        $return['data'] = array(
            'ref'                 => null,
            'resource_type_field' => $facial_recognition_tag_field,
            'name'                => $lang['unknown'],
            'parent'              => null,
            'order_by'            => null
            );

        echo json_encode($return);
        exit();
    }

    $tag = array();
    if (get_node($prediction[0], $tag)) {
        $return['data'] = $tag;

        // Remove the file since it is used only once. Once we have tagged it, the trainer will come later
        // and learn who this person is anyway, regardless of this prediction
        unlink($prepared_image_path);

        echo json_encode($return, JSON_NUMERIC_CHECK);
        exit();
    }
}

// If by this point we still don't have a response for the request,
// create one now telling client code this is a bad request
if (0 === count($return)) {
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'The request could not be handled by facial_recognition.php. This is the default response!');
}

echo json_encode($return);
exit();

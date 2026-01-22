<?php

include __DIR__ . '/../../include/boot.php';
include_once RESOURCESPACE_BASE_PATH . '/include/node_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/annotation_functions.php';
include_once RESOURCESPACE_BASE_PATH . '/include/comment_functions.php';
header('Content-Type: application/json');

if (!$annotate_enabled) {
    header('HTTP/1.1 401 Unauthorized');
    $return['error'] = array(
        'status' => 401,
        'title'  => 'Unauthorized',
        'detail' => $lang['error-permissiondenied']);

    echo json_encode($return);
    exit();
}

$resource = getval('resource', 0, false, is_positive_int_loose(...));

// Authentication (with external share access support)
$k = getval('k', '');
if ($k === '' || !check_access_key($resource, $k)) {
    include RESOURCESPACE_BASE_PATH . '/include/authenticate.php';
}

$return   = array();

$action   = getval('action', '');
$page     = getval('page', 0, true);

// Get annotation data if an ID has been provided
$annotation_id = getval('annotation_id', 0, true);
$annotation    = getval('annotation', [], false, 'is_array');

debug(sprintf('[annotations][annotations.php] AJAX request: action = %s | resource = %s | annotation_id = %s', $action, $resource, $annotation_id));

if (0 < $annotation_id) {
    $annotation = getAnnotation($annotation_id);
}

$request_ctx = ['k' => $k];

if ('get_resource_annotations' == $action) {
    $return['data'] = getAnnotoriousResourceAnnotations($resource, $page, $request_ctx);
}

// Create new annotation
if ('create' == $action && 0 < $resource) {
    debug('[annotations][annotations.php] Request to create new annotation...');
    debug('[annotations][annotations.php] annotation object is ' . json_encode($annotation));
    if (0 === count($annotation)) {
        debug('[annotations][annotations.php][error] No annotation object');
        $return['error'] = array(
            'status' => 400,
            'title'  => 'Bad Request',
            'detail' => 'ResourceSpace expects an annotation object');

        echo json_encode($return);
        exit();
    }

    $annotation_id = createAnnotation($annotation, $request_ctx);
    debug('[annotations][annotations.php] newly created annotation_id = ' . json_encode($annotation_id));

    if (false === $annotation_id) {
        debug('[annotations][annotations.php][error] No annotation_id!');
        $return['error'] = array(
            'status' => 500,
            'title'  => 'Internal Server Error',
            'detail' => 'ResourceSpace was not able to create the annotation.');

        echo json_encode($return);
        exit();
    }

    $return['data'] = $annotation_id;
}

// Update annotation
if ('update' == $action && 0 < $resource) {
    if (0 === count($annotation)) {
        $return['error'] = array(
            'status' => 400,
            'title'  => 'Bad Request',
            'detail' => 'ResourceSpace expects an annotation object');

        echo json_encode($return);
        exit();
    }

    $return['data'] = updateAnnotation($annotation, $request_ctx);
}

// Delete annotation
if ('delete' == $action && 0 < $annotation_id && 0 !== count($annotation)) {
    $delete_linked_comment = false;
    cast_echo_to_string(static function () use ($annotation, &$delete_linked_comment, $request_ctx) {
        if (!annotationEditable($annotation, $request_ctx)) {
            return;
        }

        $linked_comment = ps_query(
            'SELECT ref, resource_ref FROM `comment` WHERE annotation = ? LIMIT 1',
            ['i', $annotation['ref']]
        );

        if ($linked_comment === []) {
            return;
        }

        $_POST['ref'] = $linked_comment[0]['resource_ref'];
        $_POST['comment_to_hide'] = $linked_comment[0]['ref'];
        comments_submit();
        $delete_linked_comment = true;
    });

    $return['data'] = $delete_linked_comment ?: deleteAnnotation($annotation, $request_ctx);
}

// Get available fields (allowed) for annotations
if ('get_allowed_fields' == $action) {
    $valid_annotate_fields = canSeeAnnotationsFields();
    if (!get_edit_access($resource)) {
        $valid_annotate_fields = array_intersect($valid_annotate_fields, [0]);
    }

    foreach ($valid_annotate_fields as $annotate_field) {
        if ($annotate_field === 0) {
            $return['data'][] = [
                'ref' => $annotate_field,
                'title' => $lang['annotate_pseudo_rtf_comment_title'],
                'name' => 'pseudo-rtf-zero-comment',
                'order_by' => '',
                'type' => 0,
            ];
            continue;
        }

        $field_data = get_resource_type_field($annotate_field);
        if (!(is_array($field_data) && metadata_field_edit_access($annotate_field))) {
            continue;
        }

        $return['data'][] = [
            'ref' => $annotate_field,
            'title' => i18n_get_translated($field_data['title']),
            'name' => $field_data['name'],
            'order_by' => $field_data['order_by'],
            'type' => $field_data['type'],
        ];
    }

    if (!isset($return['data'])) {
        $return['error'] = array(
            'status' => 404,
            'title'  => 'Not Found',
            'detail' => '$annotate_fields config option does not have any fields set (i.e. it is empty)');

        echo json_encode($return);
        exit();
    }
}

// Check if this user can create new options (nodes) for a field
// REQUIRES: check if field is dynamic keyword list and user has bermission to add new fields
if ('check_allow_new_tags' == $action) {
    $resource_type_field = getval('resource_type_field', 0, true);

    if (
        $resource_type_field == 0
        || !in_array($resource_type_field, array_filter(canSeeAnnotationsFields(), metadata_field_edit_access(...)))
    ) {
        $return['data'] = false;
        echo json_encode($return);
        exit();
    }

    $field_data = get_resource_type_field($resource_type_field);

    if (FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $field_data['type'] && !checkperm("bdk{$resource_type_field}")) {
        $return['data'] = true;

        echo json_encode($return);
        exit();
    }

    $return['data'] = false;

    echo json_encode($return);
    exit();
}

// If by this point we still don't have a response for the request,
// create one now telling client code this is a bad request
if (0 === count($return)) {
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'The request could not be handled by annotations.php. This is the default response!');
}

echo json_encode($return);
exit();

<?php

command_line_only();

$web_root = dirname(__FILE__, 3);
include_once "{$web_root}/include/annotation_functions.php";
include_once "{$web_root}/include/comment_functions.php";

// Set up
$annotate_fields = [3]; # Make Country an annotateable field
$resource_ref = create_resource(1, 0);
$uk = get_node_by_name(get_nodes(3), 'United Kingdom', false);
$annotorious_annotation = [
    'resource' => $resource_ref,
    'resource_type_field' => 3,
    'page' => 0, # optional
    'tags' => [], # optional
    'shapes' => [
        [
            'type' => 'rect',
            'geometry' => [
                'x' => 0.10,
                'y' => 0.20,
                'width' => 0.50,
                'height' => 0.60,
            ],
        ]
    ],
];
$get_annotation_text = static fn (int $id): string => ps_value(
    'SELECT body AS `value` FROM `comment` WHERE annotation = ?',
    ['i', $id],
    ''
);

// Simple use
$annotation_ref = createAnnotation($annotorious_annotation, []);
if ($annotation_ref === false) {
    echo 'Unable to create annotation - ';
    return false;
}

// Create annotation with tags
$annotation = $annotorious_annotation;
$annotation['tags'] = [$uk];
$annotation_ref = createAnnotation($annotation, []);
$resource_field_data = array_column(get_resource_field_data($annotation['resource']), 'value', 'ref');
if (!($annotation_ref > 0 && $resource_field_data[$annotation['resource_type_field']] === $uk['name'])) {
    echo 'Annotation w/ tags - ';
    return false;
}

// Create text annotation
$annotation = array_merge($annotorious_annotation, ['resource_type_field' => 0, 'text' => 'Lorem ipsum']);
$annotation_ref = createAnnotation($annotation, []);
if ($annotation_ref > 0 && $get_annotation_text($annotation_ref) !== $annotation['text']) {
    echo 'Text annotation - ';
    return false;
}

// Text and bound field annotations are mutually exclusive
$annotation = array_merge(
    $annotorious_annotation,
    [
        'resource' => create_resource(1, 0),
        'text' => 'Lorem ipsum',
        'tags' => [$uk],
    ]
);
$annotation_ref = createAnnotation($annotation, []);
$resource_field_data = array_column(get_resource_field_data($annotation['resource']), 'value', 'ref');
if (
    $annotation_ref > 0
    && (
        $get_annotation_text($annotation_ref) === $annotation['text']
        || $resource_field_data[$annotation['resource_type_field']] !== $uk['name']
    )
) {
    echo 'Text and bound field annotations are mutually exclusive - ';
    return false;
}

// Tear down
unset(
    $annotate_fields,
    $resource_ref,
    $uk,
    $annotorious_annotation,
    $get_annotation_text,
    $annotation,
    $annotation_ref,
    $resource_field_data
);

return true;

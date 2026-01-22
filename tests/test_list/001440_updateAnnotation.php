<?php

command_line_only();

$web_root = dirname(__FILE__, 3);
include_once "{$web_root}/include/annotation_functions.php";
include_once "{$web_root}/include/comment_functions.php";

// Set up
$rtf_tags = create_resource_type_field('test_010402', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
$annotate_fields = [$rtf_tags];

$node_optionA = $node_optionB = [];
get_node(set_node(null, $rtf_tags, 'Option A', null, ''), $node_optionA);
get_node(set_node(null, $rtf_tags, 'Option B', null, ''), $node_optionB);

$resource_ref = create_resource(1, 0);

$annotorious_annotation = [
    'resource' => $resource_ref,
    'resource_type_field' => $rtf_tags,
    'page' => 0,
    'tags' => [$node_optionA],
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
$annotation_ref = createAnnotation($annotorious_annotation, []);

$annotation = $annotorious_annotation;
$annotation['tags'] = [$node_optionB];

// Update tags but missing its ID
if (updateAnnotation($annotation, []) !== false) {
    echo 'updateAnnotation (no ref) - ';
    return false;
}

// Update tags for an existing annotation
$annotation['ref'] = $annotation_ref;
if (updateAnnotation($annotation, []) === false) {
    echo 'updateAnnotation - ';
    return false;
}

// Update a text (comment) annotation
$annotorious_text_annotation = array_merge(
    $annotorious_annotation,
    [
        'resource_type_field' => 0,
        'tags' => [],
        'text' => 'Init value',
    ]
);
$annotorious_text_annotation['ref'] = createAnnotation($annotorious_text_annotation, []);
$annotorious_text_annotation['text'] = 'Updated value';
if (updateAnnotation($annotorious_text_annotation, [])) {
    echo "Use case: Admins (can manage content) shoudn't update text annotations - ";
    return false;
}

// Tear down
unset($rtf_tags, $node_optionA, $node_optionB, $resource_ref, $annotorious_annotation, $annotation);

return true;

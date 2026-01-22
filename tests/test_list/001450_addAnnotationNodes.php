<?php

command_line_only();


include_once dirname(__FILE__, 3) . '/include/annotation_functions.php';

// Set up
$rtf_tags = create_resource_type_field('test_010403', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
$annotate_fields = [$rtf_tags];

$node_optionA = $node_optionB = [];
get_node(set_node(null, $rtf_tags, 'Option - A', null, ''), $node_optionA);
get_node(set_node(null, $rtf_tags, 'Option - B', null, ''), $node_optionB);

$resource_ref = create_resource(1, 0);

$annotation = [
    'resource' => $resource_ref,
    'resource_type_field' => $rtf_tags,
    'page' => 0,
    'tags' => [],
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
$annotation_ref = createAnnotation($annotation, []);
$annotation['ref'] = $annotation_ref;



// No nodes to add
if (addAnnotationNodes($annotation_ref, []) !== false) {
    echo 'No nodes to add - ';
    return false;
}

// Add nodes to annotation
if (
    addAnnotationNodes($annotation_ref, [$node_optionA, $node_optionB]) !== false
    && array_column(getAnnotationTags([$annotation][0]), 'ref') !== [$node_optionA['ref'], $node_optionB['ref']]
) {
    echo 'Adding nodes to annotation - ';
    return false;
}



// Tear down
unset($rtf_tags, $annotate_fields, $node_optionA, $node_optionB, $resource_ref, $annotation, $annotation_ref);

return true;

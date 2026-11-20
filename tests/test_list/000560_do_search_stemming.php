<?php

command_line_only();


// Check stemming search e.g.  'fox' should return 'foxes', 'dance' should return 'dancing'
$stemming = true;

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);

// Add new nodes to field, doesn't matter that it is the country field
$foxesnode = set_node(null, 3, "foxes", '', 1000);
$dancingnode = set_node(null, 3, "dancing", '', 1000);

// Get baseline to compare in case pre-existing data changes
$pre_search_fox = do_search('fox');
$foxcount = is_array($pre_search_fox) ? count($pre_search_fox) : 0;
$pre_search_dance = do_search('dance');
$dancecount = is_array($pre_search_dance) ? count($pre_search_dance) : 0;

// Add foxesnode node to resource a
add_resource_nodes($resourcea, array($foxesnode));
// Add dancingnode node to resource b
add_resource_nodes($resourceb, array($dancingnode));

//SUBTEST A
// Do search for 'fox' (should return resource a)
$results = do_search('fox');
if (count($results) != $foxcount + 1 || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}


//SUBTEST B
// Do search for 'dance' (should return resource b)
$results = do_search('dance');
if (count($results) != $dancecount + 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

$subject_resource = create_resource(1, 0);
$caption_resource = create_resource(1, 0);
$uppercase_node = set_node(null, 73, 'Stemming test option', '', 1000);
try {
    // The shipped Animal option has only the original keyword in the index.
    add_resource_nodes($subject_resource, [255]);
    update_field($caption_resource, 18, 'Animal');

    $fixtures = [$subject_resource, $caption_resource];
    foreach ([
        'animal' => $fixtures,
        'Animal' => $fixtures,
        'ANIMAL' => $fixtures,
        'Animal;missingstemmingtestword' => $fixtures,
        'caption:Animal' => [$caption_resource],
        '-Animal' => [],
    ] as $query => $expected) {
        $results = do_search($query);
        if (!is_array($results) || !match_values(array_intersect($fixtures, array_column($results, 'ref')), $expected)) {
            echo "Unexpected stemming search results for {$query}\n";
            return false;
        }
    }

    // Whole-field and verbatim indexing can pass an uppercase keyword directly.
    add_node_keyword($uppercase_node, 'DANCING', 0);
    $indexed_keywords = ps_array(
        'SELECT k.keyword AS value FROM node_keyword nk JOIN keyword k ON k.ref = nk.keyword WHERE nk.node = ?',
        ['i', $uppercase_node]
    );
    if (array_diff(['danc', 'dancing'], $indexed_keywords) !== []) {
        echo "Expected uppercase indexing to add both the stem and the original keyword\n";
        return false;
    }
} finally {
    delete_resource($subject_resource);
    delete_resource($caption_resource);
    delete_node($uppercase_node);
    $stemming = false;
}

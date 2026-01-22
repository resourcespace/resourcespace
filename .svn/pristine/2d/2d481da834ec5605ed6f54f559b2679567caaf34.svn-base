<?php

/**
* Tests for save_collection()
*
*/

command_line_only();



// Test ref not a number
if (save_collection('notnumeric', ['name' => 'Name of a non-numeric collection']) !== false) {
    echo 'UC: Non-numeric ref - ';
    return false;
}

// Test relating all resources in collection
$col_rr = create_collection($userref, 'Test 1403 - Private collection - relating resources');
$rr_a = create_resource(1, 0);
$rr_b = create_resource(1, 0);
add_resource_to_collection($rr_a, $col_rr);
add_resource_to_collection($rr_b, $col_rr);
save_collection($col_rr, ['relateall' => 1]);
$return = do_search("!related{$rr_a}");
$return = array_column($return, 'ref');
if (!in_array($rr_b, $return)) {
    echo 'UC: Relate all resources in collection - ';
    return false;
}

// Test removing all resources from collection.
save_collection($col_rr, ['removeall' => 1]);
if (count(get_collection_resources($col_rr)) !== 0) {
    echo 'UC: Remove all resources from collection - ';
    return false;
}

// Test deleting all resources in a collection
// Functionality has been moved out of save_collection(). Test updated to use new approach.
add_resource_to_collection($rr_a, $col_rr);
add_resource_to_collection($rr_b, $col_rr);
delete_resources_in_collection($col_rr);
$return = do_search('', '', 'relevance', "{$resource_deletion_state}", -1, 'desc', false, 0, false, false, '', false, true, true);
$return = array_column($return, 'ref');
if (array_intersect([$rr_a, $rr_b], $return) !== [$rr_a, $rr_b]) {
    echo 'UC: Delete all resources from collection - ';
    return false;
}


// Test setting a collection to be public
$pp_col = create_collection($userref, 'Test 1403 - Private -> Public collection');
save_collection($pp_col, ['public' => 1]);
$return = search_public_collections();
$return = array_column($return, 'ref');
if (!in_array($pp_col, $return)) {
    echo 'UC: Set collection to be public - ';
    return false;
}

// Test changing the type of a collection (see definitions for more info)
$pp_col = create_collection($userref, 'Test 1403 - Private -> Public collection using type');
save_collection($pp_col, ['type' => COLLECTION_TYPE_PUBLIC]);
$col_data = get_collection($pp_col);
if (COLLECTION_TYPE_PUBLIC != $col_data['type']) {
    echo 'UC: Set collection type - ';
    return false;
}

// Test updating multiple collection columns
$col = create_collection($userref, 'Test 1403 - before update multiple columns');
$new_col_data = [
    'name' => 'Test 1403 - after update multiple columns',
    'created' => '2021-03-17 19:30:00',
    'allow_changes' => 1,
    'cant_delete' => 1,
    'keywords' => 'testkeyword',
    'home_page_publish' => 1,
    'home_page_text' => 0,
    'home_page_image' => 0,
    'session_id' => '123',
    'description' => 'Test 1403 - description',
];
save_collection($col, $new_col_data);
$col_data = get_collection($col);
foreach ($new_col_data as $column => $new_val) {
    if ($col_data[$column] != $new_val) {
        echo "UC: Update multiple columns -- column {$column} - ";
        return false;
    }
}

// Test featured collection changes
$fc_1  = create_collection($userref, 'Test 1403 - FC 1');
$fc_11 = create_collection($userref, 'Test 1403 - FC 1.1');
$fc_bg = create_resource(1, 0);
add_resource_to_collection($fc_bg, $fc_11);
$fc_data = [
    'featured_collections_changes' => [
        'update_parent' => $fc_1,
        'thumbnail_selection_method' => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS['manual'],
    ],
    'bg_img_resource_ref' => $fc_bg,
];
save_collection($fc_1, ['type' => COLLECTION_TYPE_FEATURED]);
save_collection($fc_11, $fc_data);
$fcs = get_featured_collections($fc_1, []);
if (empty($fcs)) {
    echo 'UC: Set featured collection, get_featured_collections() returned empty array - ';
    return false;
} elseif (
    !(
    $fcs[0]['type'] == COLLECTION_TYPE_FEATURED
    && $fcs[0]['parent'] == $fc_data['featured_collections_changes']['update_parent']
    && $fcs[0]['thumbnail_selection_method'] == $fc_data['featured_collections_changes']['thumbnail_selection_method']
    && $fcs[0]['bg_img_resource_ref'] == $fc_data['bg_img_resource_ref']
    )
) {
    echo 'UC: Set featured collection - ';
    return false;
}


// Tear down
unset($col_rr, $pp_col, $col, $new_col_data);
unset($rr_a, $rr_b, $return, $col_data);
unset($fc_1, $fc_11, $fc_bg, $fc_data, $fcs);

return true;

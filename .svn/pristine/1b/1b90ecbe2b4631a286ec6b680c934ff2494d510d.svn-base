<?php

command_line_only();


// Create the Featured collections tree
$fc_cat_1 = create_collection($userref, "FC 1");
save_collection($fc_cat_1, array("featured_collections_changes" => array("update_parent" => 0, "force_featured_collection_type" => true)));
$fc_cat_1_1 = create_collection($userref, "FC 1 / 1");
save_collection($fc_cat_1_1, array("featured_collections_changes" => array("update_parent" => 0, "force_featured_collection_type" => true)));

// Check FC tree has been created
$lvl_1 = get_featured_collection_categories(0, array("access_control" => false));
$lvl_2 = get_featured_collection_categories($fc_cat_1, array("access_control" => false));
$fc_tree = array_merge($lvl_1, $lvl_2);
$fc_tree = array_column($fc_tree, "ref");
if (!empty(array_diff(array($fc_cat_1, $fc_cat_1_1), $fc_tree))) {
    echo "Create Featured Collection Categories - ";
    return false;
}

$public_col = create_collection($userref, "Test 1400 - Public collection", 0, 0, 0, true);
$find_public_col = search_public_collections("Test 1400", "name", "ASC", true, false);
$found_public_col = array_column($find_public_col, "ref");
if (empty($found_public_col)) {
    echo "Create Public Collection - ";
    return false;
}

return true;

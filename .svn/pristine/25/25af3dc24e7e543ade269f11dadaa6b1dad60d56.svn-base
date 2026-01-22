<?php

command_line_only();


// Defaults arguments which we don't have to test
$order_by = 'name';
$sort = 'ASC';
$include_resources = false;
$fetchrows = -1;

// Save global scope vars that will change during this test
$original_public_collections_confine_group = $public_collections_confine_group;


// --- Setup
// Create users in other user groups
$general_user = new_user('test_002501_general', 2) ?: get_user_by_username('test_002501_general');
$super_admin_user = new_user('test_002501_super_admin', 3) ?: get_user_by_username('test_002501_super_admin');
if ($general_user === false && $super_admin_user === false) {
    echo 'Test setup (new_user function) - ';
    return false;
}

/* Create a simple featured collections tree:
Level A
    -> Sub-level A.1
        -> FC A (at level A/A.1)
Level B
    -> Sub-level B.1
        -> FC B
*/
$fc_cat_lvl_a = create_collection(0, 'Level A');
save_collection($fc_cat_lvl_a, ['featured_collections_changes' => ['update_parent' => 0, 'force_featured_collection_type' => true]]);
$fc_cat_lvl_a1 = create_collection(0, 'Sub-level A.1');
save_collection($fc_cat_lvl_a1, ['featured_collections_changes' => ['update_parent' => $fc_cat_lvl_a, 'force_featured_collection_type' => true]]);
$fc_cat_lvl_b = create_collection(0, 'Level B');
save_collection($fc_cat_lvl_b, ['featured_collections_changes' => ['update_parent' => 0, 'force_featured_collection_type' => true]]);
$fc_cat_lvl_b1 = create_collection(0, 'Sub-level B.1');
save_collection($fc_cat_lvl_b1, ['featured_collections_changes' => ['update_parent' => $fc_cat_lvl_b, 'force_featured_collection_type' => true]]);

$fc_a = create_collection($general_user, 'FC A (at level A/A.1)', 0, 0, 0, true);
save_collection($fc_a, ['featured_collections_changes' => ['update_parent' => $fc_cat_lvl_a1, 'force_featured_collection_type' => true]]);
$fc_b = create_collection($super_admin_user, 'FC B', 0, 0, 0, true);
save_collection($fc_b, ['featured_collections_changes' => ['update_parent' => $fc_cat_lvl_b1, 'force_featured_collection_type' => true]]);

$public_col = create_collection($userref, 'User public collection (at user level)', 1, 0, 0, true);
$private_col = create_collection($userref, 'Private collection');

$public_col_genuser = create_collection($general_user, 'General User public collection', 1, 0, 0, true);
$private_col_genuser = create_collection($general_user, 'General User Private collection');

// Create some resources and give them a title
$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);
update_field($resource_a, 'title', 'test_002501_A');
update_field($resource_b, 'title', 'test_002501_B');
// --- End of setup


// Search - excluding FCs and include public collections
// Cache should be reset before testing
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('level', $order_by, $sort, true, false);
$found_col_refs = array_column($spc_result, 'ref');
if (!in_array($public_col, $found_col_refs)) {
    echo 'Search in public collections only - ';
    return false;
}


// Search - including FCs and excluding public ones
add_resource_to_collection($resource_a, $fc_a);
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);

$spc_result = search_public_collections('level', $order_by, $sort, false, true);
$found_col_refs = array_column($spc_result, 'ref');
if (!in_array($fc_a, $found_col_refs)) {
    echo 'Search in featured collections only - ';
    return false;
}

// Search in both featured and public collections
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('level', $order_by, $sort, false, false);
$found_col_refs = array_column($spc_result, 'ref');
$found_expected_cols = array_filter($found_col_refs, function ($ref) use ($fc_a, $public_col) {
    return in_array($ref, [$fc_a, $public_col]);
});
if (empty($found_expected_cols)) {
    echo 'Search both featured & public collections - ';
    return false;
}


// Search excluding both featured and public collections => this is essentially a search public collections (ie function 
// was called incorrectly - both featured and public collections are "public".)
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('level', $order_by, $sort, true, true);
$found_col_refs = array_column($spc_result, 'ref');
if (!in_array($public_col, $found_col_refs)) {
    echo 'Search excluding featured & public collections - ';
    return false;
}


// Search showing resource count
add_resource_to_collection($resource_a, $public_col);
add_resource_to_collection($resource_b, $public_col);
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('level', $order_by, $sort, false, false, true);
$found_col_refs = array_column($spc_result, 'count', 'ref');
if (!($found_col_refs[$fc_a] == 1 && $found_col_refs[$public_col] == 2)) {
    echo 'Show resource count - ';
    return false;
}


// Search for collections confined to the user group (parent, child, sibling)
$public_collections_confine_group = true;
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('', $order_by, $sort, false, false);
$found_col_refs = array_flip(array_column($spc_result, 'ref'));
if (
    !(
        // Expecting Super Admins collections
        isset($found_col_refs[$fc_b], $found_col_refs[$public_col])
        // The general user collection shouldn't be returned
        && !isset($found_col_refs[$fc_a])
    )
) {
    echo 'Search confined by group (Super Admin) - ';
    return false;
}


// Override group confinement
$public_collections_confine_group = false;
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result_no_confinment = search_public_collections('', $order_by, $sort, false, false);
$found_col_refs_no_confinment = array_column($spc_result_no_confinment, 'ref');
$public_collections_confine_group = true;
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result_override_group_restrict = search_public_collections('', $order_by, $sort, false, false, $include_resources, true);
$found_col_refs_override_group_restrict = array_column($spc_result_override_group_restrict, 'ref');
if ($found_col_refs_no_confinment != $found_col_refs_override_group_restrict) {
    echo 'Override group confinment - ';
    return false;
}
$public_collections_confine_group = false;
unset($spc_result_no_confinment, $found_col_refs_no_confinment, $spc_result_override_group_restrict, $found_col_refs_override_group_restrict);


// Search for public collections or collections belonging to the user
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('', $order_by, $sort, true, false, $include_resources, false);
foreach ($spc_result as $spc) {
    if (!($spc['type'] == COLLECTION_TYPE_PUBLIC || $spc['user'] == $userref)) {
        echo 'Search user collections - ';
        return false;
    }
}


// Search for public collections using the "collectiontitle:" special search
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('collectiontitle:user level', $order_by, $sort, true, false, $include_resources, false);
if (!in_array($public_col, array_column($spc_result, 'ref'))) {
    echo 'Search public collections with "collectiontitle:" - ';
    return false;
}

// Search for public collections using the "collectiontitle:" special search and wildcards
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('collectiontitle:*ser * collection*', $order_by, $sort, true, false, $include_resources, false);
$found_col_refs = array_intersect(array_column($spc_result, 'ref'), [$public_col, $public_col_genuser]);
sort($found_col_refs, SORT_NUMERIC);
if ([$public_col, $public_col_genuser] !== $found_col_refs) {
    echo 'Search public collections with "collectiontitle:" and wildcards - ';
    return false;
}

// Search for public collections specifying date
// Set creation date of $fc_b
$public_collections_confine_group = false;
save_collection($fc_b, ["created" => "2021-09-01","name" => "September 2021 Award Ceremony"]);
unset($CACHE_FC_ACCESS_CONTROL, $CACHE_FC_PERMS_FILTER_SQL);
$spc_result = search_public_collections('collectiontitle:award basicyear:2021', "name", "ASC", false, false);
$found_col_refs = array_column($spc_result, 'ref');

if (
    in_array($fc_a, $found_col_refs)
    ||
    in_array($public_col, $found_col_refs)
    ||
    !in_array($fc_b, $found_col_refs)
) {
    echo 'Simple public collections text search with date - ';
    return false;
}

// Tear down
$public_collections_confine_group = $original_public_collections_confine_group;

unset(
    // Setup specific vars
    $order_by,
    $sort,
    $include_resources,
    $fetchrows,
    $general_user,
    $super_admin_user,
    $original_public_collections_confine_group,
    // Collections and resources used in this test
    $fc_cat_lvl_a,
    $fc_cat_lvl_a1,
    $fc_cat_lvl_b,
    $fc_cat_lvl_b1,
    $public_col,
    $private_col,
    $resource_a,
    $resource_b,
    // Use case vars
    $spc_result,
    $found_col_refs,
    $found_expected_cols,
    // Cache vars
    $CACHE_FC_ACCESS_CONTROL,
    $CACHE_FC_PERMS_FILTER_SQL
);

return true;

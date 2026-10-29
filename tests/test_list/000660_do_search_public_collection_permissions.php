<?php

command_line_only();

// Test to ensure that J permission blocks access to resources that are outside public collections that the user has access to.

$clear_relevant_caches = function () {
    global $CACHE_FC_ACCESS_CONTROL, $CACHE_FC_CATEG_SUB_FCS, $CACHE_FC_PERMS_FILTER_SQL, $CACHE_FCS_BY_ROOT, $CACHE_FC_RESOURCES;

    $CACHE_FC_ACCESS_CONTROL = null;
    $CACHE_FC_PERMS_FILTER_SQL = null;
    $CACHE_FC_CATEG_SUB_FCS = null;
    $CACHE_FCS_BY_ROOT = null;
    $CACHE_FC_RESOURCES = null;

    clear_query_cache("featured_collections");
};

$saved_userref = $userref;
$userref = 999;
$savedpermissions = $userpermissions;
$clear_relevant_caches();


// Create 5 new resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);
$resourced = create_resource(1, 0);
$resourcee = create_resource(1, 0);

// Add text to free text to fields
update_field($resourcea, 'title', 'test_000985_A');
update_field($resourceb, 'title', 'test_000985_B');
update_field($resourcec, 'title', 'test_000985_C');
update_field($resourced, 'title', 'test_000985_D');
update_field($resourcee, 'title', 'test_000985_E');

// Set dummy nodes
$test985field = create_resource_type_field("Test 985", 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, "testnineeightfive", 1);
$dummynode = set_node(null, $test985field, 'test000985', '', 1000);
add_resource_nodes($resourcea, array($dummynode));
add_resource_nodes($resourceb, array($dummynode));
add_resource_nodes($resourcec, array($dummynode));
add_resource_nodes($resourced, array($dummynode));
add_resource_nodes($resourcee, array($dummynode));

// Create the Featured collections tree
$fc_cat_mountains = create_collection(1, "Mountains");
save_collection($fc_cat_mountains, array("featured_collections_changes" => array("update_parent" => 0, "force_featured_collection_type" => true)));
$fc_cat_cuillin = create_collection(1, "Cuillin");
save_collection($fc_cat_cuillin, array("featured_collections_changes" => array("update_parent" => $fc_cat_mountains,"force_featured_collection_type" => true)));
$fc_cat_seasons = create_collection(1, "Seasons");
save_collection($fc_cat_seasons, array("featured_collections_changes" => array("update_parent" => 0,"force_featured_collection_type" => true)));
$fc_cat_winter = create_collection(1, "Winter");
save_collection($fc_cat_winter, array("featured_collections_changes" => array("update_parent" => $fc_cat_seasons,"force_featured_collection_type" => true)));
$fc_cat_spring = create_collection(1, "Spring");
save_collection($fc_cat_spring, array("featured_collections_changes" => array("update_parent" => $fc_cat_seasons,"force_featured_collection_type" => true)));

// Create public collections
$mountains = create_collection(1, 'Mountains', 0, 0, 0, true/*,array('Mountains')*/);
save_collection($mountains, array("featured_collections_changes" => array("update_parent" => $fc_cat_mountains,"force_featured_collection_type" => true)));
$cuillin   = create_collection(1, 'Cuillin', 0, 0, 0, true/*,array('Mountains','Cuillin')*/);
save_collection($cuillin, array("featured_collections_changes" => array("update_parent" => $fc_cat_cuillin,"force_featured_collection_type" => true)));
$winter    = create_collection(1, 'Winter', 0, 0, 0, true/*,array('Seasons','Winter')*/);
save_collection($winter, array("featured_collections_changes" => array("update_parent" => $fc_cat_winter,"force_featured_collection_type" => true)));
$spring    = create_collection(1, 'Spring', 0, 0, 0, true/*,array('Seasons','Spring')*/);
save_collection($spring, array("featured_collections_changes" => array("update_parent" => $fc_cat_spring,"force_featured_collection_type" => true)));

// Add resources to public collections
// Resource A in Mountains
add_resource_to_collection($resourcea, $mountains);
// Resource B in Mountains|Cuillin
add_resource_to_collection($resourceb, $cuillin);
// Resource C in Seasons|Winter
add_resource_to_collection($resourcec, $winter);
// Resource D in Seasons|Spring
add_resource_to_collection($resourced, $spring);
// Resource E in no themes

// SUBTEST A
// ----- Access to all themes and access to resources not in themes -----
// All resources should be shown
$userpermissions = array('f*','s','j*');
$clear_relevant_caches();
$results = do_search('test000985');

if (
    !is_array($results)
    || count($results) != 5
    || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourced,$resourcee))
) {
    echo "ERROR - SUBTEST A\n";
    echo "Expected Results: Resources({$resourcea},{$resourceb},{$resourcec},{$resourced},{$resourcee})\n";
    echo "Results returned: " . print_r($results, true) . "\n";
    return false;
}

// END SUBTEST A

// SUBTEST B
// ----- Access to all themes and no access to resources not in themes -----
// Resources a,b,c,d should be shown
$userpermissions = array('f*','s','j*','J');
$clear_relevant_caches();
$results = do_search('test000985');

if (
    !is_array($results)
    || count($results) != 4
    || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb,$resourcec,$resourced))
) {
    echo "ERROR - SUBTEST B\n";
    echo "Expected Results: Resources({$resourcea},{$resourceb},{$resourcec},{$resourced})\n";
    echo "Results returned: " . print_r($results, true) . "\n";
    return false;
}

// END SUBTEST B

// SUBTEST C
// ----- Access to Mountains themes and no access to resources not in themes -----
// Resource a,b should be shown
$userpermissions = array('f*','s', "j{$fc_cat_mountains}",'J');
$clear_relevant_caches();
global $CACHE_FC_ACCESS_CONTROL;
unset($CACHE_FC_ACCESS_CONTROL);
$results = do_search('test000985');

if (
    !is_array($results)
    || count($results) != 2
    || !match_values(array_column($results, 'ref'), array($resourcea,$resourceb))
) {
    echo "ERROR - SUBTEST C\n";
    echo "Expected Results: Resources({$resourcea},{$resourceb})\n";
    echo "Results returned: " . print_r($results, true) . "\n";
    return false;
}

// END SUBTEST C

// SUBTEST D
// ----- Access to Mountains but not Cuillin subtheme and no access to resources not in themes -----
// Resource a should be shown
$userpermissions = array('f*','s', "j{$fc_cat_mountains}", "-j{$fc_cat_cuillin}"/*,'jMountains','j-Mountains|Cuillin'*/,'J');
$clear_relevant_caches();
$results = do_search('test000985');

if (
    !is_array($results)
    || count($results) != 1
    || !match_values(array_column($results, 'ref'), array($resourcea))
) {
    echo "ERROR - SUBTEST D\n";
    echo "Expected Results: Resources({$resourcea})\n";
    echo "Results returned: " . print_r($results, true) . "\n";
    return false;
}

// END SUBTEST D

//Teardown
$userref = $saved_userref;
$userpermissions = $savedpermissions;
$clear_relevant_caches();
unset($clear_relevant_caches);

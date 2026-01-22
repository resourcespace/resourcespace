<?php

command_line_only();

// This is a test for the rse_version plugin, now enabled in config.default.php so no point being in the plugin subfolder
$testid = "test_1125_" . uniqid();

// Add a new node field
$revert_field = create_resource_type_field($testid . "_tree", 1, FIELD_TYPE_CATEGORY_TREE, $testid, false);

// Add nodes
$parent_node_id_1 = set_node(null, $revert_field, '~en:Fruit~fr:Frut', null, null);
$parent_node_id_2 = set_node(null, $revert_field, '~en:Vegetable~fr:Légume', null, null);
$parent_node_id_3 = set_node(null, $revert_field, '~en:Meat~fr:Viande', null, null);
$child_node_id_11 = set_node(null, $revert_field, '~en:Apple~fr:Pomme', $parent_node_id_1, null);
$child_node_id_12 = set_node(null, $revert_field, '~en:Orange~fr:Orange', $parent_node_id_1, null);
$child_node_id_13 = set_node(null, $revert_field, '~en:Cauliflower~fr:Choufleur', $parent_node_id_2, null);
$child_node_id_31 = set_node(null, $revert_field, '~en:Courgette~fr:Zucchini', $parent_node_id_2, null);
$child_node_id_32 = set_node(null, $revert_field, '~en:Ham~fr:Jambon', $parent_node_id_3, null);
$child_node_id_33 = set_node(null, $revert_field, '~en:Pork~fr:Porc', $parent_node_id_3, null);

$resourcea = create_resource(1, 0);
// Add nodes to resource
add_resource_nodes($resourcea, array($child_node_id_11, $parent_node_id_1), false, true);

// Test update
update_field($resourcea, $revert_field, "Vegetable/Courgette");

// Simulate a POST update
unset($_POST);
$_POST['ref'] = $resourcea;
$_POST['nodes'][$revert_field][] = $child_node_id_12;
$_POST['nodes'][$revert_field][] = $parent_node_id_1;
$_POST['submit'] = "true";
save_resource_data($resourcea, false, $revert_field);

// Subtest A  - Check log
$log = get_resource_log($resourcea, 1, ["r.type" => "e"]);
$lastlog = $log["data"][0];
if (
    $lastlog["diff"] != "- ~en:Vegetable~fr:Légume
- ~en:Vegetable~fr:Légume/~en:Courgette~fr:Zucchini
+ ~en:Fruit~fr:Frut
+ ~en:Fruit~fr:Frut/~en:Orange~fr:Orange"
) {
    echo "SUBTEST A";
    return false;
}

// Subtest B - Simulate revert to date/time functionality
$revert_time = ps_value("select now() value", array(), "");
sleep(2);
// Update value
update_field($resourcea, $revert_field, "~en:Apple~fr:Pomme");

// Perform revert
$colrevert = create_collection($userref, "Test 00125 revert");
add_resource_to_collection($resourcea, $colrevert);
$postvals = [];
$postvals['editthis_field_' . $revert_field] = "true";
$postvals["revert_" . $revert_field] = $revert_time;
$postvals["modeselect_" . $revert_field] = "Revert";
$save_errors = save_resource_data_multi($colrevert, [], $postvals);

$revertedval = get_data_by_field($resourcea, $revert_field);
if ($revertedval != "Fruit/Orange") {
    echo "SUBTEST B";
    return false;
}


// Check log is accurate

return true;

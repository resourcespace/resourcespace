<?php

command_line_only();

// --- Set up
$rtf_date = create_resource_type_field("Test #250 date", 1, FIELD_TYPE_DATE, 'test_250_date', false);
$resourcea = create_resource(1, 0);

// Create a standard text field
$field_title = 'Text field ABC';
$text_field_abc = create_resource_type_field($field_title, 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'textfieldabc', false);
$set_value = "Cheddar";
$update_errors = [];
update_field($resourcea, $text_field_abc, $set_value, $update_errors);

// SUBTEST A - Check value is saved
$test_value = get_data_by_field($resourcea, $text_field_abc);
if ($set_value != $test_value) {
    echo "SUBTEST A";
    return false;
}

// SUBTEST B - Check action has been logged correctly
$reslog = get_resource_log($resourcea)['data'];
$logok = false;
foreach ($reslog as $logentry) {
    if ($logentry["type"] == LOG_CODE_EDITED && $logentry["title"] == $field_title && $logentry["diff"] == "+ " . $set_value) {
        $logok = true;
    }
}
if (!$logok) {
    echo "SUBTEST B";
    return false;
}

// Create a category tree field and add some nodes using text values
$field_title = 'Tree field ABC';
$tree_field_abc = create_resource_type_field($field_title, 1, FIELD_TYPE_CATEGORY_TREE, 'treefieldabc', false);

$fruitnode = set_node(null, $tree_field_abc, "Fruit", 0, 10);
$applenode = set_node(null, $tree_field_abc, "Apple", $fruitnode, 20);
$pearnode = set_node(null, $tree_field_abc, "Pear", $fruitnode, 30);
$vegnode = set_node(null, $tree_field_abc, "Vegetable", 0, 40);
$broccnode = set_node(null, $tree_field_abc, "Broccolli", $vegnode, 50);
$carrotnode = set_node(null, $tree_field_abc, "Carrot", $vegnode, 60);

$update_errors = [];
$set_values = ["Apple","Fruit"];
$log_value = "+ Fruit/Apple\n+ Fruit"; // Changed for 10.1 as full paths to nodes are now logged to handle duplicate node names
$set_value = implode(NODE_NAME_STRING_SEPARATOR, $set_values);
$check_value = "Fruit/Apple";

update_field($resourcea, $tree_field_abc, $set_value, $update_errors);

// SUBTEST C - Check value is saved
$resnodes = get_resource_nodes($resourcea, $tree_field_abc, true);
$test_value = get_node_strings($resnodes);
if (!in_array($check_value, $test_value)) {
    echo "SUBTEST C";
    return false;
}

// SUBTEST D - Check action has been logged correctly
$reslog = get_resource_log($resourcea)['data'];
$logok = false;
foreach ($reslog as $logentry) {
    if ($logentry["type"] == LOG_CODE_EDITED && $logentry["title"] == $field_title && $logentry["diff"] == $log_value) {
        $logok = true;
    }
}

if (!$logok) {
    echo "SUBTEST D";
    return false;
}

// Use the previous text field as the title field and update the title
$newtitle = "This is a test title";
$saved_view_title_field = $view_title_field;
$view_title_field = $text_field_abc;
check_db_structs();
update_field($resourcea, $view_title_field, $newtitle);
$test_value = get_data_by_field($resourcea, $view_title_field);
// SUBTEST E - Check value has been set correctly
if ($newtitle != $test_value) {
    echo "SUBTEST E";
    return false;
}

// SUBTEST F - Check value has been set correctly on resource table
$resdata = get_resource_data($resourcea, false);
if ($newtitle != $resdata["field" . $view_title_field]) {
    echo "SUBTEST F";
    return false;
}

$use_cases = [
    [
        'name' => 'Date in expected (yyyy-mm-dd) format',
        'input' => ['resource' => $resourcea, 'rtf' => $rtf_date, 'value' => '2023-04-18'],
        'expected' => true,
        'errors' => 0,
    ],
    [
        'name' => 'Date in wrong (dmY) format should error and let user know',
        'input' => ['resource' => $resourcea, 'rtf' => $rtf_date, 'value' => '01-04-2022'],
        'expected' => false,
        'errors' => 1,
    ],
];
foreach ($use_cases as $uc) {
    $use_native_input_for_date_field = true;
    $errors = [];
    $result = update_field($uc['input']['resource'], $uc['input']['rtf'], $uc['input']['value'], $errors, false);
    if (!($uc['expected'] === $result && $uc['errors'] === count($errors))) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}



// Tear down
$view_title_field = $saved_view_title_field;
unset($use_cases, $result, $rtf_date);


# SUBTEST G - i18n_get_translated values are saved.
# Try adding a value to a dynamic keywords list field that has been translated.
# The correct translation should be added.

$keywords_list_field = create_resource_type_field("test_translated_keywords", 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'test_translated_keywords', false);
$subtest_g_node_ref = set_node(null, $keywords_list_field, '~en:Edit properties~fr:Modifier les propriétés~es:Editar propiedades', null, '');

$userpermissions[] = 'bdk' . $keywords_list_field;

$subtest_g_resource = create_resource(1, 0);
update_field($subtest_g_resource, $keywords_list_field, 'Edit properties');

$subtest_g_nodes_added = get_resource_nodes($subtest_g_resource, $keywords_list_field, false);
if (count($subtest_g_nodes_added) === 0 || isset($subtest_g_nodes_added[0]) && $subtest_g_nodes_added[0] != $subtest_g_node_ref) {
    echo "SUBTEST G";
    return false;
}


return true;

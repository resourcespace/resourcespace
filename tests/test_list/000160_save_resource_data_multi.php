<?php

command_line_only();

// --- Set up
$initial_field_column_string_separator = $field_column_string_separator;

// Metadata fields
$rtf_checkbox = create_resource_type_field("Test #402 checkbox", 1, FIELD_TYPE_CHECK_BOX_LIST, "test_402_checkbox", true);
$ckb_opt_a = set_node(null, $rtf_checkbox, '~en:Scanned negative~fr:Négatif scanné', null, 10);
$ckb_opt_b = set_node(null, $rtf_checkbox, '~en:Digital camera~fr:Appareil photo numérique', null, 20);
$ckb_opt_c = set_node(null, $rtf_checkbox, 'Check C', null, '');

$rtf_cat_tree = create_resource_type_field('Test #402 category tree', 1, FIELD_TYPE_CATEGORY_TREE, 'test_402_tree', true);
$ct_opt_colors = set_node(null, $rtf_cat_tree, '~en:Colors~fr:Couleurs', null, 10);
$ct_opt_numbers = set_node(null, $rtf_cat_tree, '~en:Numbers~fr:Nombres', null, 20);
$ct_opt_colors_red = set_node(null, $rtf_cat_tree, '~en:red~fr:rouge', $ct_opt_colors, 20);
$ct_opt_colors_black = set_node(null, $rtf_cat_tree, '~en:black~fr:noire', $ct_opt_colors, 10);
$ct_opt_colors_blue = set_node(null, $rtf_cat_tree, '~en:blue~fr:bleue', $ct_opt_colors, 30);

$rtf_date = create_resource_type_field("Test #402 date", 1, FIELD_TYPE_DATE, 'test_402_date', false);

$rtf_dropdown = create_resource_type_field('Test #402 dropdown', 1, FIELD_TYPE_DROP_DOWN_LIST, 'test_402_dropdown', true);
$drpd_opt_a = set_node(null, $rtf_dropdown, 'Drop A', null, '');
$drpd_opt_b = set_node(null, $rtf_dropdown, 'Drop B', null, '');

// Resources
$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);

// Collections
$collection_ref = create_collection($userref, 'test_402', 1);
add_resource_to_collection($resource_a, $collection_ref);
add_resource_to_collection($resource_b, $collection_ref);
$resources_list = [$resource_a, $resource_b];

// Assert helpers
$assert_same_all_resources_fieldx = function ($resources, $rtf) {
    $data = [];
    foreach ($resources as $resource) {
        $data[$resource] = get_resource_data($resource, false)["field{$rtf}"];
    }

    $fieldx_value = reset($data);

    if (
        count($data) > 1
        && count(array_unique($data)) === 1
        && $fieldx_value !== false
        && mb_strpos($fieldx_value, $GLOBALS['field_column_string_separator']) !== false
    ) {
        return true;
    }

    return false;
};

// Deactivate some nodes
toggle_active_state_for_nodes([$ckb_opt_c, $drpd_opt_b]);
// --- End of Set up



$use_cases = [
    [
        'name' => 'Date in wrong (dmY) format should error and let user know',
        'input' => [
            'collection' => $collection_ref,
            'postvals' => [
                "field_{$rtf_date}" => '01-04-2022',
                "editthis_field_{$rtf_date}" => 'yes',
                "modeselect_{$rtf_date}" => 'RT',
            ],
        ],
        'expected' => [$rtf_date => '01-04-2022'], // it means it errored - see save_resource_data
    ],
    [
        'name' => 'Date in expected (yyyy-mm-dd) format',
        'input' => [
            'collection' => $collection_ref,
            'postvals' => [
                "field_{$rtf_date}" => '2023-04-18',
                "editthis_field_{$rtf_date}" => 'yes',
                "modeselect_{$rtf_date}" => 'RT',
            ],
        ],
        'expected' => true,
    ],
    [
        'name' => 'Adding disabled options',
        'input' => [
            'collection' => $collection_ref,
            'postvals' => [
                'nodes' => [$rtf_checkbox => [$ckb_opt_b, $ckb_opt_c]],
                "editthis_field_{$rtf_checkbox}" => 'yes',
                "modeselect_{$rtf_checkbox}" => 'RT',
            ],
        ],
        'expected' => true,
    ],
    [
        'name' => 'Removing disabled options',
        'setup' => fn() => add_resource_nodes($resource_b, [$ckb_opt_c], false, false),
        'input' => [
            'collection' => $collection_ref,
            'postvals' => [
                'nodes' => [$rtf_checkbox => [$ckb_opt_b]],
                "editthis_field_{$rtf_checkbox}" => 'yes',
                "modeselect_{$rtf_checkbox}" => 'RT',
            ],
        ],
        'expected' => true,
    ],
    [
        'name' => 'Removing disabled options for a type that can only hold one value (e.g. dropdown)',
        'setup' => fn() => add_resource_nodes($resource_b, [$drpd_opt_b], false, false),
        'input' => [
            'collection' => $collection_ref,
            'postvals' => [
                'nodes' => [$rtf_dropdown => [$drpd_opt_a]],
                "editthis_field_{$rtf_dropdown}" => 'yes',
                "modeselect_{$rtf_dropdown}" => 'RT',
            ],
        ],
        'expected' => true,
    ],
];
foreach ($use_cases as $uc) {
    // Set up the use case environment
    if (isset($uc['setup'])) {
        $uc['setup']();
    }

    if ($uc['expected'] !== save_resource_data_multi($uc['input']['collection'], [], $uc['input']['postvals'])) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}


// Check field_column_string_separator is applied for fixed list fields
$field_column_string_separator = '|:|';

// - Check box
$data_joins[] = $rtf_checkbox;
$_POST['nodes'][$rtf_checkbox] = [$ckb_opt_a, $ckb_opt_b];
$_POST["editthis_field_{$rtf_checkbox}"] = 'yes';
$_POST["modeselect_{$rtf_checkbox}"] = 'RT';
save_resource_data_multi($collection_ref);
if (!$assert_same_all_resources_fieldx($resources_list, $rtf_checkbox)) {
    echo "Use case (RT mode): use separator for storing multiple node values in the resource table (column fieldX) - ";
    return false;
}


// - Category tree
// -- Replace all text/options
$data_joins[] = $rtf_cat_tree;
$_POST['nodes'][$rtf_cat_tree] = [$ct_opt_colors, $ct_opt_colors_red, $ct_opt_colors_black];
$_POST["editthis_field_{$rtf_cat_tree}"] = 'yes';
$_POST["modeselect_{$rtf_cat_tree}"] = 'RT';
save_resource_data_multi($collection_ref);
if (!$assert_same_all_resources_fieldx($resources_list, $rtf_cat_tree)) {
    echo 'Use case (RT mode): use separator for storing multiple node paths for category tree in column fieldX - ';
    return false;
}


$cat_tree_fieldx_value = get_resource_data($resource_a, false)["field{$rtf_cat_tree}"];
$cat_tree_fieldX_values = array_intersect_key(
    array_column(get_nodes($rtf_cat_tree, null, true), 'name', 'ref'),
    array_flip([$ct_opt_colors, $ct_opt_colors_black, $ct_opt_colors_red])
);
$expected_cat_tree_fieldx_value = implode(
    $field_column_string_separator,
    array_map(
        function (array $v): string {
            return implode('/', $v);
        },
        [
            [$cat_tree_fieldX_values[$ct_opt_colors]],
            [$cat_tree_fieldX_values[$ct_opt_colors], $cat_tree_fieldX_values[$ct_opt_colors_black]],
            [$cat_tree_fieldX_values[$ct_opt_colors], $cat_tree_fieldX_values[$ct_opt_colors_red]],
        ]
    )
);
if (
    $expected_cat_tree_fieldx_value !== $cat_tree_fieldx_value
) {
    echo 'Use case (RT mode): column fieldX (category tree) having nodes resolved according to their order_by - ';
    return false;
}


// -- Append all text/options
$data_joins[] = $rtf_cat_tree;
$_POST['nodes'][$rtf_cat_tree] = [$ct_opt_numbers, $ct_opt_colors, $ct_opt_colors_red, $ct_opt_colors_black];
$_POST["editthis_field_{$rtf_cat_tree}"] = 'yes';
$_POST["modeselect_{$rtf_cat_tree}"] = 'AP';
save_resource_data_multi($collection_ref);
if (!$assert_same_all_resources_fieldx($resources_list, $rtf_cat_tree)) {
    echo 'Use case (AP mode): use separator for storing multiple node paths for category tree in column fieldX - ';
    return false;
}

$cat_tree_fieldx_value = get_resource_data($resource_a, false)["field{$rtf_cat_tree}"];
$cat_tree_fieldX_values = array_intersect_key(
    array_column(get_nodes($rtf_cat_tree, null, true), 'name', 'ref'),
    array_flip([$ct_opt_colors, $ct_opt_colors_black, $ct_opt_colors_red, $ct_opt_numbers])
);
$expected_cat_tree_fieldx_value = implode(
    $field_column_string_separator,
    array_map(
        function (array $v): string {
            return implode('/', $v);
        },
        [
            [$cat_tree_fieldX_values[$ct_opt_colors]],
            [$cat_tree_fieldX_values[$ct_opt_colors], $cat_tree_fieldX_values[$ct_opt_colors_black]],
            [$cat_tree_fieldX_values[$ct_opt_colors], $cat_tree_fieldX_values[$ct_opt_colors_red]],
            [$cat_tree_fieldX_values[$ct_opt_numbers]],
        ]
    )
);
if (
    $expected_cat_tree_fieldx_value !== $cat_tree_fieldx_value
) {
    echo 'Use case (AP mode): column fieldX (category tree) having nodes resolved according to their order_by - ';
    return false;
}



// Tear down
$field_column_string_separator = $initial_field_column_string_separator;
$data_joins = $_POST = [];
unset(
    $rtf_checkbox,
    $ckb_opt_a,
    $ckb_opt_b,
    $resource_a,
    $resource_b,
    $collection_ref,
    $resources_list,
    $rtf_date,
    $assert_same_all_resources_fieldx,
    $cat_tree_fieldx_value,
    $cat_tree_fieldX_values,
    $expected_cat_tree_fieldx_value,
    $use_cases,
    $rtf_dropdown,
    $ckb_opt_c,
    $drpd_opt_a,
    $drpd_opt_b
);

return true;

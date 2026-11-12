<?php

command_line_only();

$fieldcolumns = get_resource_type_field_columns();
$savecolumns = array_filter($fieldcolumns, function ($v, $k) {
    return $k == "display_condition";
}, ARRAY_FILTER_USE_BOTH);

$resource = create_resource(1);

$use_cases = [
    [
        'name'  => 'Display condition : standard string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test1a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test1a');
            $governed_field   = create_resource_type_field('2005Test1b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test1b');
            $node_id = set_node(null, $display_governor, 'No special characters', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test1a=No special characters']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : non matching standard string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test2a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test2a');
            $governed_field   = create_resource_type_field('2005Test2b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test2b');
            $node_id = set_node(null, $display_governor, 'No special characters', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test1a=This does not match']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => false
    ],
    [
        'name'  => 'Display condition : string with special characters',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test3a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test3a');
            $governed_field   = create_resource_type_field('2005Test3b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test3b');
            $node_id = set_node(null, $display_governor, 'There are\' some, special \ characters / here', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test3a=There are\' some, special \ characters / here']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : non matching string with special characters',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test4a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test4a');
            $governed_field   = create_resource_type_field('2005Test4b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test4b');
            $node_id = set_node(null, $display_governor, 'This does not match', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test4a=There are\' some, special \ characters / here']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => false
    ],
    [
        'name'  => 'Display condition : Category tree root normal string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test5a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test5a');
            $governed_field   = create_resource_type_field('2005Test5b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test5b');
            $node_id = set_node(null, $display_governor, 'No special characters', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test5a=No special characters']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : Non matching category tree root normal string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test6a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test6a');
            $governed_field   = create_resource_type_field('2005Test6b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test6b');
            $node_id = set_node(null, $display_governor, 'No special characters', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test6a=This does not match']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => false
    ],
    [
        'name'  => 'Display condition : Category tree leaf normal string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test7a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test7a');
            $governed_field   = create_resource_type_field('2005Test7b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test7b');
            $parent_node_id   = set_node(null, $display_governor, 'Category tree parent', null, 10);
            $node_id          = set_node(null, $display_governor, 'Category tree - child node', $parent_node_id, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test7a=Category tree - child node']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : Category tree root special characters',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test8a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test8a');
            $governed_field   = create_resource_type_field('2005Test8b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test8b');
            $node_id   = set_node(null, $display_governor, 'Category / tree , root \' node', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test8a=Category / tree , root \' node']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : Category tree leaf special characters',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test9a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test9a');
            $governed_field   = create_resource_type_field('2005Test9b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test9b');
            $parent_node_id   = set_node(null, $display_governor, 'Category tree parent', null, 10);
            $node_id          = set_node(null, $display_governor, 'Category / tree , leaf \' node', $parent_node_id, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test9a=Category / tree , leaf \' node']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : Non matching category tree leaf special characters',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test9a', 0, FIELD_TYPE_CATEGORY_TREE, '2005Test9a');
            $governed_field   = create_resource_type_field('2005Test9b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test9b');
            $parent_node_id   = set_node(null, $display_governor, 'Category tree parent', null, 10);
            $node_id          = set_node(null, $display_governor, 'Category / tree , leaf \' node', $parent_node_id, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test9a=Category / tree , root \' node']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => false
    ],
    [
        'name'  => 'Display condition : Multilingual string',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test10a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test10a');
            $governed_field   = create_resource_type_field('2005Test10b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test10b');
            $node_id          = set_node(null, $display_governor, '~en:English text~fr:French text', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test10a=English text']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ],
    [
        'name'  => 'Display condition : Multilingual string full value',
        'setup' => function () use ($resource, $savecolumns) {
            $display_governor = create_resource_type_field('2005Test11a', 0, FIELD_TYPE_CHECK_BOX_LIST, '2005Test11a');
            $governed_field   = create_resource_type_field('2005Test11b', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '2005Test11b');
            $node_id          = set_node(null, $display_governor, '~en:English text~fr:French text', null, 10);
            save_resource_type_field($governed_field, $savecolumns, ['display_condition' => '2005Test11a=~en:English text~fr:French text']);

            add_resource_nodes($resource, [$node_id]);
            return $governed_field;
        },
        'result' => true
    ]
];

global $ref, $use, $display_check_data;
$ref_place_holder = $ref;
$use_place_holder = $use;
$display_check_place_holder = $display_check_data;
$ref = $use = $resource;

foreach ($use_cases as $use_case) {
    $governed_field = $use_case['setup']();

    $field  = [];
    $fields = $display_check_data = get_resource_field_data($resource);
    foreach ($fields as $f) {
        if ($f['ref'] == $governed_field) {
            $field = $f;
            break;
        }
    }

    $result = check_display_condition(1, $field, $fields, false, $resource);
    if ($field == [] || $result !== $use_case['result']) {
        echo 'ERROR - ' . $use_case['name'] . PHP_EOL;
        return false;
    }
}

$ref = $ref_place_holder;
$use = $use_place_holder;
$display_check_data = $display_check_place_holder;

// Unrestricted dependency data must carry display permissions without exposing denied fields.
$saved_permissions = $userpermissions;
try {
    $userpermissions = ['f' . $governed_field];
    $all_fields = get_resource_field_data($resource, false, false);
    $visible_fields = array_values(array_filter($all_fields, static fn($field) => $field['can_view']));
    if (array_column($visible_fields, 'ref') !== [$governed_field]
        || $visible_fields !== get_resource_field_data($resource)) {
        echo 'ERROR - Explicit field visibility flag';
        return false;
    }

    $userpermissions = ['f*', 'f-' . $governed_field];
    $all_fields = array_column(get_resource_field_data($resource, false, false), null, 'ref');
    if (!isset($all_fields[$governed_field]) || $all_fields[$governed_field]['can_view']
        || in_array($governed_field, array_column(get_resource_field_data($resource), 'ref'))) {
        echo 'ERROR - Explicit field denial or dependency data lost';
        return false;
    }

    $userpermissions = ['f*', 'T1'];
    $all_fields = get_resource_field_data($resource, false, false);
    if (in_array(true, array_column($all_fields, 'can_view'), true)
        || get_resource_field_data($resource) !== false) {
        echo 'ERROR - Resource type denial ignored';
        return false;
    }

    $userpermissions = ['P' . $governed_field];
    $template_fields = get_resource_field_data(-1, false, false, $resource);
    $visible_fields = array_values(array_filter($template_fields, static fn($field) => $field['can_view']));
    if (array_column($visible_fields, 'ref') !== [$governed_field]
        || $visible_fields !== get_resource_field_data(-1, false, true, $resource)) {
        echo 'ERROR - Upload-only field permission flag';
        return false;
    }
} finally {
    $userpermissions = $saved_permissions;
}

return true;

<?php

command_line_only();

// --- Set up
$run_id = test_generate_random_ID(5);
test_log("Run ID - {$run_id}");
$rt_title_prefix = sprintf('Test #%s-%s', test_get_file_id(__FILE__), $run_id);
$rt_name_prefix = sprintf('test_%s-%s', test_get_file_id(__FILE__), $run_id);

// Checkbox field
$rtf_checkbox = create_resource_type_field("{$rt_title_prefix} checkbox", 1, FIELD_TYPE_CHECK_BOX_LIST, "{$rt_name_prefix}_checkbox", true);
$ckb_opt_a = set_node(null, $rtf_checkbox, "Check A ({$rt_name_prefix})", null, '');
$ckb_opt_b = set_node(null, $rtf_checkbox, "Check B ({$rt_name_prefix})", null, '');
$ckb_opt_c = set_node(null, $rtf_checkbox, "Check C ({$rt_name_prefix})", null, '');

// Text field and a resource with a value (it's also using nodes)
$rtf_text = create_resource_type_field("{$rt_title_prefix} text", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "{$rt_name_prefix}_txt", false);
$resource_a = create_resource(1, 0);
update_field($resource_a, $rtf_text, "Lorem ipsum dolor sit amet. ({$rt_name_prefix})");
$rtf_text_node_id = get_resource_nodes($resource_a, $rtf_text)[0];
$rtf_text_node = [];
get_node($rtf_text_node_id, $rtf_text_node, false);

// Category tree field
$rtf_cat_tree = create_resource_type_field("{$rt_title_prefix} category tree", 1, FIELD_TYPE_CATEGORY_TREE, "{$rt_name_prefix}_tree", true);
test_log("rtf_cat_tree = {$rtf_cat_tree}");
$ct_opt_a = set_node(null, $rtf_cat_tree, 'A', null, '');
$ct_opt_a_a1 = set_node(null, $rtf_cat_tree, 'a.1', $ct_opt_a, '');
$ct_opt_a_a1_a11 = set_node(null, $rtf_cat_tree, 'a.1.1', $ct_opt_a_a1, '');
$ct_opt_a_a1_a12 = set_node(null, $rtf_cat_tree, 'a.1.2', $ct_opt_a_a1, '');
$ct_opt_a_a2 = set_node(null, $rtf_cat_tree, 'a.2', $ct_opt_a, '');
$ct_opt_b = set_node(null, $rtf_cat_tree, 'B', null, '');
$ct_opt_b_b1 = set_node(null, $rtf_cat_tree, 'b.1', $ct_opt_b, '');
$ct_opt_c = set_node(null, $rtf_cat_tree, 'C', null, '');

// Initial state
$nodes_UT = array_merge(
    get_nodes($rtf_checkbox, null, false),
    [$rtf_text_node],
    get_nodes($rtf_cat_tree, null, true)
);
$reset_state = function () use ($nodes_UT) {
    test_log('Resetting state...');
    update_node_active_state(array_column($nodes_UT, 'ref'), true);
};
// --- End of Set up

$use_cases = [
    [
        'name' => 'Only fixed list fields can toggle the active state',
        'input' => ['refs' => [$rtf_text_node_id]],
        'expected' => [$rtf_text_node_id => 1],
    ],
    [
        'name' => 'Toggle fixed list fields (e.g checkbox) active state',
        'input' => ['refs' => [$ckb_opt_a]],
        'expected' => [$ckb_opt_a => 0],
    ],
    [
        'name' => 'Toggle various fields',
        'reset_state' => true,
        'input' => ['refs' => [$ckb_opt_b, $rtf_text_node_id, $ct_opt_c]],
        'expected' => [
            $ckb_opt_b => 0,
            $rtf_text_node_id => 1,
            $ct_opt_c => 0,
        ],
    ],
    [
        'name' => 'Disabling tree root option propagates to children',
        'reset_state' => true,
        'input' => ['refs' => [$ct_opt_a]],
        'expected' => [
            $ct_opt_a => 0,
            $ct_opt_a_a1 => 0,
            $ct_opt_a_a1_a11 => 0,
            $ct_opt_a_a1_a12 => 0,
            $ct_opt_a_a2 => 0,
        ],
    ],
    [
        'name' => 'Re-activating a tree root option leaves children alone',
        'input' => ['refs' => [$ct_opt_a]],
        'expected' => [$ct_opt_a => 1],
    ],
    [
        'name' => 'Disabling child (level 1) option propagates to children',
        'reset_state' => true,
        'input' => ['refs' => [$ct_opt_a_a1]],
        'expected' => [
            $ct_opt_a_a1 => 0,
            $ct_opt_a_a1_a11 => 0,
            $ct_opt_a_a1_a12 => 0,
        ],
    ],
    [
        'name' => 'Re-activating a tree level 1 option leaves children alone',
        'reset_state' => true,
        'setup' => fn() => update_node_active_state([$ct_opt_a_a1], false),
        'input' => ['refs' => [$ct_opt_a_a1]],
        'expected' => [$ct_opt_a_a1 => 1],
    ],
    [
        'name' => 'Toggling a child option should check the parent is active',
        'reset_state' => true,
        'setup' => fn() => update_node_active_state([$ct_opt_a_a1, $ct_opt_a_a1_a11], false),
        'input' => ['refs' => [$ct_opt_a_a1_a11]],
        'expected' => [$ct_opt_a_a1_a11 => 0],
    ],
    [
        'name' => 'Toggling a child option should check grand parents are active',
        'reset_state' => true,
        'setup' => fn() => update_node_active_state([$ct_opt_a, $ct_opt_a_a1_a12], false),
        'input' => ['refs' => [$ct_opt_a_a1_a12]],
        'expected' => [$ct_opt_a_a1_a12 => 0],
    ],
/*
A
    A.1
        A.1.1
        A.1.2
    A.2
B
    B.1
C

# Category tree cases
| Current node: active | Parent: active | Toggle? | NEW current node: active | Propagate to children? |
| -------------------- | -------------- | ------- | ------------------------ | ---------------------- |
| 1                    | n/a - root     | yes     | 0                        | yes                    |
| 0                    | n/a - root     | yes     | 1                        | no                     |
| 1                    | 1 - level 1    | yes     | 0                        | yes                    |
| 0                    | 1 - level 1    | yes     | 1                        | no                     |
| 0                    | 0 - level 1    | no      | 0                        | n/a (should already be done) |
| 0                    | 0 - level 2    | no      | 0                        | n/a (should already be done) |

*/
];
foreach ($use_cases as $uc) {
    if (isset($uc['reset_state'])) {
        $reset_state();
    }

    // Set up the use case environment
    if (isset($uc['setup'])) {
        $uc['setup']();
        test_log(
            "Setup use case: nodes active state = "
            . print_r(array_column(get_nodes_by_refs(array_column($nodes_UT, 'ref')), 'active', 'ref'), true)
        );
    }

    $result = toggle_active_state_for_nodes($uc['input']['refs']);

    ksort($result, SORT_NUMERIC);
    ksort($uc['expected'], SORT_NUMERIC);

    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        test_log('$result = ' . print_r($result, true));
        test_log('expected = ' . print_r($uc['expected'], true));
        return false;
    }
}

// Tear down
unset(
    $use_cases,
    $run_id,
    $rt_title_prefix,
    $rt_name_prefix,
    $rtf_checkbox,
    $ckb_opt_a,
    $ckb_opt_b,
    $ckb_opt_c,
    $rtf_text,
    $resource_a,
    $rtf_text_node,
    $nodes_UT,
    $reset_state
);

return true;

<?php

command_line_only();



// Set up
$get_collections_order = function (array $refs) {
    return ps_array('SELECT ref AS `value` FROM collection WHERE ref IN (' . ps_param_insert(count($refs)) . ') ORDER BY order_by ASC', ps_param_fill($refs, 'i'));
};
$A = create_collection(0, 'test_1406 collection A');
$B = create_collection(0, 'test_1406 collection B');
$C = create_collection(0, 'test_1406 collection C');
$all = [$A, $B, $C];



$use_cases = [
    [
        'name' => 'Re-order',
        'new_order' => [$B, $A, $C],
        'expected' => [$B, $A, $C],
    ],
    [
        'name' => 'Nothing to re-order',
        'new_order' => [],
        'expected' => $all,
    ],
    [
        'name' => 'Bad input: non-numeric strings',
        'new_order' => [$C, 'foo', $A, $B, 'bar'],
        'expected' => [$C, $A, $B],
    ],
    [
        'name' => 'Bad input: string numbers',
        'new_order' => [$C, $A, (string) $B],
        'expected' => [$C, $A, $B],
    ],
];
foreach ($use_cases as $use_case) {
    sql_reorder_records('collection', $use_case['new_order']);
    if ($get_collections_order($all) !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }

    // Reset order to the initial one
    sql_reorder_records('collection', $all);
}



// Tear down
unset($get_collections_order, $A, $B, $C, $use_cases);

return true;

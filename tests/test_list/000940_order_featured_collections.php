<?php

command_line_only();


// Set up
$fc_struct = function ($name, $order_by, $is_categ = true) {
    return [
        'name' => $name,
        'order_by' => $order_by,
        'has_resources' => (int) !$is_categ,
    ];
};
$fc_A = $fc_struct('A', 30);
$fc_B = $fc_struct('B', 40);
$fc_C = $fc_struct('C', 50);
$fc_categ_1 = $fc_struct('Category 1', 0);
$fc_categ_2 = $fc_struct('Category 2', 0);
$fc_categ_3 = $fc_struct('A new category 3', 0);
$fc_categ_4_wstar = $fc_struct('*Category 4', 0);
$fc_col_1 = $fc_struct('FC col 1', 0, false);
$fc_col_2_wstar = $fc_struct('*FC col with star', 0, false);
$fc_col_A = $fc_struct('A new FC collection', 10, false);



$test_1404_ucs = [
    [
        'name' => 'Sort using order_by',
        'input' => [$fc_B, $fc_A, $fc_C],
        'expected' => [$fc_A, $fc_B, $fc_C],
    ],
    [
        'name' => 'Categories first, then collections',
        'input' => [$fc_col_1, $fc_categ_2, $fc_categ_1],
        'expected' => [$fc_categ_1, $fc_categ_2, $fc_col_1],
    ],
    [
        'name' => 'Sort by name (no stars)',
        'input' => [$fc_categ_2, $fc_categ_3, $fc_categ_1],
        'expected' => [$fc_categ_3, $fc_categ_1, $fc_categ_2],
    ],
    [
        'name' => 'Sort by name (w/ stars)',
        'input' => [$fc_categ_1, $fc_categ_2, $fc_categ_4_wstar, $fc_col_1, $fc_col_2_wstar],
        'expected' => [$fc_categ_4_wstar, $fc_categ_1, $fc_categ_2, $fc_col_2_wstar, $fc_col_1],
    ],
    [
        // Collections with order_by = 0 will be sorted based on the other rules - by has_resources or name
        'name' => 'Mixing collections with order_by = 0',
        'input' => [$fc_B, $fc_A, $fc_C, $fc_categ_2, $fc_categ_3, $fc_categ_1, $fc_col_A, $fc_col_1],
        'expected' => [$fc_A, $fc_categ_3, $fc_B, $fc_C, $fc_categ_1, $fc_categ_2, $fc_col_A, $fc_col_1],
    ],
];
foreach ($test_1404_ucs as $use_case) {
    usort($use_case['input'], 'order_featured_collections');
    if ($use_case['expected'] !== $use_case['input']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset($fc_struct, $test_1404_ucs);
unset($fc_A, $fc_B, $fc_C, $fc_categ_1, $fc_categ_2, $fc_categ_3, $fc_categ_4_wstar, $fc_col_1, $fc_col_2_wstar);

return true;

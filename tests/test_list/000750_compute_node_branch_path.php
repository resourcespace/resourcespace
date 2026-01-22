<?php

command_line_only();

// --- Set up
$branch_path_fct = function ($carry, $item) {
    return "{$carry}/{$item["name"]}";
};
$path = function ($P) use ($branch_path_fct) {
    return array_reduce($P, $branch_path_fct, "");
};

$tree = [
    ['ref' => 1, 'parent' => null, 'name' => 'A'],
        ['ref' => 2, 'parent' => 1, 'name' => 'B'],
            ['ref' => 5, 'parent' => 2, 'name' => 'D'],
        ['ref' => 3, 'parent' => 1, 'name' => 'C'],
        ['ref' => 4, 'parent' => 1, 'name' => 'E'],
            ['ref' => 6, 'parent' => 4, 'name' => 'F'],
];
// --- End of Set up



$use_cases = [
    [
        'name' => 'Getting a top level node should return it back',
        'id' => 1,
        'expected' => '/A',
    ],
    [
        'name' => 'Path for B is A -> B',
        'id' => 2,
        'expected' => '/A/B',
    ],
    [
        'name' => 'Path for E is A -> E',
        'id' => 4,
        'expected' => '/A/E',
    ],
    [
        'name' => 'Path for D is A -> B -> D',
        'id' => 5,
        'expected' => '/A/B/D',
    ],
    [
        'name' => 'Path for F is A -> E -> F',
        'id' => 6,
        'expected' => '/A/E/F',
    ],
    [
        'name' => 'Path for inexistant node',
        'id' => 2342,
        'expected' => '',
    ],
];
foreach ($use_cases as $use_case) {
    if ($use_case['expected'] !== $path(compute_node_branch_path($tree, $use_case['id']))) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset($branch_path_fct, $path, $tree);

return true;

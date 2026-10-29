<?php

command_line_only();

// Setup
// Create resource type field
$dot_notation_field = create_resource_type_field('dotnotation');
$data_joins[] =  $dot_notation_field;
$sort_fields[] = $dot_notation_field;

// Set sort method
$fieldcolumns = get_resource_type_field_columns();
$savecolumns = array_filter($fieldcolumns, function ($v, $k) {
    return $k == "sort_method";
}, ARRAY_FILTER_USE_BOTH);
save_resource_type_field($dot_notation_field, $savecolumns, ["sort_method" => "1"]);

// Create test nodes/resources
$title_node = set_node(null, 8, 'dotnotation test resource', '', 1000);
$test_values = [
    "ab123.456.789",
    "4539.234.01",
    "123.342.98",
    "ab23.456.789",
    "123.42.98",
    "8.99.99",
    "",
];

for ($n = 0; $n < count($test_values); $n++) {
    $resources[$n] = create_resource(1, 0);
    add_resource_nodes($resources[$n], [$title_node]);
    if ($test_values[$n] !== "") {
        $nodes[$n] = set_node(null, $dot_notation_field, $test_values[$n], '', 1000);
        add_resource_nodes($resources[$n], [$nodes[$n]]);
    }
}

update_fieldx($dot_notation_field);
// End setup

$test_cases = [
    "A" => [
        "sort" => "resourceid",
        "order_by" => "asc",
        "expected_result" => [
            $resources[0],
            $resources[1],
            $resources[2],
            $resources[3],
            $resources[4],
            $resources[5],
            $resources[6],
        ]
    ],
    "B" => [
        "sort" => "field$dot_notation_field",
        "order_by" => "asc",
        "expected_result" => [
            $resources[6],      // empty
            $resources[3],      // ab23.456.789
            $resources[0],      // ab123.456.789
            $resources[5],      // 8.99.99
            $resources[4],      // 123.42.98
            $resources[2],      // 123.342.98
            $resources[1],      // 4539.234.01
        ]
    ],
    "C" => [
        "sort" => "field$dot_notation_field",
        "order_by" => "desc",
        "expected_result" => [
            $resources[1],
            $resources[2],
            $resources[4],
            $resources[5],
            $resources[0],
            $resources[3],
            $resources[6],
        ]
    ],
];

foreach ($test_cases as $test => $case) {
    $results = array_column(do_search("@@$title_node", '', $case["sort"], 0, -1, $case["order_by"]), 'ref');

    if (array_values(array_intersect($results, $resources)) != array_values($case["expected_result"])) {
        echo "Test $test ";
        return false;
    }
}

// Tear down
$delete_state_cache = $resource_deletion_state ?? null;
unset($resource_deletion_state);

foreach ($resources as $resource) {
    delete_resource($resource);
}

if (!is_null($delete_state_cache)) {
    $resource_deletion_state = $delete_state_cache;
}

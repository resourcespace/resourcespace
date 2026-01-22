<?php

command_line_only();

// --- Set up
create_resource(1, 0); # test 400 breaks as it expects this resource (with ID #1) to still exist
$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);
$resource_c = create_resource(1, 0);
$resource_d = create_resource(1, 0);
$no_logger = static fn() => null;
$always_false = static fn() => false;
// --- End of Set up

$use_cases = [
    [
        'name' => 'Delete a single record',
        'input' => [
            'table' => 'resource',
            'refs' => [$resource_a],
            'logger' => $no_logger,
        ],
        'expected' => true,
    ],
    [
        'name' => 'Delete multiple records',
        'input' => [
            'table' => 'resource',
            'refs' => [$resource_b, $resource_c],
            'logger' => $no_logger,
        ],
        'expected' => true,
    ],
    [
        'name' => 'Deleting from table without ref column returns false',
        'input' => [
            'table' => 'resource_node',
            'refs' => [$resource_d],
            'logger' => $no_logger,
        ],
        'expected' => false,
    ],
    [
        'name' => 'Using a logger works as expected',
        'input' => [
            'table' => 'resource',
            'refs' => [$resource_d],
            'logger' => static fn($ref) => $GLOBALS['logger_store'][$ref] = true,
        ],
        'post_check' => static fn() => !isset($GLOBALS['logger_store'][$resource_d]),
        'expected' => true,
    ],
];
foreach ($use_cases as $uc) {
    $post_check_failed = $uc['post_check'] ?? $always_false;

    if (
        $uc['expected'] !== db_delete_table_records($uc['input']['table'], $uc['input']['refs'], $uc['input']['logger'])
        && $post_check_failed
    ) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases, $resource_a, $resource_b, $resource_c, $resource_d);

return true;

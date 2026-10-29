<?php

command_line_only();


// Set up
$orig_userpermissions = $userpermissions;
$userpermissions = [];



$use_cases = [
    [
        'name' => 'No permissions',
        'perms' => [],
        'expected' => false,
    ],
    [
        'name' => 'No h permission',
        'perms' => ['j*'],
        'expected' => false,
    ],
    [
        'name' => 'No j* permission',
        'perms' => ['h'],
        'expected' => false,
    ],
    [
        'name' => 'Allow re-order when having "h", "j*" and no "-jX"',
        'perms' => ['h', 'j*'],
        'expected' => true,
    ],
    [
        'name' => 'Not allowing re-order with "-jX" permission',
        'perms' => ['h', 'j*', '-j1405'],
        'expected' => false,
    ],
];
foreach ($use_cases as $use_case) {
    $userpermissions = $use_case['perms'];
    $GLOBALS['CACHE_FC_ACCESS_CONTROL'] = null;
    if (can_reorder_featured_collections() !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
$userpermissions = $orig_userpermissions;
unset($orig_userpermissions, $use_cases);
unset($GLOBALS['CACHE_FC_ACCESS_CONTROL']);
return true;

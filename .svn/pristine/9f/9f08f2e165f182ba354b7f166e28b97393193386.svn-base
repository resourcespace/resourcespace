<?php

command_line_only();


// --- Set up
$original_user_data = $userdata;
$original_userpermissions = $userpermissions;
$give_user_permission = function (string $perm) {
    return function () use ($perm) {
        $GLOBALS['userpermissions'][] = $perm;
    };
};
// --- End of Set up



$use_cases = [
    [
        'name' => 'Access not denied by resource type (ie T perm)',
        'input' => ['resource_type' => 1, 'size' => 'scr'],
        'expected' => false,
    ],
    [
        'name' => 'Permission "T?" denies access (overall)',
        'setup' => $give_user_permission('T1'),
        'input' => ['resource_type' => 1, 'size' => 'scr'],
        'expected' => true,
    ],
    [
        'name' => 'Permission "T?_?" deny access to original size ',
        'setup' => $give_user_permission('T1_'),
        'input' => ['resource_type' => 1, 'size' => ''],
        'expected' => true,
    ],
    [
        'name' => 'Permission "T?_?" deny access to specific size ',
        'setup' => $give_user_permission('T1_scr'),
        'input' => ['resource_type' => 1, 'size' => 'scr'],
        'expected' => true,
    ],
];
foreach ($use_cases as $use_case) {
    // Reset before testing this use case
    $userpermissions = $original_userpermissions;

    // Set up the use case environment
    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }

    $result = resource_has_access_denied_by_RT_size($use_case['input']['resource_type'], $use_case['input']['size']);
    if ($use_case['expected'] !== $result) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
setup_user($original_user_data);
unset($original_user_data, $use_cases, $result);

return true;

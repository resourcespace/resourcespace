<?php

command_line_only();

$webroot = dirname(__DIR__, 2);
include_once "{$webroot}/include/request_functions.php";


// Set up
// Create a metadata field for the owner
$owner_rtf = create_resource_type_field('test_2602 owner_field', 0, FIELD_TYPE_DROP_DOWN_LIST, 'test_2602_owner_field', false);
if ($owner_rtf === false) {
    echo 'Setting up the test: $owner_rtf - ';
    return false;
}

// Create Options for the owner_field
$owner_field_nodes_pool = [
    'A' => set_node(null, $owner_rtf, 'Test 2602: Owner - Admins', null, 10),
    'SA' => set_node(null, $owner_rtf, 'Test 2602: Owner - Super Admins', null, 20),
    'Others' => set_node(null, $owner_rtf, 'Test 2602: Owner - Others', null, 30),
];
foreach ($owner_field_nodes_pool as $node_ref) {
    if ($node_ref === false) {
        echo 'Setting up the test: node options for the $owner_rtf - ';
        return false;
    }
}

// Re-use existing standard user groups for this test:-
// [1] => Administrators
// [3] => Super Admin
$GLOBALS['owner_field_mappings'] = [
    $owner_field_nodes_pool['A'] => 1,
    $owner_field_nodes_pool['SA'] => 3,
    // $owner_field_nodes_pool['Others'] ===> testing a missing mapping. (shown here just for clarity)
];

// Create users
$users_data = [
    ['test_2602_A', 'Test 2602: User A', 1],
    ['test_2602_SA', 'Test 2602: User SA', 3],
    ['test_2602_Others', 'Test 2602: User Others', 6],
    ['test_2602_gen_1', 'Test 2602: User gen_1', 2],
];
$users_list = [];
foreach ($users_data as $user_details) {
    $user_2602 = new_user($user_details[0], $user_details[2]) ?: get_user_by_username($user_details[0]);

    $_POST['username'] = $user_details[0];
    $_POST['email'] = "{$user_details[0]}@integration-test.resourcespace.com";
    $_POST['fullname'] = $user_details[1];
    $_POST['usergroup'] = $user_details[2];
    $_POST['password'] = 'test_2602';
    $_POST['approved'] = "1";
    if (save_user($user_2602) !== true) {
        echo 'Setting up the test: users - ';
        return false;
    }

    unset($GLOBALS['udata_cache']);
    $users_list[] = get_user($user_2602);
}


// Create a pool of resources
$resource_pool = [];
$vars_suffixes = ['A', 'SA', 'Others', 'none1', 'none2'];
foreach ($vars_suffixes as $suffix) {
    $name = "resource_owned_by_{$suffix}";
    $resource_pool[$name] = create_resource(1, 0);

    // Quick update of owner field to something valid (when set)
    if (
        // failed to create resource
        $resource_pool[$name] === false
        // failed to associate owner field value
        || (
            isset($owner_field_nodes_pool[$suffix])
            && !add_resource_nodes($resource_pool[$name], [$owner_field_nodes_pool[$suffix]], false, false)
        )
    ) {
        echo 'Setting up the test: resources - ';
        return false;
    }
}

$all_test_users = get_users(0, 'test_2602_%', 'u.ref');
$build_expected_list_of_users = function (array $usernames) {
    $result = [];
    foreach ($usernames as $username) {
        $result[get_user_by_username($username)] = "{$username}@integration-test.resourcespace.com";
    }
    ksort($result, SORT_NUMERIC);
    return $result;
};
// End of Set up



$test_2602_ucs = [
    [
        'name' => 'Notify users responsible for resources managed by A',
        'input' => [
            'users' => $all_test_users,
            'resources' => [$resource_pool['resource_owned_by_A'], $resource_pool['resource_owned_by_none1']],
        ],
        'expected' => $build_expected_list_of_users(['test_2602_A']),
    ],
    [
        'name' => 'Notify users responsible for resources managed by A or SA',
        'input' => [
            'users' => $all_test_users,
            'resources' => [
                $resource_pool['resource_owned_by_A'],
                $resource_pool['resource_owned_by_SA'],
                $resource_pool['resource_owned_by_none1'],
            ],
        ],
        'expected' => $build_expected_list_of_users(['test_2602_A', 'test_2602_SA']),
    ],
    [
        'name' => 'Missing mapping for owner field option. (misconfiguration)',
        'input' => [
            'users' => $all_test_users,
            'resources' => [$resource_pool['resource_owned_by_Others'], $resource_pool['resource_owned_by_none1']],
        ],
        'expected' => [],
    ],
    [
        'name' => "Unmanaged resources shouldn't filter out any users",
        'input' => [
            'users' => $all_test_users,
            'resources' => [$resource_pool['resource_owned_by_none1'], $resource_pool['resource_owned_by_none2']],
        ],
        'expected' => $build_expected_list_of_users(array_column($all_test_users, 'username')),
    ],
    [
        'name' => 'No resources to process',
        'input' => [
            'users' => $all_test_users,
            'resources' => [],
        ],
        'expected' => [],
    ],
    [
        'name' => 'No users to filter',
        'input' => [
            'users' => [],
            'resources' => [
                $resource_pool['resource_owned_by_A'],
                $resource_pool['resource_owned_by_SA'],
                $resource_pool['resource_owned_by_Others'],
            ],
        ],
        'expected' => [],
    ],
    [
        'name' => 'Feature misconfigured or disabled (should return the input users)',
        'config' => ['owner_field' => 0],
        'input' => [
            'users' => $all_test_users,
            'resources' => [
                $resource_pool['resource_owned_by_A'],
                $resource_pool['resource_owned_by_SA'],
                $resource_pool['resource_owned_by_Others'],
            ],
        ],
        'expected' => $build_expected_list_of_users(array_column($all_test_users, 'username')),
    ],
];
foreach ($test_2602_ucs as $uc) {
    $GLOBALS['owner_field'] = $uc['config']['owner_field'] ?? $owner_rtf;
    $result = get_notification_users_by_owner_field($uc['input']['users'], $uc['input']['resources']);
    ksort($result, SORT_NUMERIC);

    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($owner_rtf, $owner_field_nodes_pool, $users_data, $users_list, $user_2602, $resource_pool, $vars_suffixes, $all_test_users);
unset($test_2602_ucs, $build_expected_list_of_users);
$GLOBALS['owner_field'] = 0;
$GLOBALS['owner_field_mappings'] = [];

return true;

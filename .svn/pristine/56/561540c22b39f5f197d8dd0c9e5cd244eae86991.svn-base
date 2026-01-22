<?php

command_line_only();

// --- Set up
$test_id = test_generate_random_ID(6);
$test_1201_setup_user = function (array $info) use ($test_id) {
    unset($GLOBALS['udata_cache']);
    $test_run_username = "{$info['username']}_$test_id";
    $new_user_id = new_user($test_run_username, 3);
    if ($new_user_id !== false) {
        $_POST['username'] = $test_run_username;
        $_POST['fullname'] = $info['fullname'];
        $_POST['email'] = "test_1201_{$test_id}_{$info['email']}";
        $_POST['password'] = make_password();
        $_POST['approved'] = $info['approved'] ?? '1';
        $_POST['account_expires'] = $info['account_expires'] ?? '';
        save_user($new_user_id);
        unset($GLOBALS['udata_cache']);
        $new_user_id = get_user($new_user_id);
    }
    return $new_user_id;
};

$active_user = $test_1201_setup_user([
    'username' => 'test_1201_user1',
    'fullname' => 'User One',
    'email' => 'userone@dummy.resourcespace.com',
]);
$user_not_approved = $test_1201_setup_user([
    'username' => 'test_1201_user2',
    'fullname' => 'User Two',
    'email' => 'usertwo@dummy.resourcespace.com',
    'approved' => '0',
]);
$expired_user = $test_1201_setup_user([
    'username' => 'test_1201_user3',
    'fullname' => 'User Three',
    'email' => 'userthree@dummy.resourcespace.com',
    'account_expires' => '2022-10-01',
]);
$user_w_invalid_email = $test_1201_setup_user([
    'username' => 'test_1201_user4',
    'fullname' => 'User Four',
    'email' => 'userfour@localhost', # based on current implementation which is using filter_var w/ FILTER_VALIDATE_EMAIL
]);
$test_1201_users_list = [$active_user, $user_not_approved, $expired_user, $user_w_invalid_email];
$test_1201_generate_expected_result = function (array $users) use ($test_1201_users_list): array {
    $unames = [];
    $emails = [];
    $refs = [];
    $key_required = [];
    foreach ($users as $user_info) {
        $user_idx = array_search($user_info['ref'], array_column($test_1201_users_list, 'ref'));
        $unames[] = $test_1201_users_list[$user_idx]['username'];
        $emails[] = $test_1201_users_list[$user_idx]['email'];
        $refs[] = $user_info['ref'];
        $key_required[] = $user_info['key_required'];
    }

    return [
        'unames' => $unames,
        'emails' => $emails,
        'refs' => $refs,
        'key_required' => $key_required,
    ];
};
// --- End of Set up



$use_cases = [
    [
        'name' => 'When given a list of valid usernames',
        'user_list' => [$active_user['username']],
        'expected' => $test_1201_generate_expected_result(
            [
                ['ref' => $active_user['ref'], 'key_required' => false],
            ]
        ),
    ],
    [
        'name' => 'When given a list of valid emails',
        'user_list' => [$active_user['email']],
        'expected' => array_intersect_key(
            array_merge(
                $test_1201_generate_expected_result(
                    [
                        ['ref' => $active_user['ref'], 'key_required' => true],
                    ]
                ),
                ['unames' => [$active_user['email']]]
            ),
            [
                'unames' => [],
                'emails' => [],
                'key_required' => [],
            ]
        ),
    ],
    [
        'name' => 'When given a list of usernames not approved',
        'user_list' => [$user_not_approved['username']],
        'expected' => [],
    ],
    [
        'name' => 'When given a list of expired usernames',
        'user_list' => [$expired_user['username']],
        'expected' => [],
    ],
    [
        'name' => 'User with invalid e-mail address',
        'user_list' => [$user_w_invalid_email['username']],
        'expected' => [],
    ],
];
foreach ($use_cases as $use_case) {
    $result = resolve_user_emails($use_case['user_list']);
    if ($use_case['expected'] !== $result) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset(
    $test_1201_setup_user,
    $test_1201_generate_expected_result,
    $test_1201_users_list,
    $active_user,
    $user_not_approved,
    $expired_user,
    $user_w_invalid_email,
    $use_cases,
    $result
);

return true;

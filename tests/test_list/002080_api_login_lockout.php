<?php

command_line_only();

include_once dirname(__DIR__, 2) . '/include/login_functions.php';
include_once dirname(__DIR__, 2) . '/include/api_functions.php';

$saved_login_globals = [];
foreach ([
    'username',
    'password',
    'password_hash',
    'session_hash',
    'usergroup',
    'udata_cache',
    'max_login_attempts_per_username',
    'max_login_attempts_per_ip',
    'max_login_attempts_wait_minutes',
    'password_brute_force_delay',
] as $login_global_name) {
    $saved_login_globals[$login_global_name] = $GLOBALS[$login_global_name] ?? null;
}
$test_ip = get_ip();
$saved_ip_lockouts = ps_query('SELECT tries, last_try FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);
$test_username = 'api_lockout_' . test_generate_random_ID(6);
$test_email = $test_username . '@example.invalid';
$test_user = new_user($test_username);
$test_password = 'LockoutPassword1!';
$GLOBALS['max_login_attempts_per_username'] = 3;
$GLOBALS['max_login_attempts_per_ip'] = 5;
$GLOBALS['max_login_attempts_wait_minutes'] = 10;

$use_cases = [
    [
        'name' => 'A correct password below the threshold returns a session key and clears both counters',
        'test' => function () use ($test_username, $test_password, $test_user, $test_ip) {
            $first_attempt = api_login($test_username, 'WrongPassword');
            $second_attempt = api_login($test_username, 'WrongPassword');

            $key = api_login($test_username, $test_password);
            $account_failures = ps_value('SELECT login_tries value FROM user WHERE ref = ?', ['i', $test_user], -1);
            $ip_failures = ps_value('SELECT COUNT(*) value FROM ip_lockout WHERE ip = ?', ['s', $test_ip], -1);

            return (
                $first_attempt === false
                && $second_attempt === false
                && is_string($key)
                && $key === get_session_api_key($test_user)
                && (int) $account_failures === 0
                && (int) $ip_failures === 0
            );
        },
        'expected' => true,
    ],
    [
        'name' => 'Three incorrect passwords lock the account when logging in by username or email',
        'test' => function () use ($test_username, $test_email, $test_password, $test_user, $test_ip) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (api_login($test_email, 'WrongPassword') !== false) {
                    return false;
                }
            }

            // Clear the IP counter to check the account lockout independently.
            ps_query('DELETE FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);
            $before = ps_query(
                'SELECT session, login_tries, login_last_try FROM user WHERE ref = ?',
                ['i', $test_user],
            );
            $username_login = api_login($test_username, $test_password);
            $email_login = api_login($test_email, $test_password);
            $incorrect_password_login = api_login($test_username, 'WrongPassword');
            $ui_login = perform_login($test_username, $test_password);
            $after = ps_query(
                'SELECT session, login_tries, login_last_try FROM user WHERE ref = ?',
                ['i', $test_user],
            );

            return (
                (int) $before[0]['login_tries'] === 3
                && $username_login === false
                && $email_login === false
                && $incorrect_password_login === false
                && $ui_login['valid'] === false
                // Blocked attempts must preserve the session, counter and lockout expiry.
                && $after === $before
            );
        },
        'expected' => true,
    ],
    [
        'name' => 'After account lockout expires, the correct password allows login again',
        'test' => function () use ($test_username, $test_password, $test_user) {
            ps_query(
                'UPDATE user SET login_tries = 3, login_last_try = NOW() - INTERVAL 11 MINUTE WHERE ref = ?',
                ['i', $test_user],
            );

            return is_string(api_login($test_username, $test_password));
        },
        'expected' => true,
    ],
    [
        'name' => 'After account lockout expires, an incorrect password starts a fresh failure counter',
        'test' => function () use ($test_username, $test_password, $test_user) {
            ps_query(
                'UPDATE user SET login_tries = 3, login_last_try = NOW() - INTERVAL 11 MINUTE WHERE ref = ?',
                ['i', $test_user],
            );

            $incorrect_login = api_login($test_username, 'WrongPassword');
            $failures = ps_value('SELECT login_tries value FROM user WHERE ref = ?', ['i', $test_user], -1);
            $correct_login = api_login($test_username, $test_password);

            return $incorrect_login === false && (int) $failures === 1 && is_string($correct_login);
        },
        'expected' => true,
    ],
    [
        'name' => 'Three incorrect passwords in the UI also block API login',
        'test' => function () use ($test_username, $test_password) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if (perform_login($test_username, 'WrongPassword')['valid'] !== false) {
                    return false;
                }
            }

            return api_login($test_username, $test_password) === false;
        },
        'expected' => true,
    ],
    [
        'name' => 'Five attempts for unknown usernames lock the IP for API and UI login',
        'test' => function () use ($test_username, $test_password, $test_ip) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                if (api_login($test_username . '_missing', 'WrongPassword') !== false) {
                    return false;
                }
            }

            $before = ps_query('SELECT tries, last_try FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);
            $api_login = api_login($test_username, $test_password);
            $ui_login = perform_login($test_username, $test_password);
            $after = ps_query('SELECT tries, last_try FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);

            return (
                (int) $before[0]['tries'] === 5
                && $api_login === false
                && $ui_login['valid'] === false
                // Blocked attempts must not reset the counter or extend the lockout.
                && $after === $before
            );
        },
        'expected' => true,
    ],
    [
        'name' => 'After IP lockout expires, the correct password allows login again',
        'test' => function () use ($test_username, $test_password, $test_ip) {
            ps_query(
                'INSERT INTO ip_lockout (ip, tries, last_try) VALUES (?, 5, NOW() - INTERVAL 11 MINUTE)',
                ['s', $test_ip],
            );

            return is_string(api_login($test_username, $test_password));
        },
        'expected' => true,
    ],
    [
        'name' => 'After IP lockout expires, an unknown username starts a fresh failure counter',
        'test' => function () use ($test_username, $test_password, $test_ip) {
            ps_query(
                'INSERT INTO ip_lockout (ip, tries, last_try) VALUES (?, 5, NOW() - INTERVAL 11 MINUTE)',
                ['s', $test_ip],
            );

            $unknown_user_login = api_login($test_username . '_missing', 'WrongPassword');
            $failures = ps_value('SELECT tries value FROM ip_lockout WHERE ip = ?', ['s', $test_ip], -1);
            $correct_login = api_login($test_username, $test_password);

            return $unknown_user_login === false && (int) $failures === 1 && is_string($correct_login);
        },
        'expected' => true,
    ],
    [
        'name' => 'An incorrect API password waits for the configured delay before returning false',
        'test' => function () use ($test_username) {
            $GLOBALS['password_brute_force_delay'] = 1;
            $start = hrtime(true);
            $result = api_login($test_username, 'WrongPassword');

            return $result === false && (hrtime(true) - $start) >= 1000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'An unknown API username waits for the same delay before returning false',
        'test' => function () use ($test_username) {
            $GLOBALS['password_brute_force_delay'] = 1;
            $start = hrtime(true);
            $result = api_login($test_username . '_missing', 'WrongPassword');

            return $result === false && (hrtime(true) - $start) >= 1000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'An incorrect password through the shared UI login function waits for the configured delay',
        'test' => function () use ($test_username) {
            $GLOBALS['password_brute_force_delay'] = 1;
            $start = hrtime(true);
            $result = perform_login($test_username, 'WrongPassword');

            return $result['valid'] === false && (hrtime(true) - $start) >= 1000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'A disabled account with a correct password retains the failure delay',
        'test' => function () use ($test_username, $test_password, $test_user) {
            ps_query('UPDATE user SET approved = 2 WHERE ref = ?', ['i', $test_user]);
            $GLOBALS['password_brute_force_delay'] = 1;
            $start = hrtime(true);
            $result = api_login($test_username, $test_password);

            return $result === false && (hrtime(true) - $start) >= 1000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'An expired account with a correct password retains the failure delay',
        'test' => function () use ($test_username, $test_password, $test_user) {
            ps_query(
                'UPDATE user SET account_expires = NOW() - INTERVAL 1 DAY WHERE ref = ?',
                ['i', $test_user],
            );
            $GLOBALS['password_brute_force_delay'] = 1;
            $start = hrtime(true);
            $result = api_login($test_username, $test_password);

            return $result === false && (hrtime(true) - $start) >= 1000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'A successful API login does not wait for the configured failure delay',
        'test' => function () use ($test_username, $test_password) {
            $GLOBALS['password_brute_force_delay'] = 3;
            $start = hrtime(true);
            $result = api_login($test_username, $test_password);

            return is_string($result) && (hrtime(true) - $start) < 3000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'An already locked account returns without the password failure delay',
        'test' => function () use ($test_username, $test_password, $test_user) {
            ps_query(
                'UPDATE user SET login_tries = 3, login_last_try = NOW() WHERE ref = ?',
                ['i', $test_user],
            );
            $GLOBALS['password_brute_force_delay'] = 3;
            $start = hrtime(true);
            $result = api_login($test_username, $test_password);

            return $result === false && (hrtime(true) - $start) < 3000000000;
        },
        'expected' => true,
    ],
    [
        'name' => 'An already locked IP returns without the password failure delay',
        'test' => function () use ($test_username, $test_password, $test_ip) {
            ps_query('INSERT INTO ip_lockout (ip, tries, last_try) VALUES (?, 5, NOW())', ['s', $test_ip]);
            $GLOBALS['password_brute_force_delay'] = 3;
            $start = hrtime(true);
            $result = api_login($test_username, $test_password);

            return $result === false && (hrtime(true) - $start) < 3000000000;
        },
        'expected' => true,
    ],
];

foreach ($use_cases as $use_case) {
    // Only the delay-specific use cases need to wait.
    $GLOBALS['password_brute_force_delay'] = 0;
    // Start each use case with an unlocked account and no failures from this IP.
    ps_query('DELETE FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);
    ps_query(
        'UPDATE user SET `password` = ?, email = ?, approved = 1, account_expires = NULL, login_tries = 0, login_last_try = NULL, `session` = ? WHERE ref = ?',
        [
            's', rs_password_hash('RS' . $test_username . $test_password),
            's', $test_email,
            's', 'existing_session',
            'i', $test_user,
        ],
    );
    unset($GLOBALS['udata_cache']);

    if ($use_case['test']() !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}

// Tear down
ps_query('DELETE FROM user WHERE ref = ?', ['i', $test_user]);
ps_query('DELETE FROM ip_lockout WHERE ip = ?', ['s', $test_ip]);
foreach ($saved_ip_lockouts as $row) {
    ps_query(
        'INSERT INTO ip_lockout (ip, tries, last_try) VALUES (?, ?, ?)',
        ['s', $test_ip, 'i', $row['tries'], 's', $row['last_try']],
    );
}
foreach ($saved_login_globals as $login_global_name => $login_global_value) {
    $GLOBALS[$login_global_name] = $login_global_value;
}

unset(
    $test_username,
    $test_password,
    $test_user,
    $test_ip,
    $test_email,
    $saved_login_globals,
    $saved_ip_lockouts,
    $login_global_name,
    $login_global_value,
    $use_cases,
    $use_case,
    $row,
);

return true;

<?php

command_line_only();
/*
The order of config loading (overriding) is:-
- default
- deprecated
- config.php
...system wide (including after plugins' configs have loaded) + snapshot
- user group specific (as part of setup_user() call)
- user preferences (as part of authenticate.php, if successful)

Note: this is mostly dealing with GLOBAL "config" variables. ResourceSpace has the UI settings for system wide and user preferences only.
*/


// --- Set up
$original_user_ref = $userref;

$test_10601_setup_user = function (array $info) {
    unset($GLOBALS['udata_cache']);
    return get_user(get_user_by_username($info['username']) ?: new_user($info['username'], $info['usergroup'] ?? 3));
};

$user_SA = $test_10601_setup_user(['username' => 'test_10601_user1']);

$init_application_name = 'ResourceSpace - test #10601';
$system_wide_application_name = "{$init_application_name} -- system wide";
$system_wide_setup = function () use ($system_wide_application_name) {
    $GLOBALS['system_wide_config_options']['applicationname'] = $system_wide_application_name;
};
// --- End of Set up



$use_cases = [
    [
        'name' => 'Bad config option name',
        'input' => ['user_array' => $user_SA, 'name' => '', 'default' => null],
        'expected' => ['value' => null, 'return' => false],
    ],

    // System wide configs
    [
        'name' => 'Get system wide config option (globally, without using a default)',
        'setup' => function () use ($system_wide_application_name) {
            // The global scope is overriden with the system wide scope values (ie $GLOBALS['system_wide_config_options'])
            // Not an ideal scenario. We should aim to use the default if possible.
            $GLOBALS['applicationname'] = $system_wide_application_name;
        },
        'input' => ['user_array' => null, 'name' => 'applicationname', 'default' => null],
        'expected' => [
            'value' => $system_wide_application_name,
            'return' => false
        ],
    ],
    [
        'name' => 'Get the default value instead of the current (global) config option value',
        'input' => ['user_array' => null, 'name' => 'applicationname', 'default' => 'DefaultTakesPrecedence'],
        'expected' => [
            'value' => 'DefaultTakesPrecedence',
            'return' => false
        ],
    ],
    [
        'name' => 'No system wide config option, get default value',
        'setup' => function () {
            unset($GLOBALS['system_wide_config_options']['applicationname']);
        },
        'input' => ['user_array' => null, 'name' => 'applicationname', 'default' => 'test_10601_default_value'],
        'expected' => [
            'value' => 'test_10601_default_value',
            'return' => false
        ],
    ],
    [
        'name' => 'Get user group preference',
        'setup' => function () use ($user_SA) {
            set_usergroup_config_option($user_SA['usergroup'], 'applicationname', 'RS-SA-UG');
        },
        'input' => ['user_array' => $user_SA, 'name' => 'applicationname', 'default' => null],
        'expected' => ['value' => 'RS-SA-UG', 'return' => true],
    ],

    // User preferences (configs)
    [
        'name' => 'Get user preference (overrides user group preference)',
        'setup' => function () use ($user_SA) {
            set_config_option($user_SA['ref'], 'applicationname', 'RS-SA');
        },
        'input' => ['user_array' => $user_SA, 'name' => 'applicationname', 'default' => null],
        'expected' => ['value' => 'RS-SA', 'return' => true],
    ],
    [
        'name' => 'No user preference, needs to get system wide value (provided as default)',
        'setup' => function () use ($user_SA) {
            ps_query("DELETE FROM user_preferences WHERE user = ? AND parameter = 'applicationname'", ['i', $user_SA['ref']]);
            ps_query("DELETE FROM user_preferences WHERE usergroup = ? AND parameter = 'applicationname'", ['i', $user_SA['usergroup']]);
        },
        'input' => ['user_array' => $user_SA, 'name' => 'applicationname', 'default' => $system_wide_application_name],
        'expected' => ['value' => $system_wide_application_name, 'return' => false],
    ],
    [
        'name' => 'Fallback to the user group config override value (last config override)',
        'setup' => function () use ($test_10601_setup_user) {
            // Mock that by default the config is disabled (not necessary but it may prevent the test from failing if
            // config.default will be changed)
            $GLOBALS['user_pref_resource_notifications'] = false;
            $GLOBALS['system_wide_config_options']['user_pref_resource_notifications'] = false;

            // Get user group with config overrides
            $usergroup_w_overrides = get_usergroup(array_key_first(get_usergroups(false, 'test_10601_usergroup', true)));
            if ($usergroup_w_overrides === false) {
                $new_ug = save_usergroup(0, [
                    'name' => 'test_10601_usergroup',
                    'config_options' => '$user_pref_resource_notifications = true;',
                ]);
                resign_all_code(false, false);
                $usergroup_w_overrides = get_usergroup($new_ug);
            }

            // Get a user for whom those group config overrides should apply
            $user_w_group_overrides = $test_10601_setup_user(['username' => 'test_10601_user2', 'usergroup' => $usergroup_w_overrides['ref']]);
            setup_user($user_w_group_overrides);
        },
        'cleanup' => function () use ($original_user_ref) {
            setup_user(get_user($original_user_ref));
        },
        'input' => [
            // Defer getting the user ID until after we've set up the use case (otherwise the user might not exist)
            'user_array' => function () {
                return get_user(get_user_by_username('test_10601_user2'))['ref'];
            },
            'name' => 'user_pref_resource_notifications',
            'default' => null
        ],
        'expected' => ['value' => true, 'return' => false],
    ],
];


foreach ($use_cases as $use_case) {
    // Reset before testing this use case
    $GLOBALS['applicationname'] = $init_application_name;
    $system_wide_setup();

    // Set up the use case environment
    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }

    $uci_user_id = is_callable($use_case['input']['user_array']) ? $use_case['input']['user_array']() : $use_case['input']['user_array'];

    $uci_name = $use_case['input']['name'];
    $uci_default = $use_case['input']['default'];
    $returned_config_option_value = null;
    $config_type = array();
    if (isset($uci_user_id['ref'])) {
        $config_type['user'] = $uci_user_id['ref'];
    }
    if (isset($uci_user_id['usergroup'])) {
        $config_type['usergroup'] = $uci_user_id['usergroup'];
    }

    $result = get_config_option($config_type, $uci_name, $returned_config_option_value, $uci_default);

    // Debug use case
    $padding = 45;
    $js_result = str_pad(json_encode($result), $padding);
    $js_returned_config_option_value = str_pad(json_encode($returned_config_option_value), $padding);
    $js_return = json_encode($use_case['expected']['return']);
    $js_value = json_encode($use_case['expected']['value']);
    test_log(<<<EOL
### Use case: {$use_case['name']}...
Name         | Actual                                        | Expected
-------------|-----------------------------------------------|-------------------------------------------
fct return   | $js_result | $js_return
config value | $js_returned_config_option_value | $js_value
=========================================================================================================

EOL);

    // Clean up the environment. Prevent follow-up cases from failing because a use case modified it heavily.
    if (isset($use_case['cleanup'])) {
        $use_case['cleanup']();
    }

    if (!($use_case['expected']['return'] === $result && $use_case['expected']['value'] === $returned_config_option_value)) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset(
    $original_user_ref,
    $test_10601_setup_user,
    $user_SA,
    $init_application_name,
    $system_wide_application_name,
    $system_wide_setup,
    $use_cases
);

return true;

<?php

command_line_only();

// --- Set up
$original_state = $GLOBALS;
$setup_global_env = function () use ($original_state) {
    $GLOBALS['use_case_expected_logs'] = [];
    $GLOBALS['userpermissions'] = $original_state['userpermissions'];
};

$rtf_text = create_resource_type_field("Test #413 text", 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "test_413_text", false);
$rtf_drop = create_resource_type_field("Test #413 drop", 1, FIELD_TYPE_DROP_DOWN_LIST, "test_413_drop", false);
$rtf_chk = create_resource_type_field("Test #413 chk", 1, FIELD_TYPE_CHECK_BOX_LIST, "test_413_chk", false);
$rtf_radio = create_resource_type_field("Test #413 radio", 1, FIELD_TYPE_RADIO_BUTTONS, "test_413_radio", false);

$test413_resource_log = function (array $info) {
    ps_query(
        'INSERT INTO `resource_log`(`date`, `resource`, `type`, `resource_type_field`, notes) VALUES (?, ?, ?, ?, ?)',
        [
        's', $info['date'] ?? date('Y-m-d H:i:s'),
        'i', $info['resource'] ?? 413_000,
        's', $info['type'] ?? LOG_CODE_VIEWED,
        'i', $info['rtf'] ?? null,
        's', $info['notes'] ?? null,
        ]
    );
    return sql_insert_id();
};

$last_log_ref = ps_value('SELECT ref AS `value` FROM resource_log ORDER BY ref DESC LIMIT 1', [], 0);
// --- End of Set up



$use_cases = [
    [
        'name' => 'Filtering by minref returns the specified minimum ID',
        'setup' => function () use ($test413_resource_log) {
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['notes' => 'UC#1']);
        },
        'input' => ['minref' => ++$last_log_ref, 'days' => 7, 'maxrecords' => 0, 'fields' => [], 'log_codes' => []],
    ],
    [
        'name' => 'Get logs in the last 3 days',
        'setup' => function () use ($test413_resource_log) {
            ps_query('TRUNCATE resource_log');
            $test413_resource_log([
                'date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'notes' => 'UC#2: 3d'
            ]);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log([
                'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'notes' => 'UC#2: 2d'
            ]);
        },
        'input' => ['minref' => 0, 'days' => 3, 'maxrecords' => 0, 'fields' => [], 'log_codes' => []],
    ],
    [
        'name' => 'Return only a certain number of records back',
        'setup' => function () use ($test413_resource_log) {
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['notes' => 'UC#3']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['notes' => 'UC#3']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['notes' => 'UC#3']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 3, 'fields' => [], 'log_codes' => []],
    ],
    [
        'name' => 'Filter by metadata field',
        'setup' => function () use ($rtf_text, $test413_resource_log) {
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['rtf' => $rtf_text, 'notes' => 'UC#4']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [$rtf_text], 'log_codes' => []],
    ],
    [
        'name' => 'Filter by log code',
        'setup' => function () use ($test413_resource_log) {
            $test413_resource_log(['type' => LOG_CODE_COPIED, 'notes' => 'UC#5']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['type' => LOG_CODE_DELETED, 'notes' => 'UC#5']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [], 'log_codes' => [LOG_CODE_DELETED]],
    ],
    [
        'name' => 'Enforce access control',
        'setup' => function () use ($test413_resource_log) {
            $test413_resource_log(['notes' => 'UC#6']);
            $GLOBALS['userpermissions'] = array_diff($GLOBALS['userpermissions'], ['v']);
        },
        'input' => ['minref' => 0, 'days' => 7, 'maxrecords' => 0, 'fields' => [], 'log_codes' => []],
    ],
    [
        'name' => 'Filter by multiple metadata fields',
        'setup' => function () use ($rtf_chk, $rtf_drop, $test413_resource_log) {
            $GLOBALS['userpermissions'][] = 'v';
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['rtf' => $rtf_chk, 'notes' => 'UC#7-checkbox']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['rtf' => $rtf_drop, 'notes' => 'UC#7-dropdown']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [$rtf_drop, $rtf_chk], 'log_codes' => []],
    ],
    [
        'name' => 'Filter by multiple log codes',
        'setup' => function () use ($test413_resource_log) {
            $test413_resource_log(['type' => LOG_CODE_COPIED, 'notes' => 'UC#8']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['type' => LOG_CODE_LOCKED, 'notes' => 'UC#8']);
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['type' => LOG_CODE_EMAILED, 'notes' => 'UC#8']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [], 'log_codes' => [LOG_CODE_LOCKED, LOG_CODE_EMAILED]],
    ],
    [
        'name' => 'Invalid metadata fields',
        'setup' => function () use ($rtf_radio, $test413_resource_log) {
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['rtf' => $rtf_radio, 'notes' => 'UC#9']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [0, 23.34, 'test', $rtf_radio], 'log_codes' => []],
    ],
    [
        'name' => 'Invalid log codes',
        'setup' => function () use ($test413_resource_log) {
            $GLOBALS['use_case_expected_logs'][] = $test413_resource_log(['type' => LOG_CODE_CREATED, 'notes' => 'UC#10']);
        },
        'input' => ['minref' => 0, 'days' => 2, 'maxrecords' => 0, 'fields' => [], 'log_codes' => ['t413UC10', LOG_CODE_CREATED]],
    ],
];
foreach ($use_cases as $use_case) {
    $setup_global_env();
    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }

    // We use the notes just for debugging the use cases
    $result = array_column(
        resource_log_last_rows(
            $use_case['input']['minref'],
            $use_case['input']['days'],
            $use_case['input']['maxrecords'],
            $use_case['input']['fields'],
            $use_case['input']['log_codes']
        ),
        'notes',
        'ref'
    );
    if (array_keys($result) !== $GLOBALS['use_case_expected_logs']) {
        echo "Use case: {$use_case['name']} - ";
        printf(PHP_EOL . '$result: %s = %s' . PHP_EOL, gettype($result), print_r($result, true));
        printf(PHP_EOL . 'use_case_expected_log = %s' . PHP_EOL, print_r($GLOBALS['use_case_expected_logs'], true));
        return false;
    }
}



// Tear down
unset(
    $original_state,
    $setup_global_env,
    $rtf_text,
    $test413_resource_log,
    $last_log_ref,
    $use_cases,
    $result
);

return true;

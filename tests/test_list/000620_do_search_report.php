<?php

command_line_only();

// --- Set up
$run_id = test_generate_random_ID(10);
$original_userpermissions = $userpermissions;

// Resources
for ($i = 0; $i < 5; $i++) {
    create_resource(1, 0, -1, "test_966-{$run_id}");
}
create_resource(2, 0, -1, "test_966-{$run_id}"); # A "Document" resource

// Reports
ps_query(
    'INSERT into report (`name`, `query`, support_non_correlated_sql) VALUES (?, ?, ?)',
    [
        's', "(test_966-$run_id) all relevant resources",
        's', "SELECT rl.`resource` AS 'thumbnail' FROM resource_log AS rl WHERE notes = 'test_966-{$run_id}'",
        'i', 0
    ]
);
$report_id_created_resources = sql_insert_id();
// - reports not applicable but which could still be run by mistake
ps_query(
    'INSERT into report (`name`, `query`, support_non_correlated_sql) VALUES (?, ?, ?)',
    [
        's', "(test_966-$run_id) non-correlated sql",
        's', "  SELECT r.ref AS 'Resource ID',
         (
            SELECT `value`
              FROM resource_data AS rd
             WHERE rd.resource = r.ref
               AND rd.resource_type_field = [title_field]
             LIMIT 1
         ) AS 'Title', 
         count(*) AS 'Downloads' 
    FROM resource_log rl
    JOIN resource r on rl.resource = r.ref
   WHERE rl.type = 'd'
     AND rl.date >= date('[from-y]-[from-m]-[from-d]')
     AND rl.date <= adddate(date('[to-y]-[to-m]-[to-d]'), 1)
     AND r.ref IN [non_correlated_sql]
GROUP BY r.ref
ORDER BY 'Downloads' DESC",
        'i', 1
    ]
);
$report_id_ncsql = sql_insert_id();
ps_query(
    'INSERT into report (`name`, `query`, support_non_correlated_sql) VALUES (?, ?, ?)',
    [
        's', "(test_966-$run_id) pending submissions",
        's', 'SELECT * FROM resource WHERE archive = -2;',
        'i', 0
    ]
);
$report_id_pending_submissions = sql_insert_id();
// --- End of Set up



$use_cases = [
    [
        'name' => 'Invalid (non-existent) report ID',
        'input' => ['!report99999'],
        'expected' => 0,
    ],
    [
        'name' => 'Invalid (supports non-correlated SQL) report ID',
        'input' => ["!report{$report_id_ncsql}"],
        'expected' => 0,
    ],
    [
        'name' => 'Invalid (no thumbnail column) report ID',
        'input' => ["!report{$report_id_pending_submissions}"],
        'expected' => 0,
    ],
    [
        'name' => 'Access control prevents user from viewing report as search results',
        'setup' => function () {
            $GLOBALS['userpermissions'] = array_values(array_diff($GLOBALS['userpermissions'], ['t']));
        },
        'input' => ["!report{$report_id_created_resources}"],
        'expected' => 0,
    ],
    [
        'name' => 'Valid report ID should get all resources',
        'input' => ["!report{$report_id_created_resources}"],
        'expected' => 6,
    ],
    [
        'name' => 'Filter report by resource type "Document"',
        'input' => ["!report{$report_id_created_resources}", '2'],
        'expected' => 1,
    ],
];
foreach ($use_cases as $use_case) {
    // Reset before testing this use case
    $userpermissions = $original_userpermissions;

    // Set up the use case environment
    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }

    $results = do_search(...$use_case['input']);
    if (count($results) !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
$userpermissions = $original_userpermissions;
unset(
    $run_id,
    $original_userpermissions,
    $report_id_created_resources,
    $report_id_ncsql,
    $report_id_pending_submissions,
    $use_cases,
    $results
);

return true;

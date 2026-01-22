<?php

command_line_only();


// Check basic date search (based on searchbar)
// Save current setings
$saved_date_field = $date_field;

$resourcea  = create_resource(1, 0);
$resourceb  = create_resource(1, 0);
$resourcec  = create_resource(1, 0);

// create new date range field and use as date field
$newdatefield   = create_resource_type_field("Basic date test", 0, FIELD_TYPE_DATE, "testbasicdate");
$date_field     = $newdatefield;

update_field($resourcea, $newdatefield, "2017-07-15");
update_field($resourceb, $newdatefield, "2017-07-02");
update_field($resourcec, $newdatefield, "2017-04-08");

$test_cases = [
    'SUBTEST A' => [
        'year'   => '2017',
        'month'  => '07',
        'day'    => '15',
        'search' => false,
        'result' => 'basicyear:2017, basicmonth:07, basicday:15'
    ],
    'SUBTEST B' => [
        'year'   => '2017',
        'month'  => '07',
        'day'    => '15',
        'search' => true,
        'result' => [
            'count'     => 1,
            'contains'  => [$resourcea]
            ]
    ],
    'SUBTEST C' => [
        'year'   => '2017',
        'month'  => '07',
        'search' => true,
        'result' => [
            'count'     => 2,
            'contains'  => [$resourcea, $resourceb]
            ]
    ],
    'SUBTEST D' => [
        'month'  => '07',
        'day'    => '02',
        'search' => true,
        'result' => [
            'count'     => 1,
            'contains'  => [$resourceb]
            ]
    ],
    'SUBTEST E' => [
        'year'   => '2017',
        'day'    => '08',
        'search' => true,
        'result' => [
            'count'     => 1,
            'contains'  => [$resourcec]
            ]
    ],
    'SUBTEST F' => [
        'year'   => '2017',
        'search' => true,
        'result' => [
            'count'     => 3,
            'contains'  => [$resourcea, $resourceb, $resourcec]
            ]
    ],
    'SUBTEST G' => [
        'month'  => '07',
        'search' => true,
        'result' => [
            'count'     => 2,
            'contains'  => [$resourcea, $resourceb]
            ]
    ],
    'SUBTEST H' => [
        'day'    => '08',
        'search' => true,
        'result' => [
            'count'     => 1,
            'contains'  => [$resourcec]
            ]
    ]
];

foreach ($test_cases as $test_name => $test_case) {
    $search = "";
    $_POST["basicyear"]     = $test_case['year'] ?? "";
    $_POST["basicmonth"]    = $test_case['month'] ?? "";
    $_POST["basicday"]      = $test_case['day'] ?? "";

    $search = update_search_from_request($search);
    if (!$test_case['search']) {
        if ($search !== $test_case['result']) {
            echo 'ERROR - ' . $test_name . "\n";
            return false;
        }
    } else {
        $results = do_search($search);
        if (count($results) !== $test_case['result']['count']) {
            echo 'ERROR - ' . $test_name . "\n";
            return false;
        } else {
            $search_refs = array_column($results, 'ref');
            foreach ($test_case['result']['contains'] as $ref) {
                if (!in_array($ref, $search_refs)) {
                    echo 'ERROR - ' . $test_name . "\n";
                    return false;
                }
            }
        }
    }
}
$date_field = $saved_date_field;

return true;

<?php

command_line_only();

// --- Set up
include_once dirname(__DIR__, 2) . '/include/reporting_functions.php';
$reporting_periods_default = [3, 5, 7];



$use_cases = [
    [
        'name' => 'Invalid period provided should fallback to the first default value',
        'input' => [
            'period' => 9999,
        ],
        'expected' => [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 3)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 3)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 3)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    [
        'name' => 'Predefined period (5 days)',
        'input' => [
            'period' => 5,
        ],
        'expected' => [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 5)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 5)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 5)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    [
        'name' => 'Specific number of days (2 days)',
        'input' => [
            'period' => 0,
            'period_days' => '2',
        ],
        'expected' => [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 2)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 2)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 2)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    [
        'name' => 'Invalid specific number of days should fallback to 1 day',
        'input' => [
            'period' => 0,
            'period_days' => '',
        ],
        'expected' => [
            'from_year' => date('Y', time() - (60 * 60 * 24)),
            'from_month' => date('m', time() - (60 * 60 * 24)),
            'from_day' => date('d', time() - (60 * 60 * 24)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    [
        'name' => 'Specific date range',
        'input' => [
            'period' => -1,
            'from-y' => 2000,
            'from-m' => 03,
            'from-d' => 06,
            'to-y' => 2023,
            'to-m' => 03,
            'to-d' => 16,
        ],
        'expected' => [
            'from_year' => 2000,
            'from_month' => 3,
            'from_day' => 6,
            'to_year' => 2023,
            'to_month' => 3,
            'to_day' => 16,
        ],
    ],
];
foreach ($use_cases as $use_case) {
    if ($use_case['expected'] !== report_process_period($use_case['input'])) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset($use_cases);

return true;

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/include/reporting_functions.php';

$saved_report_periods = [];
beforeEach(function () use (&$saved_report_periods): void {
    $saved_report_periods = array_intersect_key($GLOBALS, ['reporting_periods_default' => true]);
    $GLOBALS['reporting_periods_default'] = [3, 5, 7];
});

afterEach(function () use (&$saved_report_periods): void {
    unset($GLOBALS['reporting_periods_default']);
    foreach ($saved_report_periods as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('calculates the report date range', function (array $input, array $expected): void {
    expect(report_process_period($input))->toBe($expected);
})->with([
    'Invalid period provided should fallback to the first default value' => [
        [
            'period' => 9999,
        ],
        [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 3)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 3)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 3)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    'Predefined period (5 days)' => [
        [
            'period' => 5,
        ],
        [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 5)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 5)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 5)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    'Specific number of days (2 days)' => [
        [
            'period' => 0,
            'period_days' => '2',
        ],
        [
            'from_year' => date('Y', time() - (60 * 60 * 24 * 2)),
            'from_month' => date('m', time() - (60 * 60 * 24 * 2)),
            'from_day' => date('d', time() - (60 * 60 * 24 * 2)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    'Invalid specific number of days should fallback to 1 day' => [
        [
            'period' => 0,
            'period_days' => '',
        ],
        [
            'from_year' => date('Y', time() - (60 * 60 * 24)),
            'from_month' => date('m', time() - (60 * 60 * 24)),
            'from_day' => date('d', time() - (60 * 60 * 24)),
            'to_year' => date('Y'),
            'to_month' => date('m'),
            'to_day' => date('d'),
        ],
    ],
    'Specific date range' => [
        [
            'period' => -1,
            'from-y' => 2000,
            'from-m' => 03,
            'from-d' => 06,
            'to-y' => 2023,
            'to-m' => 03,
            'to-d' => 16,
        ],
        [
            'from_year' => 2000,
            'from_month' => 3,
            'from_day' => 6,
            'to_year' => 2023,
            'to_month' => 3,
            'to_day' => 16,
        ],
    ],
    'Specific date range supports numeric looking values' => [
        [
            'period' => -1,
            'from-y' => '2026',
            'from-m' => 03,
            'from-d' => 06,
            'to-y' => 'not a number',
            'to-m' => 03,
            'to-d' => 16,
        ],
        [
            'from_year' => 2026,
            'from_month' => 3,
            'from_day' => 6,
            'to_year' => 0,
            'to_month' => 3,
            'to_day' => 16,
        ],
    ],
]);

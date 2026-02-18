<?php

command_line_only();

$use_cases = [
    // Empty / optional input
    [
        'name' => 'Empty input, not optional',
        'input' => [
            'input' => '',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                $lang['int_ranges_empty'],
            ],
        ],
    ],
    // Single numbers
    [
        'name' => 'Single zero is invalid',
        'input' => [
            'input' => '0',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '0',
                    $lang['int_ranges_single_below_1']
                ),
            ],
        ],
    ],
    [
        'name' => 'Single above max',
        'input' => [
            'input' => '5',
            'max_val' => 4,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['5', 4],
                    $lang['int_ranges_single_above_max']
                ),
            ],
        ],
    ],
    // Numeric ranges
    [
        'name' => 'Reversed numeric range',
        'input' => [
            'input' => '3-1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '3-1',
                    $lang['int_ranges_range_reversed']
                ),
            ],
        ],
    ],
    [
        'name' => 'Range below 1',
        'input' => [
            'input' => '0-3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '0-3',
                    $lang['int_ranges_range_below_1']
                ),
            ],
        ],
    ],
    [
        'name' => 'Range above max',
        'input' => [
            'input' => '2-5',
            'max_val' => 4,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['2-5', 4],
                    $lang['int_ranges_range_above_max']
                ),
            ],
        ],
    ],
    // Wildcard ranges
    [
        'name' => 'Wildcard disabled',
        'input' => [
            'input' => '9-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '9-*',
                    $lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    [
        'name' => 'Wildcard start above max',
        'input' => [
            'input' => '11-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['11-*', 10],
                    $lang['int_ranges_range_above_max']
                ),
            ],
        ],
    ],
    [
        'name' => 'Sole wildcard "*" is valid when allow_wildcard=true',
        'input' => [
            'input' => '*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Whitespace around sole "*" is still sole input and valid when allow_wildcard=true',
        'input' => [
            'input' => '   *   ',
            'max_val' => 123,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Sole wildcard "*" is invalid when allow_wildcard=false',
        'input' => [
            'input' => '*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $lang['int_ranges_not_valid']),
            ],
        ],
    ],
    // Invalid wildcard forms
    [
        'name' => 'Wildcard on left side',
        'input' => [
            'input' => '*-3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '*-3',
                    $lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    [
        'name' => '"*" inside a list is invalid (even if allow_wildcard=true)',
        'input' => [
            'input' => '1,*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $lang['int_ranges_not_valid']),
            ],
        ],
    ],
    [
        'name' => '"*" inside a list is invalid and other parts can also error',
        'input' => [
            'input' => '*,0,3-1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '0', $lang['int_ranges_single_below_1']),
                str_replace('%%PART%%', '3-1', $lang['int_ranges_range_reversed']),
            ],
        ],
    ],
    // Mixed input
    [
        'name' => 'Mixed with invalid part',
        'input' => [
            'input' => '1,2,5-7,*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '*',
                    $lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    // Garbage
    [
        'name' => 'Alphabetic input',
        'input' => [
            'input' => 'abc',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    'abc',
                    $lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    [
        'name' => 'Multiple invalid singles (below 1 + above max)',
        'input' => [
            'input' => '0,6',
            'max_val' => 5,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '0', $lang['int_ranges_single_below_1']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['6', 5], $lang['int_ranges_single_above_max']),
            ],
        ],
    ],

    [
        'name' => 'Multiple invalid ranges (reversed + below 1)',
        'input' => [
            'input' => '5-3,0-2',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '5-3', $lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', '0-2', $lang['int_ranges_range_below_1']),
            ],
        ],
    ],

    [
        'name' => 'Mixed invalid: not-valid token + reversed range + below-1 single',
        'input' => [
            'input' => 'abc,9-1,0',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', 'abc', $lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '9-1', $lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', '0', $lang['int_ranges_single_below_1']),
            ],
        ],
    ],

    [
        'name' => 'Wildcard disabled + above max single + above max range',
        'input' => [
            'input' => '9-*,6,3-8',
            'max_val' => 5,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '9-*', $lang['int_ranges_not_valid']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['6', 5], $lang['int_ranges_single_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['3-8', 5], $lang['int_ranges_range_above_max']),
            ],
        ],
    ],

    [
        'name' => 'Wildcard allowed but start above max + invalid wildcard forms',
        'input' => [
            'input' => '11-*,*-3,*-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['11-*', 10], $lang['int_ranges_range_above_max']),
                str_replace('%%PART%%', '*-3', $lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '*-*', $lang['int_ranges_not_valid']),
            ],
        ],
    ],

    [
        'name' => 'Max-val enforced: range end above max + single above max + range start above max',
        'input' => [
            'input' => '2-9,7,12-13',
            'max_val' => 6,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['2-9', 6], $lang['int_ranges_range_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['7', 6], $lang['int_ranges_single_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['12-13', 6], $lang['int_ranges_range_above_max']),
            ],
        ],
    ],

    [
        'name' => 'Whitespace + empty segments ignored, but multiple real errors remain',
        'input' => [
            'input' => ' , 0 , 4-2 , abc , ',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '0', $lang['int_ranges_single_below_1']),
                str_replace('%%PART%%', '4-2', $lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', 'abc', $lang['int_ranges_not_valid']),
            ],
        ],
    ],
    // Valid inputs
    [
        'name' => 'Valid single number',
        'input' => [
            'input' => '3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [3],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Valid multiple numbers',
        'input' => [
            'input' => '5,7,90',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [5,7,90],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Valid range',
        'input' => [
            'input' => '5-11',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Valid multiple ranges',
        'input' => [
            'input' => '1-3,5-11',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [1,2,3,5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Valid multiple overlapping ranges',
        'input' => [
            'input' => '5-11,7-9',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Valid mix',
        'input' => [
            'input' => '6,5-11,7-9,12,1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'numbers' => [1,5,6,7,8,9,10,11,12],
            'errors' => [],
        ],
    ],

];

foreach ($use_cases as $use_case) {
    $output = parse_int_ranges($use_case['input']['input'],
                                $use_case['input']['max_val'],
                                $use_case['input']['optional'],
                                $use_case['input']['allow_wildcard']);

    if ($use_case['expected'] !== $output) {
        echo "Use case: {$use_case['name']} - ";
        test_log("Output: " . print_r($output, true));
        test_log("Expected = " . print_r($use_case['expected'], true));
        test_log('--- ');
        return false;
    }
}

//Tests all pass so return true
return true;

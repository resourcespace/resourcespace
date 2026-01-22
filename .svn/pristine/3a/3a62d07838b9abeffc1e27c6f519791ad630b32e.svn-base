<?php

command_line_only();

$use_cases = [

    // Empty input
    [
        'name' => 'Empty input returns error',
        'input' => [
            'input' => '',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => ['Input is empty.'],
        ],
    ],
    // Field validation
    [
        'name' => 'Invalid field name is rejected',
        'input' => [
            'input' => '1,2,3',
            'field' => 'id; DROP TABLE users;--',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => ['Invalid field name.'],
        ],
    ],
    [
        'name' => 'Valid field name with table prefix',
        'input' => [
            'input' => '1',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref = ?)',
            'params' => ['i', 1],
            'errors' => [],
        ],
    ],
    // Single numbers
    [
        'name' => 'Single number builds equality condition',
        'input' => [
            'input' => '3',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref = ?)',
            'params' => ['i', 3],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Single zero is invalid',
        'input' => [
            'input' => '0',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Values must be 1 or greater ("0").',
            ],
        ],
    ],
    [
        'name' => 'Single above max is invalid',
        'input' => [
            'input' => '6',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Value "6" exceeds maximum of 5.',
            ],
        ],
    ],
    // Numeric ranges
    [
        'name' => 'Numeric range builds BETWEEN condition',
        'input' => [
            'input' => '5-9',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 5, 'i', 9],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Range with spaces builds BETWEEN condition',
        'input' => [
            'input' => ' 5 - 9 ',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 5, 'i', 9],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Reversed range is invalid',
        'input' => [
            'input' => '9-5',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Start of range "9-5" cannot be greater than end.',
            ],
        ],
    ],
    [
        'name' => 'Range below 1 is invalid',
        'input' => [
            'input' => '0-2',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Values must be 1 or greater in range "0-2".',
            ],
        ],
    ],
    [
        'name' => 'Range exceeds max is invalid',
        'input' => [
            'input' => '2-6',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Range "2-6" exceeds maximum value of 5.',
            ],
        ],
    ],
    // Wildcard ranges (start-*)
    [
        'name' => 'Wildcard disabled rejects start-*',
        'input' => [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"9-*" is not a valid number or range (use formats like 3 or 5-9).',
            ],
        ],
    ],
    [
        'name' => 'Wildcard enabled with max_val > 0 uses BETWEEN start and max_val',
        'input' => [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 9, 'i', 10],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Wildcard enabled with max_val = 0 uses >= start',
        'input' => [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref >= ?)',
            'params' => ['i', 9],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Wildcard start above max is invalid (bounded)',
        'input' => [
            'input' => '11-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Range "11-*" exceeds maximum value of 10.',
            ],
        ],
    ],
        [
        'name' => 'Sole wildcard "*" is valid when allow_wildcard=true',
        'input' => [
            'input' => '*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(1=1)',
            'params' => [],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Sole wildcard "*" is invalid when allow_wildcard=false',
        'input' => [
            'input' => '*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    [
        'name' => '"*" inside a list is invalid (even if allow_wildcard=true)',
        'input' => [
            'input' => '1,*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    [
        'name' => '"*" inside a list plus other invalid parts yields multiple errors',
        'input' => [
            'input' => '*,0,9-1,abc',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
                'Values must be 1 or greater ("0").',
                'Start of range "9-1" cannot be greater than end.',
                '"abc" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    [
        'name' => 'Whitespace around sole "*" is still sole input and valid when allow_wildcard=true',
        'input' => [
            'input' => '   *   ',
            'field' => 't.id',
            'max_val' => 999,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(1=1)',
            'params' => [],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Wildcard on left side is invalid',
        'input' => [
            'input' => '*-3',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*-3" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    [
        'name' => 'Double wildcard range is invalid',
        'input' => [
            'input' => '*-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*-*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    // Mixed / comma-separated success cases
    [
        'name' => 'Mixed values produce OR-joined where and params in order',
        'input' => [
            'input' => '1,2,5-7,9-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref = ? OR r.ref = ? OR r.ref BETWEEN ? AND ? OR r.ref >= ?)',
            'params' => ['i', 1, 'i', 2, 'i', 5, 'i', 7, 'i', 9],
            'errors' => [],
        ],
    ],
    [
        'name' => 'Empty segments are ignored',
        'input' => [
            'input' => '1,,2',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => true,
            'where' => '(r.ref = ? OR r.ref = ?)',
            'params' => ['i', 1, 'i', 2],
            'errors' => [],
        ],
    ],
    // Multiple errors
    [
        'name' => 'Multiple errors: invalid token + reversed range + below-1 single',
        'input' => [
            'input' => 'abc,9-1,0',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"abc" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
                'Start of range "9-1" cannot be greater than end.',
                'Values must be 1 or greater ("0").',
            ],
        ],
    ],
    [
        'name' => 'Multiple errors: wildcard disabled + above-max single + above-max range',
        'input' => [
            'input' => '9-*,6,3-8',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        'expected' => [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"9-*" is not a valid number or range (use formats like 3 or 5-9).',
                'Value "6" exceeds maximum of 5.',
                'Range "3-8" exceeds maximum value of 5.',
            ],
        ],
    ],

];

foreach ($use_cases as $use_case) {
    $output = build_range_where_condition($use_case['input']['input'],
                                            $use_case['input']['field'],
                                            $use_case['input']['max_val'],
                                            $use_case['input']['allow_wildcard']);

    if ($use_case['expected'] != $output) {
        echo "Use case: {$use_case['name']} - ";
        test_log("Output: " . print_r($output, true));
        test_log("Expected = " . print_r($use_case['expected'], true));
        test_log('--- ');
        return false;
    }
}

//Tests all pass so return true
return true;

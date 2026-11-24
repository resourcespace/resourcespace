<?php

declare(strict_types=1);

it('builds the SQL condition and parameters for integer ranges', function (array $input, array $expected): void {
    expect(build_range_where_condition(
        $input['input'],
        $input['field'],
        $input['max_val'],
        $input['allow_wildcard']
    ))->toEqual($expected);
})->with([
    // Empty input
    'Empty input returns error' => [
        [
            'input' => '',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => ['Input is empty.'],
        ],
    ],
    // Field validation
    'Invalid field name is rejected' => [
        [
            'input' => '1,2,3',
            'field' => 'id; DROP TABLE users;--',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => ['Invalid field name.'],
        ],
    ],
    'Valid field name with table prefix' => [
        [
            'input' => '1',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'where' => '(r.ref = ?)',
            'params' => ['i', 1],
            'errors' => [],
        ],
    ],
    // Single numbers
    'Single number builds equality condition' => [
        [
            'input' => '3',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'where' => '(r.ref = ?)',
            'params' => ['i', 3],
            'errors' => [],
        ],
    ],
    'Single zero is invalid' => [
        [
            'input' => '0',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Values must be 1 or greater ("0").',
            ],
        ],
    ],
    'Single above max is invalid' => [
        [
            'input' => '6',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Value "6" exceeds maximum of 5.',
            ],
        ],
    ],
    // Numeric ranges
    'Numeric range builds BETWEEN condition' => [
        [
            'input' => '5-9',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 5, 'i', 9],
            'errors' => [],
        ],
    ],
    'Range with spaces builds BETWEEN condition' => [
        [
            'input' => ' 5 - 9 ',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 5, 'i', 9],
            'errors' => [],
        ],
    ],
    'Reversed range is invalid' => [
        [
            'input' => '9-5',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Start of range "9-5" cannot be greater than end.',
            ],
        ],
    ],
    'Range below 1 is invalid' => [
        [
            'input' => '0-2',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Values must be 1 or greater in range "0-2".',
            ],
        ],
    ],
    'Range exceeds max is invalid' => [
        [
            'input' => '2-6',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Range "2-6" exceeds maximum value of 5.',
            ],
        ],
    ],
    // Wildcard ranges (start-*)
    'Wildcard disabled rejects start-*' => [
        [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"9-*" is not a valid number or range (use formats like 3 or 5-9).',
            ],
        ],
    ],
    'Wildcard enabled with max_val > 0 uses BETWEEN start and max_val' => [
        [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'where' => '(r.ref BETWEEN ? AND ?)',
            'params' => ['i', 9, 'i', 10],
            'errors' => [],
        ],
    ],
    'Wildcard enabled with max_val = 0 uses >= start' => [
        [
            'input' => '9-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'where' => '(r.ref >= ?)',
            'params' => ['i', 9],
            'errors' => [],
        ],
    ],
    'Wildcard start above max is invalid (bounded)' => [
        [
            'input' => '11-*',
            'field' => 'r.ref',
            'max_val' => 10,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                'Range "11-*" exceeds maximum value of 10.',
            ],
        ],
    ],
    'Sole wildcard "*" is valid when allow_wildcard=true' => [
        [
            'input' => '*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'where' => '(1=1)',
            'params' => [],
            'errors' => [],
        ],
    ],
    'Sole wildcard "*" is invalid when allow_wildcard=false' => [
        [
            'input' => '*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    '"*" inside a list is invalid (even if allow_wildcard=true)' => [
        [
            'input' => '1,*',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    '"*" inside a list plus other invalid parts yields multiple errors' => [
        [
            'input' => '*,0,9-1,abc',
            'field' => 'id',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
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
    'Whitespace around sole "*" is still sole input and valid when allow_wildcard=true' => [
        [
            'input' => '   *   ',
            'field' => 't.id',
            'max_val' => 999,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'where' => '(1=1)',
            'params' => [],
            'errors' => [],
        ],
    ],
    'Wildcard on left side is invalid' => [
        [
            'input' => '*-3',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*-3" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    'Double wildcard range is invalid' => [
        [
            'input' => '*-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'where' => null,
            'params' => [],
            'errors' => [
                '"*-*" is not a valid number or range (use formats like 3, 5-9, or 9-*).',
            ],
        ],
    ],
    // Mixed / comma-separated success cases
    'Mixed values produce OR-joined where and params in order' => [
        [
            'input' => '1,2,5-7,9-*',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'where' => '(r.ref = ? OR r.ref = ? OR r.ref BETWEEN ? AND ? OR r.ref >= ?)',
            'params' => ['i', 1, 'i', 2, 'i', 5, 'i', 7, 'i', 9],
            'errors' => [],
        ],
    ],
    'Empty segments are ignored' => [
        [
            'input' => '1,,2',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'where' => '(r.ref = ? OR r.ref = ?)',
            'params' => ['i', 1, 'i', 2],
            'errors' => [],
        ],
    ],
    // Multiple errors
    'Multiple errors: invalid token + reversed range + below-1 single' => [
        [
            'input' => 'abc,9-1,0',
            'field' => 'r.ref',
            'max_val' => 0,
            'allow_wildcard' => true,
        ],
        [
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
    'Multiple errors: wildcard disabled + above-max single + above-max range' => [
        [
            'input' => '9-*,6,3-8',
            'field' => 'r.ref',
            'max_val' => 5,
            'allow_wildcard' => false,
        ],
        [
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
]);

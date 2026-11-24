<?php

declare(strict_types=1);

$range_lang = (static function (): array {
    require dirname(__DIR__, 2) . '/languages/en.php';
    return $lang;
})();

$saved_range_lang = [];
beforeEach(function () use (&$saved_range_lang, $range_lang): void {
    $saved_range_lang = array_intersect_key($GLOBALS, ['lang' => true]);
    $GLOBALS['lang'] = $range_lang;
});

afterEach(function () use (&$saved_range_lang): void {
    unset($GLOBALS['lang']);
    if (array_key_exists('lang', $saved_range_lang)) {
        $GLOBALS['lang'] = $saved_range_lang['lang'];
    }
});

it('parses integer ranges', function (array $input, array $expected): void {
    expect(parse_int_ranges(
        $input['input'],
        $input['max_val'],
        $input['optional'],
        $input['allow_wildcard']
    ))->toBe($expected);
})->with([
    // Empty / optional input
    'Empty input, not optional' => [
        [
            'input' => '',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                $range_lang['int_ranges_empty'],
            ],
        ],
    ],
    // Single numbers
    'Single zero is invalid' => [
        [
            'input' => '0',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '0',
                    $range_lang['int_ranges_single_below_1']
                ),
            ],
        ],
    ],
    'Single above max' => [
        [
            'input' => '5',
            'max_val' => 4,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['5', 4],
                    $range_lang['int_ranges_single_above_max']
                ),
            ],
        ],
    ],
    // Numeric ranges
    'Reversed numeric range' => [
        [
            'input' => '3-1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '3-1',
                    $range_lang['int_ranges_range_reversed']
                ),
            ],
        ],
    ],
    'Range below 1' => [
        [
            'input' => '0-3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '0-3',
                    $range_lang['int_ranges_range_below_1']
                ),
            ],
        ],
    ],
    'Range above max' => [
        [
            'input' => '2-5',
            'max_val' => 4,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['2-5', 4],
                    $range_lang['int_ranges_range_above_max']
                ),
            ],
        ],
    ],
    // Wildcard ranges
    'Wildcard disabled' => [
        [
            'input' => '9-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '9-*',
                    $range_lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    'Wildcard start above max' => [
        [
            'input' => '11-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    ['%%PART%%', '%%MAX_VAL%%'],
                    ['11-*', 10],
                    $range_lang['int_ranges_range_above_max']
                ),
            ],
        ],
    ],
    'Sole wildcard "*" is valid when allow_wildcard=true' => [
        [
            'input' => '*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'numbers' => [],
            'errors' => [],
        ],
    ],
    'Whitespace around sole "*" is still sole input and valid when allow_wildcard=true' => [
        [
            'input' => '   *   ',
            'max_val' => 123,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => true,
            'numbers' => [],
            'errors' => [],
        ],
    ],
    'Sole wildcard "*" is invalid when allow_wildcard=false' => [
        [
            'input' => '*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $range_lang['int_ranges_not_valid']),
            ],
        ],
    ],
    // Invalid wildcard forms
    'Wildcard on left side' => [
        [
            'input' => '*-3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '*-3',
                    $range_lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    '"*" inside a list is invalid (even if allow_wildcard=true)' => [
        [
            'input' => '1,*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $range_lang['int_ranges_not_valid']),
            ],
        ],
    ],
    '"*" inside a list is invalid and other parts can also error' => [
        [
            'input' => '*,0,3-1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '*', $range_lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '0', $range_lang['int_ranges_single_below_1']),
                str_replace('%%PART%%', '3-1', $range_lang['int_ranges_range_reversed']),
            ],
        ],
    ],
    // Mixed input
    'Mixed with invalid part' => [
        [
            'input' => '1,2,5-7,*',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    '*',
                    $range_lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    // Garbage
    'Alphabetic input' => [
        [
            'input' => 'abc',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(
                    '%%PART%%',
                    'abc',
                    $range_lang['int_ranges_not_valid']
                ),
            ],
        ],
    ],
    'Multiple invalid singles (below 1 + above max)' => [
        [
            'input' => '0,6',
            'max_val' => 5,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '0', $range_lang['int_ranges_single_below_1']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['6', 5], $range_lang['int_ranges_single_above_max']),
            ],
        ],
    ],

    'Multiple invalid ranges (reversed + below 1)' => [
        [
            'input' => '5-3,0-2',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '5-3', $range_lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', '0-2', $range_lang['int_ranges_range_below_1']),
            ],
        ],
    ],

    'Mixed invalid: not-valid token + reversed range + below-1 single' => [
        [
            'input' => 'abc,9-1,0',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', 'abc', $range_lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '9-1', $range_lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', '0', $range_lang['int_ranges_single_below_1']),
            ],
        ],
    ],

    'Wildcard disabled + above max single + above max range' => [
        [
            'input' => '9-*,6,3-8',
            'max_val' => 5,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '9-*', $range_lang['int_ranges_not_valid']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['6', 5], $range_lang['int_ranges_single_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['3-8', 5], $range_lang['int_ranges_range_above_max']),
            ],
        ],
    ],

    'Wildcard allowed but start above max + invalid wildcard forms' => [
        [
            'input' => '11-*,*-3,*-*',
            'max_val' => 10,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['11-*', 10], $range_lang['int_ranges_range_above_max']),
                str_replace('%%PART%%', '*-3', $range_lang['int_ranges_not_valid']),
                str_replace('%%PART%%', '*-*', $range_lang['int_ranges_not_valid']),
            ],
        ],
    ],

    'Max-val enforced: range end above max + single above max + range start above max' => [
        [
            'input' => '2-9,7,12-13',
            'max_val' => 6,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['2-9', 6], $range_lang['int_ranges_range_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['7', 6], $range_lang['int_ranges_single_above_max']),
                str_replace(['%%PART%%','%%MAX_VAL%%'], ['12-13', 6], $range_lang['int_ranges_range_above_max']),
            ],
        ],
    ],

    'Whitespace + empty segments ignored, but multiple real errors remain' => [
        [
            'input' => ' , 0 , 4-2 , abc , ',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => true,
        ],
        [
            'ok' => false,
            'numbers' => [],
            'errors' => [
                str_replace('%%PART%%', '0', $range_lang['int_ranges_single_below_1']),
                str_replace('%%PART%%', '4-2', $range_lang['int_ranges_range_reversed']),
                str_replace('%%PART%%', 'abc', $range_lang['int_ranges_not_valid']),
            ],
        ],
    ],
    // Valid inputs
    'Valid single number' => [
        [
            'input' => '3',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [3],
            'errors' => [],
        ],
    ],
    'Valid multiple numbers' => [
        [
            'input' => '5,7,90',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [5,7,90],
            'errors' => [],
        ],
    ],
    'Valid range' => [
        [
            'input' => '5-11',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    'Valid multiple ranges' => [
        [
            'input' => '1-3,5-11',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [1,2,3,5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    'Valid multiple overlapping ranges' => [
        [
            'input' => '5-11,7-9',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [5,6,7,8,9,10,11],
            'errors' => [],
        ],
    ],
    'Valid mix' => [
        [
            'input' => '6,5-11,7-9,12,1',
            'max_val' => 0,
            'optional' => false,
            'allow_wildcard' => false,
        ],
        [
            'ok' => true,
            'numbers' => [1,5,6,7,8,9,10,11,12],
            'errors' => [],
        ],
    ],
]);

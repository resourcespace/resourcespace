<?php

command_line_only();

$webroot = dirname(__DIR__, 2);
include_once "{$webroot}/include/request_functions.php";


// Set up
$valid_owner_rtf = create_resource_type_field('owner_field (valid)', 0, FIELD_TYPE_DROP_DOWN_LIST, 'test_2601_valid_owner_field', false);
if ($valid_owner_rtf === false) {
    echo 'Failed setting up the test!';
    return false;
}



$test_2601_ucs = [
    [
        'name' => 'Valid configuration',
        'configs' => [
            'owner_field' => $valid_owner_rtf,
            'owner_field_mappings' => [
                278 => 3,
                279 => 1,
            ]
        ],
        'expected' => true,
    ],
    [
        'name' => 'Invalid configuration - bad owner_field',
        'configs' => [
            'owner_field' => 'bad',
            'owner_field_mappings' => [
                278 => 3,
                279 => 1,
            ]
        ],
        'expected' => false,
    ],
    [
        'name' => 'Invalid configuration - bad owner_field_mappings',
        'configs' => [
            'owner_field' => $valid_owner_rtf,
            'owner_field_mappings' => [
                'bad key' => 3,
                279 => 'bad value',
                280 => [],
                998 => false,
                999 => null,
            ]
        ],
        'expected' => false,
    ],
    [
        'name' => 'Invalid configuration',
        'configs' => [
            'owner_field' => 'bad',
            'owner_field_mappings' => [
                'bad key' => 3,
                279 => 'bad value',
                280 => [],
                998 => false,
                999 => null,
            ]
        ],
        'expected' => false,
    ],
];
foreach ($test_2601_ucs as $use_case) {
    $GLOBALS['owner_field'] = $use_case['configs']['owner_field'];
    $GLOBALS['owner_field_mappings'] = $use_case['configs']['owner_field_mappings'];
    if ($use_case['expected'] !== can_use_owner_field()) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
unset($test_2601_ucs);
$GLOBALS['owner_field'] = 0;
$GLOBALS['owner_field_mappings'] = [];

return true;

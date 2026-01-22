<?php

command_line_only();

// --- Set up
$initial_field_column_string_separator = $field_column_string_separator;
$field_column_string_separator = ', ';



$use_cases = [
    [
        'name' => 'Empty string returns the same',
        'input' => '',
        'expected' => '',
    ],
    [
        'name' => 'NULL returns the same',
        'input' => null,
        'expected' => null,
    ],
    [
        'name' => 'Value full of spaces returns the empty string',
        'input' => '    ',
        'expected' => '    ',
    ],
    [
        'name' => 'One value returns it back the same',
        'input' => 'Opt',
        'expected' => 'Opt',
    ],
    [
        'name' => 'One value with i18n syntax returns it back translated',
        'input' => '~en:Digital camera~fr:Appareil photo numérique',
        'expected' => 'Digital camera',
    ],
    [
        'name' => 'Simple CSV returns the same',
        'input' => 'Opt1, Opt2',
        'expected' => 'Opt1, Opt2',
    ],
    [
        'name' => 'Split by separator, translate and output CSV',
        'input' => '~en:Digital camera~fr:Appareil photo numérique, ~en:Scanned negative~fr:Négatif scanné',
        'expected' => 'Digital camera, Scanned negative',
    ],
    [
        'name' => 'Split a category tree path by separator, translate and output CSV',
        'input' => '~en:Colors~fr:Couleurs, ~en:Colors~fr:Couleurs/~en:red~fr:rouge',
        'expected' => 'Colors, Colors/red',
    ],
];
foreach ($use_cases as $use_case) {
    if ($use_case['expected'] !== data_joins_field_value_translate_and_csv($use_case['input'])) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
$field_column_string_separator = $initial_field_column_string_separator;
unset($initial_field_column_string_separator, $use_cases);

return true;

<?php

command_line_only();

// --- Set up
$initial_defaultlanguage = $defaultlanguage;
$initial_language = $language;
$defaultlanguage = $language = 'en';

$change_global = function ($identifier, $value) {
    return function () use ($identifier, $value) {
        $GLOBALS[$identifier] = $value;
    };
};


$use_cases = [
    // General
    [
        'name' => 'Empty string returns the same',
        'input' => '',
        'expected' => '',
    ],
    [
        'name' => 'NULL returns an empty string',
        'input' => null,
        'expected' => '',
    ],
    // Check for no trimming (translations shouldn't mess with the text format, it's not responsible for it)
    [
        'name' => 'Value with only spaces returns as is',
        'input' => '    ',
        'expected' => '    ',
    ],
    [
        'name' => 'Non-translatable syntax returns as is',
        'input' => ' Lorem ipsum',
        'expected' => ' Lorem ipsum',
    ],
    // i18n syntax behaviour
    [
        'name' => 'Translate i18n syntax (en)',
        'input' => '~en:EnglishVariant~es:SpanishVariant',
        'expected' => 'EnglishVariant',
    ],
    [
        'name' => 'Translate i18n syntax (es)',
        'setup' => $change_global('language', 'es'),
        'input' => '~en:EnglishVariant~es:SpanishVariant',
        'expected' => 'SpanishVariant',
    ],
    [
        'name' => 'Support 2 & 5 characters language codes',
        'setup' => $change_global('language', 'en-US'),
        'input' => '~en:EnglishVariant~en-US:AmericanVariant~es:SpanishVariant',
        'expected' => 'AmericanVariant',
    ],
    [
        'name' => 'Text not containing the language translation',
        'input' => '~fr:FrenchVariant~es:SpanishVariant',
        'expected' => 'FrenchVariant',
    ],
    [
        'name' => 'Broken text with partial translation (default available)',
        'setup' => $change_global('language', 'fr'),
        'input' => 'FrenchVariant~en:EnglishVariant~es:SpanishVariant',
        'expected' => 'EnglishVariant',
    ],
];
foreach ($use_cases as $use_case) {
    // reset use case
    $language = $initial_language;
    $defaultlanguage = $initial_defaultlanguage;

    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }
    if ($use_case['expected'] !== i18n_get_translated($use_case['input'])) {
        var_dump(i18n_get_translated($use_case['input']));
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}



// Tear down
$defaultlanguage = $initial_defaultlanguage;
$language = $initial_language;
unset($initial_defaultlanguage, $initial_language, $change_global, $use_cases);

return true;

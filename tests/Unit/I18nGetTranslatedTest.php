<?php

declare(strict_types=1);

$saved_translation_config = [];
beforeEach(function () use (&$saved_translation_config): void {
    $saved_translation_config = array_intersect_key($GLOBALS, ['language' => true, 'defaultlanguage' => true, 'field_column_string_separator' => true]);
    $GLOBALS['defaultlanguage'] = 'en';
});

afterEach(function () use (&$saved_translation_config): void {
    unset($GLOBALS['language']);
    unset($GLOBALS['defaultlanguage'], $GLOBALS['field_column_string_separator']);
    foreach ($saved_translation_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('returns the translated text', function (?string $input, string $expected, string $language = 'en'): void {
    $GLOBALS['language'] = $language;

    expect(i18n_get_translated($input))->toBe($expected);
})->with([
    // General
    'Empty string returns the same' => [
        '',
        '',
    ],
    'NULL returns an empty string' => [
        null,
        '',
    ],
    // Check for no trimming (translations shouldn't mess with the text format, it's not responsible for it)
    'Value with only spaces returns as is' => [
        '    ',
        '    ',
    ],
    'Non-translatable syntax returns as is' => [
        ' Lorem ipsum',
        ' Lorem ipsum',
    ],
    // i18n syntax behaviour
    'Translate i18n syntax (en)' => [
        '~en:EnglishVariant~es:SpanishVariant',
        'EnglishVariant',
    ],
    'Translate i18n syntax (es)' => [
        '~en:EnglishVariant~es:SpanishVariant',
        'SpanishVariant',
        'es',
    ],
    'Support 2 & 5 characters language codes' => [
        '~en:EnglishVariant~en-US:AmericanVariant~es:SpanishVariant',
        'AmericanVariant',
        'en-US',
    ],
    'Text not containing the language translation' => [
        '~fr:FrenchVariant~es:SpanishVariant',
        'FrenchVariant',
    ],
    'Broken text with partial translation (default available)' => [
        'FrenchVariant~en:EnglishVariant~es:SpanishVariant',
        'EnglishVariant',
        'fr',
    ],
]);

it('translates each joined metadata value and each category path segment', function (
    ?string $input,
    ?string $expected,
    string $language = 'en',
    string $separator = ', ',
): void {
    require_once RESOURCESPACE_BASE_PATH . '/include/resource_functions.php';
    $GLOBALS['language'] = $language;
    $GLOBALS['field_column_string_separator'] = $separator;

    expect(data_joins_field_value_translate_and_csv($input))->toBe($expected);
})->with([
    'empty' => ['', ''],
    'null' => [null, null],
    'spaces' => ['    ', '    '],
    'one plain option' => ['Opt', 'Opt'],
    'multiple plain options' => ['Opt1, Opt2', 'Opt1, Opt2'],
    'one translated option' => ['~en:Digital camera~fr:Appareil photo', 'Digital camera'],
    'translated options with a custom separator' => [
        '~en:Camera~fr:Appareil photo | ~en:Negative~fr:Negatif',
        'Appareil photo | Negatif',
        'fr',
        ' | ',
    ],
    'translated branch' => ['~en:Colours~fr:Couleurs, ~en:Colours~fr:Couleurs/~en:Red~fr:Rouge', 'Colours, Colours/Red'],
]);

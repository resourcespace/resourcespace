<?php

declare(strict_types=1);

$saved_translation_config = [];
beforeEach(function () use (&$saved_translation_config): void {
    $saved_translation_config = array_intersect_key($GLOBALS, ['language' => true, 'defaultlanguage' => true]);
    $GLOBALS['defaultlanguage'] = 'en';
});

afterEach(function () use (&$saved_translation_config): void {
    unset($GLOBALS['language']);
    unset($GLOBALS['defaultlanguage']);
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

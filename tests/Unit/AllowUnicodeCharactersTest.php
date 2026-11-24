<?php

declare(strict_types=1);

it('retains the allowed Unicode characters', function (array $input, string $expected): void {
    expect(allow_unicode_characters(...$input))->toBe($expected);
})->with([
    'Characters like trademark/copyright should be stripped' => [
        ['Trademark™ Product® 😊 Free© Trial ℠ ℞'],
        'Trademark Product Free Trial',
    ],
    'Any kind of invisible characters (e.g. zero-width) should be stripped' => [
        ['A zero-width character (between the letter - A​B)'],
        'A zerowidth character between the letter AB',
    ],
    'Support multi lines by default' => [
        ["Lorem ipsum dolor sit amet, consectetur adipiscing elit\r\nDuis a accumsan elit"],
        "Lorem ipsum dolor sit amet consectetur adipiscing elit\r\nDuis a accumsan elit",
    ],
    'Allow * (used for wildcard search), if applicable' => [
        ['Test*', ['*']],
        'Test*',
    ],
    'Allow ! (used for special search), if applicable' => [
        ['!collection123', ['!']],
        '!collection123',
    ],
    'Allow - (used for NOT searches), if applicable' => [
        ['test -ignoreKeyword', ['-']],
        'test -ignoreKeyword',
    ],
    'Allow multiple extra characters, if applicable' => [
        ['test* -ignoreKeyword', ['-', '*']],
        'test* -ignoreKeyword',
    ],
    'Allow @ (used by search with the NODE_TOKEN_PREFIX format)' => [
        ['@@1234', []],
        '@@1234',
    ],
    'Allow . by default e.g. in IDs' => [
        ['987.65.4', []],
        '987.65.4',
    ],
]);

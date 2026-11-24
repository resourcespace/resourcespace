<?php

declare(strict_types=1);

$url = 'https://example.test/page.php?param1=val1&param2=val2#fragment';

it('encodes a value for JavaScript output', function (string $input, string $expected): void {
    expect(encode_js_value($input))->toBe($expected);
})->with([
    'Simple text should be left alone (treated as string)' => ['Foo bar', '"Foo bar"'],
    'Double quotes should be encoded' => ['"', '"\u0022"'],
    'Single quotes should be encoded' => ["'", '"\u0027"'],
    'Less/greater than sign should be encoded' => ['<>', '"\u003C\u003E"'],
    'Ampersand should be encoded' => ['&', '"\u0026"'],
    'Backslash should be escaped' => ['\\', '"\\\\"'],
    'Slashes should NOT be encoded' => ['/path/to/page.php', '"/path/to/page.php"'],
    'Invalid UTF-8 byte is replaced' => [
        "\x80",
        '"\ufffd"'
    ],
    'URL query string should be encoded (see the ampersand and backslash use cases)' => [
        $url,
        sprintf(
            '"%s"',
            str_replace('&', '\u0026', $url)
        ),
    ],
]);

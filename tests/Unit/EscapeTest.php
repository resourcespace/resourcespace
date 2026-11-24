<?php

declare(strict_types=1);

$url = 'https://example.test/page.php?param1=val1&param2=val2#fragment';

it('escapes text for HTML output', function (string $input, string $expected): void {
    expect(escape($input))->toBe($expected);
})->with([
    'Simple text should be left alone' => ['Foo bar', 'Foo bar'],
    'Ampersand should be encoded' => ['&', '&amp;'],
    'Less-than sign should be encoded' => ['<', '&lt;'],
    'Greater-than sign should be encoded' => ['>', '&gt;'],
    'Double quotes should be encoded' => ['"', '&quot;'],
    'Single quotes should be encoded' => ["'", '&#039;'],

    // We want to show invalid characters to be able to detect encoding issues!
    'Invalid UTF-8 byte is replaced' => ["\x80", "\u{FFFD}"],

    'URL query string should be encoded (see ampersand use case)' => [
        $url,
        str_replace('&', '&amp;', $url),
    ],
    'File path should be left alone' => ['/path/to/page.php', '/path/to/page.php'],
    'Text with URI schema keywords should be left alone' => [
        'Text that may contain URI keywords like -- data: test ',
        'Text that may contain URI keywords like -- data: test ',
    ],
]);

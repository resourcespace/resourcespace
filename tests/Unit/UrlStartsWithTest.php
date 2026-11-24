<?php

declare(strict_types=1);

it('checks the prefix of a valid URL', function (mixed $url, bool $expected): void {
    expect(url_starts_with('http://test.localhost', $url))->toBe($expected);
})->with([
    'Incorrect type' => [
        [],
        false,
    ],
    'Simple' => [
        'http://test.localhost/pages/search.php',
        true,
    ],
    'Different URL' => [
        'http://unknown.localhost/pages/search.php',
        false,
    ],
    'With query string params' => [
        'http://test.localhost/pages/search.php?foo=1&bar=2',
        true,
    ],
    'Base value found part of a different URL' => [
        'http://unknown.localhost/pages/search.php?foo=1&bar=http://test.localhost/pages/search.php',
        false,
    ],
    'Not a URL' => [
        'Lorem ipsum',
        false,
    ],
]);

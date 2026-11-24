<?php

declare(strict_types=1);

it('checks if a URL is safe for HTML output', function (mixed $url, bool $expected): void {
    expect(is_safe_url($url))->toBe($expected);
})->with([
    'Invalid URL: just a string value (e.g. a fragment)' => [
        '#frag',
        false,
    ],
    'Invalid URL: malicious input' => [
        '<script>or some other injection (e.g. ") exploit',
        false,
    ],
    'Invalid URL: not using HTTP protocol' => [
        'file://' . dirname(__DIR__, 2) . '/filestore/file.txt',
        false,
    ],
    'Unsupported input type (string)' => [
        'some string',
        false,
    ],
    'Unsupported input type (int)' => [
        1234,
        false,
    ],
    'Unsupported input type (array)' => [
        [],
        false,
    ],
    'XSS payload' => [
        'http://test.localhost/pages"/edit.php',
        false,
    ],
    'URL w/ port' => [
        'http://test.localhost:8000/some_page.php',
        true,
    ],
    'URL w/ simple fragment (safe)' => [
        'http://test.localhost/some_page.php#myFragment',
        true,
    ],
    'URL w/ simple fragment (unsafe)' => [
        'http://test.localhost/some_page.php#my<b>Fragment',
        false,
    ],
    'URL w/ complex fragment (used by other applications; safe)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check.other.thing=f&l=en',
        true,
    ],
    'URL w/ complex HTML encoded fragment (used by other applications; safe)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&amp;foo=t&amp;check.other.thing=f&amp;l=en',
        true,
    ],
    'URL w/ complex percent encoded fragment (used by other applications; safe)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&l=en&q=unit%20test',
        true,
    ],
    'URL w/ complex fragment (used by other applications; unsafe v1)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check\'other.thing=f&l=en',
        false,
    ],
    'URL w/ complex fragment (used by other applications; unsafe v2)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check"other.thing=f&l=en',
        false,
    ],
    'URL w/ complex fragment (used by other applications; unsafe v3)' => [
        'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&<img>check.other.thing=f&l=en',
        false,
    ],
    'No QS (w/ ? marker)' => [
        'http://test.localhost/pages/edit.php?',
        true,
    ],
    'No QS' => [
        'http://test.localhost/pages/edit.php',
        true,
    ],
    'URL w/ simple query strings' => [
        'http://test.localhost/pages/edit.php?foo=1&bar=2',
        true,
    ],
    'URL w/ malicious query strings param name' => [
        'http://test.localhost/pages/edit.php?fo"o=1&bar=2',
        false,
    ],
    'URL w/ malicious query strings' => [
        'http://test.localhost/pages/edit.php?foo=<b>bad</b>&bar=2',
        false,
    ],
    'URL encoded param' => [
        'http://test.localhost/pages/edit.php?ref=1&redirect='
            . urlencode('http://irrelevant.localhost/some_page.php?foo=bar'),
        true,
    ],
]);

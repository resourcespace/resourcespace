<?php

declare(strict_types=1);

command_line_only();

$use_cases = [
    [
        'name' => 'Invalid URL: just a string value (e.g. a fragment)',
        'input' => '#frag',
        'expected' => false,
    ],
    [
        'name' => 'Invalid URL: malicious input',
        'input' => '<script>or some other injection (e.g. ") exploit',
        'expected' => false,
    ],
    [
        'name' => 'Invalid URL: not using HTTP protocol',
        'input' => 'file://' . dirname(__DIR__, 2) . '/filestore/file.txt',
        'expected' => false,
    ],
    [
        'name' => 'Unsupported input type (string)',
        'input' => 'some string',
        'expected' => false,
    ],
    [
        'name' => 'Unsupported input type (int)',
        'input' => 1234,
        'expected' => false,
    ],
    [
        'name' => 'Unsupported input type (array)',
        'input' => [],
        'expected' => false,
    ],
    [
        'name' => 'XSS payload',
        'input' => 'http://test.localhost/pages"/edit.php',
        'expected' => false,
    ],
    [
        'name' => 'URL w/ port',
        'input' => 'http://test.localhost:8000/some_page.php',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ simple fragment (safe)',
        'input' => 'http://test.localhost/some_page.php#myFragment',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ simple fragment (unsafe)',
        'input' => 'http://test.localhost/some_page.php#my<b>Fragment',
        'expected' => false,
    ],
    [
        'name' => 'URL w/ complex fragment (used by other applications; safe)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check.other.thing=f&l=en',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ complex HTML encoded fragment (used by other applications; safe)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&amp;foo=t&amp;check.other.thing=f&amp;l=en',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ complex percent encoded fragment (used by other applications; safe)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&l=en&q=unit%20test',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ complex fragment (used by other applications; unsafe v1)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check\'other.thing=f&l=en',
        'expected' => false,
    ],
    [
        'name' => 'URL w/ complex fragment (used by other applications; unsafe v2)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&check"other.thing=f&l=en',
        'expected' => false,
    ],
    [
        'name' => 'URL w/ complex fragment (used by other applications; unsafe v3)',
        'input' => 'http://test.localhost/some_page.php#!/unit/test?pn=1&foo=t&<img>check.other.thing=f&l=en',
        'expected' => false,
    ],
    [
        'name' => 'No QS (w/ ? marker)',
        'input' => 'http://test.localhost/pages/edit.php?',
        'expected' => true,
    ],
    [
        'name' => 'No QS',
        'input' => 'http://test.localhost/pages/edit.php',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ simple query strings',
        'input' => 'http://test.localhost/pages/edit.php?foo=1&bar=2',
        'expected' => true,
    ],
    [
        'name' => 'URL w/ malicious query strings param name',
        'input' => 'http://test.localhost/pages/edit.php?fo"o=1&bar=2',
        'expected' => false,
    ],
    [
        'name' => 'URL w/ malicious query strings',
        'input' => 'http://test.localhost/pages/edit.php?foo=<b>bad</b>&bar=2',
        'expected' => false,
    ],
    [
        'name' => 'URL encoded param',
        'input' => 'http://test.localhost/pages/edit.php?ref=1&redirect='
            . urlencode('http://irrelevant.localhost/some_page.php?foo=bar'),
        'expected' => true,
    ],
];
foreach ($use_cases as $uc) {
    if ($uc['expected'] !== is_safe_url($uc['input'])) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases);

return true;

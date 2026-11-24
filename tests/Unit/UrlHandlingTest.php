<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/include/login_functions.php';

$saved_url_config = [];
beforeEach(function () use (&$saved_url_config): void {
    $saved_url_config = array_intersect_key($GLOBALS, ['baseurl' => true, 'baseurl_short' => true]);
});

afterEach(function () use (&$saved_url_config): void {
    unset($GLOBALS['baseurl'], $GLOBALS['baseurl_short']);
    foreach ($saved_url_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

// Run each case for installations at the root and in a subdirectory.
$by_installation = static function (Closure $cases): array {
    $dataset = [];
    foreach (['/', '/resourcespace/'] as $installation_path) {
        $baseurl = 'https://test.localhost' . rtrim($installation_path, '/');
        foreach ($cases($installation_path, $baseurl) as $name => $arguments) {
            $dataset["{$name} ({$installation_path})"] = [$installation_path, ...$arguments];
        }
    }
    return $dataset;
};

it('recognises URLs within the configured installation', function (
    string $installation_path,
    string $input,
    bool $expected
): void {
    $GLOBALS['baseurl_short'] = $installation_path;
    $GLOBALS['baseurl'] = 'https://test.localhost' . rtrim($installation_path, '/');

    expect(is_internal_url($input))->toBe($expected);
})->with($by_installation(static function (string $installation_path, string $baseurl): array {
    $baseurl_short = $installation_path;
    $destination = 'pages/collections_featured.php?parent=123&find=test';

    return [
        'Accept an absolute internal URL' => [$baseurl . '/' . $destination, true],
        'Accept an application-root URL' => [$baseurl_short . $destination, true],
        'Reject an empty URL' => ['', false],
        'Reject a document-relative URL' => [$destination, false],
        'Reject an external URL' => ['https://example.org/', false],
        'Reject a protocol-relative URL' => ['//example.org/', false],
        'Reject a backslash that browsers interpret as a hostname separator' => ['/\\example.org/', false],
        'Reject an external hostname starting with our hostname' => ['https://test.localhost.example.org/', false],
        'Reject our hostname used as credentials for an external hostname' => [
            'https://test.localhost@example.org/',
            false,
        ],
        'Reject a similar installation path unless the installation is at the root' => [
            'https://test.localhost/resourcespace-other/pages/home.php',
            $installation_path === '/',
        ],
        'Accept HTML-sensitive characters in an absolute internal URL' => [
            $baseurl . "/pages/collections_featured.php?find=O'Reilly&parent=123",
            true,
        ],
        'Accept HTML-sensitive characters in an application-root URL' => [
            $baseurl_short . "pages/collections_featured.php?find=O'Reilly&parent=123",
            true,
        ],
        'Reject carriage returns and newlines in a URL' => [
            $baseurl_short . "pages/home.php\r\nLocation: https://example.org/",
            false,
        ],
    ];
}));

it('converts only application-root URLs to absolute URLs', function (
    string $installation_path,
    string $input,
    string $expected
): void {
    $GLOBALS['baseurl_short'] = $installation_path;
    $GLOBALS['baseurl'] = 'https://test.localhost' . rtrim($installation_path, '/');

    expect(convert_to_absolute_url($input))->toBe($expected);
})->with($by_installation(static function (string $installation_path, string $baseurl): array {
    $baseurl_short = $installation_path;
    $destination = 'pages/collections_featured.php?parent=123&find=test';

    return [
        'Convert an application-root URL and preserve its query string' => [
            $baseurl_short . $destination,
            $baseurl . '/' . $destination,
        ],
        'Leave an absolute internal URL unchanged' => [$baseurl . '/' . $destination, $baseurl . '/' . $destination],
        'Leave an absolute external URL unchanged' => ['https://example.org/', 'https://example.org/'],
        'Leave a protocol-relative URL unchanged' => ['//example.org/', '//example.org/'],
        'Leave a document-relative URL unchanged' => [$destination, $destination],
        'Leave an empty URL unchanged' => ['', ''],
    ];
}));

it('resolves login return URLs within the installation', function (
    string $installation_path,
    string $input,
    string $expected
): void {
    $GLOBALS['baseurl_short'] = $installation_path;
    $GLOBALS['baseurl'] = 'https://test.localhost' . rtrim($installation_path, '/');

    expect(get_login_redirect_url($input))->toBe($expected);
})->with($by_installation(static function (string $installation_path, string $baseurl): array {
    $baseurl_short = $installation_path;
    $destination = "pages/search.php?search=O'Reilly&archive=0#ResourceList";
    $default_url = "{$baseurl}/index.php";

    return [
        'Absolute internal URL' => ["{$baseurl}/{$destination}", "{$baseurl}/{$destination}"],
        'Application-root URL' => [$baseurl_short . $destination, "{$baseurl}/{$destination}"],
        'Document-relative URL' => [$destination, "{$baseurl}/{$destination}"],
        'Default home URL' => ['pages/home.php?login=true', "{$baseurl}/pages/home.php?login=true"],
        'Application base URL' => [$baseurl, "{$baseurl}/"],
        'Application root' => [$baseurl_short, "{$baseurl}/"],
        'Empty URL' => ['', $default_url],
        'External HTTPS URL' => ['https://example.org/', $default_url],
        'External HTTP URL' => ['http://example.org/', $default_url],
        'Protocol-relative URL' => ['//example.org/', $default_url],
        'Protocol-relative URL without a path' => ['//example.org', $default_url],
        'Extra leading slash' => ['///example.org/', $default_url],
        'Backslash hostname separator' => ['/\\example.org/', $default_url],
        'Backslashes' => ['\\\\example.org/', $default_url],
        'Absolute URL with a backslash' => ["{$baseurl}/\\example.org/", $default_url],
        'Hostname prefix' => ['https://test.localhost.example.org/', $default_url],
        'Hostname as user information' => ['https://test.localhost@example.org/', $default_url],
        'Wrong port' => ['https://test.localhost:8443/pages/home.php', $default_url],
        'Wrong scheme' => ['http://test.localhost/pages/home.php', $default_url],
        'JavaScript URL' => ['javascript:alert(1)', $default_url],
        'Data URL' => ['data:text/html,test', $default_url],
        'Leading whitespace' => [' //example.org/', $default_url],
        'Control characters' => ["\t//example.org/", $default_url],
        'Newline in internal URL' => ["{$baseurl}/pages/home.php\n", $default_url],
        'Similar installation path' => [
            'https://test.localhost/resourcespace-other/pages/home.php',
            $installation_path === '/'
                ? 'https://test.localhost/resourcespace-other/pages/home.php'
                : $default_url,
        ],
    ];
}));

it('sanitises unsafe URLs for HTML', function (
    string $input,
    string $expected,
    string $baseurl_short = '/test'
): void {
    $GLOBALS['baseurl_short'] = $baseurl_short;
    $GLOBALS['baseurl'] = 'https://test.localhost' . rtrim($baseurl_short, '/');

    expect(sanitise_url($input))->toBe($expected);
})->with([
    'Absolute (http) URL should be returned as is, if safe' => [
        'http://test.localhost/path/?p1=v1&p2=v2#fragment',
        'http://test.localhost/path/?p1=v1&p2=v2#fragment',
    ],
    'Absolute (https) URL should be returned as is, if safe' => [
        'https://test.localhost/path/?p1=v1&p2=v2#fragment',
        'https://test.localhost/path/?p1=v1&p2=v2#fragment',
    ],
    'Allow "mailto" schema URI' => ['mailto:test@montala.com', 'mailto:test@montala.com'],
    'Sanitise unsafe "mailto" schema URI (1)' => ['mailto"><p>test</p>:test@montala.com', '#'],
    'Sanitise unsafe "mailto" schema URI (2)' => ['mailto:test@montala.com"><p>test</p>', '#'],
    'Allow root-relative URL (web root)' => [
        '/path/?p1=v1&p2=v2#fragment',
        '/path/?p1=v1&p2=v2#fragment',
        '/',
    ],
    'Allow root-relative URL (sub-directory: /test)' => [
        '/test/path/?p1=v1&p2=v2#fragment',
        '/test/path/?p1=v1&p2=v2#fragment',
    ],
    'Allow relative URL path (1)' => ['./pages/relative_from.php', './pages/relative_from.php'],
    'Allow relative URL path (2)' => ['../pages/relative_from.php', '../pages/relative_from.php'],
    'Allow relative URL path (3)' => ['../../pages/relative_from.php', '../../pages/relative_from.php'],
    'Allow relative URL path (4)' => ['page.php', 'page.php'],
    'Allow relative URL path (5)' => [
        'generic_page.html?p1=v1&p2=v2#fragment',
        'generic_page.html?p1=v1&p2=v2#fragment',
    ],
    'Unsafe relative URL path will be sanitised (1)' => ['../pages/relative_from.php?p1=v1&p2="><p>test</p>', '#'],
    'Unsafe relative URL path will be sanitised (2)' => ['."/pages/relative_from.php?p1=v1&p2="><p>test</p>', '#'],
    'Unsafe relative URL path will be sanitised (3)' => ['page.php?p1"=v1&p2="><p>test</p>', '#'],
    'Relative (root) URL not matching $baseurl_short is sanitised (1)' => ['/unknown/path/?p1=v1&p2=v2#fragment', '#'],
    'Relative (root) URL not matching $baseurl_short is sanitised (2)' => [
        ':path/?p1=v1&p2=v2#fragment',
        '#',
        '/',
    ],
    'URI with non-http schema will be sanitised' => ['javascript:alert("test")', '#'],
    'URL with html code (not % encoded) will be sanitised' => [
        'https://test.localhost/path/?p1=v1&p2="><p>test</p>',
        '#',
    ],
    'Allow an absolute URL with unsafe but % encoded characters' => [
        'https://test.localhost/pages/test.php?p1=Foo%27s+Bar+baz',
        'https://test.localhost/pages/test.php?p1=Foo%27s+Bar+baz',
    ],
    'Allow a relative URL with unsafe but % encoded characters' => [
        '/pages/test.php?p1=Foo%27s+Bar+baz',
        '/pages/test.php?p1=Foo%27s+Bar+baz',
        '/',
    ],
]);

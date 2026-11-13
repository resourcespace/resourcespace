<?php

declare(strict_types=1);

command_line_only();
include_once dirname(__DIR__, 2) . '/include/login_functions.php';

// Set up
$saved_baseurl = $baseurl;
$saved_baseurl_short = $baseurl_short;

foreach (['/', '/resourcespace/'] as $installation_path) {
    $baseurl = 'https://test.localhost' . substr($installation_path, 0, -1);
    $baseurl_short = $installation_path;
    $destination = "pages/search.php?search=O'Reilly&archive=0#ResourceList";
    $default_url = "{$baseurl}/index.php";
    $use_cases = [
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

    foreach ($use_cases as $name => [$input, $expected]) {
        if (get_login_redirect_url($input) !== $expected) {
            echo "Use case: {$name} (installation path: {$installation_path}) - ";
            $baseurl = $saved_baseurl;
            $baseurl_short = $saved_baseurl_short;
            return false;
        }
    }
}

// Tear down
$baseurl = $saved_baseurl;
$baseurl_short = $saved_baseurl_short;
unset(
    $saved_baseurl,
    $saved_baseurl_short,
    $installation_path,
    $destination,
    $default_url,
    $use_cases,
    $name,
    $input,
    $expected
);

return true;

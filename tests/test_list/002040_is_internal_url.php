<?php

declare(strict_types=1);

command_line_only();

// --- Set up
$saved_baseurl = $baseurl;
$saved_baseurl_short = $baseurl_short;
// --- End of Set up

foreach (['/', '/resourcespace/'] as $installation_path) {
    $baseurl = 'https://test.localhost' . substr($installation_path, 0, -1);
    $baseurl_short = $installation_path;
    $destination = 'pages/collections_featured.php?parent=123&find=test';
    $use_cases = [
        [
            'name' => 'Accept an absolute internal URL',
            'input' => $baseurl . '/' . $destination,
            'expected' => true,
        ],
        [
            'name' => 'Accept an application-root URL',
            'input' => $baseurl_short . $destination,
            'expected' => true,
        ],
        [
            'name' => 'Reject an empty URL',
            'input' => '',
            'expected' => false,
        ],
        [
            'name' => 'Reject a document-relative URL',
            'input' => $destination,
            'expected' => false,
        ],
        [
            'name' => 'Reject an external URL',
            'input' => 'https://example.org/',
            'expected' => false,
        ],
        [
            'name' => 'Reject a protocol-relative URL',
            'input' => '//example.org/',
            'expected' => false,
        ],
        [
            'name' => 'Reject a backslash that browsers interpret as a hostname separator',
            'input' => '/\\example.org/',
            'expected' => false,
        ],
        [
            'name' => 'Reject an external hostname starting with our hostname',
            'input' => 'https://test.localhost.example.org/',
            'expected' => false,
        ],
        [
            'name' => 'Reject our hostname used as credentials for an external hostname',
            'input' => 'https://test.localhost@example.org/',
            'expected' => false,
        ],
        [
            'name' => 'Reject a similar installation path unless the installation is at the root',
            'input' => 'https://test.localhost/resourcespace-other/pages/home.php',
            'expected' => $installation_path === '/',
        ],
        [
            'name' => 'Accept HTML-sensitive characters in an absolute internal URL',
            'input' => $baseurl . "/pages/collections_featured.php?find=O'Reilly&parent=123",
            'expected' => true,
        ],
        [
            'name' => 'Accept HTML-sensitive characters in an application-root URL',
            'input' => $baseurl_short . "pages/collections_featured.php?find=O'Reilly&parent=123",
            'expected' => true,
        ],
        [
            'name' => 'Reject carriage returns and newlines in a URL',
            'input' => $baseurl_short . "pages/home.php\r\nLocation: https://example.org/",
            'expected' => false,
        ],
    ];

    foreach ($use_cases as $uc) {
        $result = is_internal_url($uc['input']);
        if ($uc['expected'] !== $result) {
            printf(
                '%sUse case: %s (installation path: %s) - ',
                RS_TEST_DEBUG ? PHP_EOL : '',
                $uc['name'],
                $installation_path,
            );
            return false;
        }
    }
}

// Tear down
$baseurl = $saved_baseurl;
$baseurl_short = $saved_baseurl_short;
unset($saved_baseurl, $saved_baseurl_short, $installation_path, $destination, $use_cases, $uc, $result);

return true;

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
            'name' => 'Convert an application-root URL and preserve its query string',
            'input' => $baseurl_short . $destination,
            'expected' => $baseurl . '/' . $destination,
        ],
        [
            'name' => 'Leave an absolute internal URL unchanged',
            'input' => $baseurl . '/' . $destination,
            'expected' => $baseurl . '/' . $destination,
        ],
        [
            'name' => 'Leave an absolute external URL unchanged',
            'input' => 'https://example.org/',
            'expected' => 'https://example.org/',
        ],
        [
            'name' => 'Leave a protocol-relative URL unchanged',
            'input' => '//example.org/',
            'expected' => '//example.org/',
        ],
        [
            'name' => 'Leave a document-relative URL unchanged',
            'input' => $destination,
            'expected' => $destination,
        ],
        [
            'name' => 'Leave an empty URL unchanged',
            'input' => '',
            'expected' => '',
        ],
    ];
    foreach ($use_cases as $uc) {
        $result = convert_to_absolute_url($uc['input']);
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

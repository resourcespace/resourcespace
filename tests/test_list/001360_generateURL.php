<?php

command_line_only();

// --- Set up
$static_url = 'http://test.localhost';
// --- End of Set up

$use_cases = [
    [
        'name' => 'URL with default params',
        'input' => [$static_url, ['p1' => 'v1', 'p2' => 'v2']],
        'expected' => "{$static_url}?p1=v1&p2=v2",
    ],
    [
        'name' => 'URL with no params',
        'input' => [$static_url, []],
        'expected' => "{$static_url}",
    ],
    [
        'name' => 'Set params should override the default ones',
        'input' => [$static_url, ['p1' => 'v1', 'p2' => 'v2'], ['p1' => 'newV1']],
        'expected' => "{$static_url}?p1=newV1&p2=v2",
    ],
    [
        'name' => 'Both default and set params allow PHP null values',
        'input' => [$static_url, ['def' => null], ['set' => null]],
        'expected' => "{$static_url}?def=&set=",
    ],
    [
        'name' => 'Both default and set params should block array values',
        'input' => [$static_url, ['p1' => [], 'p2' => ['p2.1' => 1]], ['set1' => [], 'set2' => ['set2.1' => 1]]],
        'expected' => "{$static_url}",
    ],
    [
        'name' => 'URLs within query string params should be encoded',
        'input' => [
            $static_url,
            ['p1' => 'v1', 'url' => generateURL($static_url, ['ping' => 1, 'foo' => 'bar'])]
        ],
        'expected' => "{$static_url}?p1=v1&url=http%3A%2F%2Ftest.localhost%3Fping%3D1%26foo%3Dbar",
    ],
    [
        'name' => 'URL param name should be encoded',
        'input' => [$static_url, ['"onmouseover=\'alert(803)\'"' => '']],
        'expected' => "{$static_url}?%22onmouseover%3D%27alert%28803%29%27%22=",
    ],
    [
        'name' => 'URL param value should encode characters we might not want in HTML context too',
        'input' => [$static_url, ['p1' => '">v1', 'p2' => '\'>v2', 'p3' => '&v3=test', 'p4' => ':v4;']],
        'expected' => "{$static_url}?p1=%22%3Ev1&p2=%27%3Ev2&p3=%26v3%3Dtest&p4=%3Av4%3B",
    ],
];
foreach ($use_cases as $uc) {
    $result = generateURL(...$uc['input']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        test_log(" - expected >>>{$uc['expected']}<<<");
        test_log(" - result   >>>{$result}<<<");
        return false;
    }
}

// Tear down
unset($use_cases, $static_url);

return true;

<?php

declare(strict_types=1);

$static_url = 'http://test.localhost';

it(
    'generates the expected URL',
    function (array $input, string $expected): void {
        expect(generateURL(...$input))->toBe($expected);
    },
)->with([
    'URL with default params' => [
        [$static_url, ['p1' => 'v1', 'p2' => 'v2']],
        "{$static_url}?p1=v1&p2=v2",
    ],
    'URL with no params' => [
        [$static_url, []],
        $static_url,
    ],
    'Set params should override the default ones' => [
        [$static_url, ['p1' => 'v1', 'p2' => 'v2'], ['p1' => 'newV1']],
        "{$static_url}?p1=newV1&p2=v2",
    ],
    'Both default and set params allow PHP null values' => [
        [$static_url, ['def' => null], ['set' => null]],
        "{$static_url}?def=&set=",
    ],
    'Both default and set params should block array values' => [
        [$static_url, ['p1' => [], 'p2' => ['p2.1' => 1]], ['set1' => [], 'set2' => ['set2.1' => 1]]],
        $static_url,
    ],
    'URLs within query string params should be encoded' => [
        [
            $static_url,
            ['p1' => 'v1', 'url' => 'http://test.localhost?ping=1&foo=bar'],
        ],
        "{$static_url}?p1=v1&url=http%3A%2F%2Ftest.localhost%3Fping%3D1%26foo%3Dbar",
    ],
    'URL param name should be encoded' => [
        [$static_url, ['"onmouseover=\'alert(803)\'"' => '']],
        "{$static_url}?%22onmouseover%3D%27alert%28803%29%27%22=",
    ],
    'URL param value should encode characters we might not want in HTML context too' => [
        [$static_url, ['p1' => '">v1', 'p2' => '\'>v2', 'p3' => '&v3=test', 'p4' => ':v4;']],
        "{$static_url}?p1=%22%3Ev1&p2=%27%3Ev2&p3=%26v3%3Dtest&p4=%3Av4%3B",
    ],
]);

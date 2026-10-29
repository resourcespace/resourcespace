<?php

declare(strict_types=1);

command_line_only();

$use_cases = [
    [
        'name' => 'Incorrect type',
        'input' => [],
        'expected' => false,
    ],
    [
        'name' => 'Simple',
        'input' => 'http://test.localhost/pages/search.php',
        'expected' => true,
    ],
    [
        'name' => 'Different URL',
        'input' => 'http://unknown.localhost/pages/search.php',
        'expected' => false,
    ],
    [
        'name' => 'With query string params',
        'input' => 'http://test.localhost/pages/search.php?foo=1&bar=2',
        'expected' => true,
    ],
    [
        'name' => 'Base value found part of a different URL',
        'input' => 'http://unknown.localhost/pages/search.php?foo=1&bar=http://test.localhost/pages/search.php',
        'expected' => false,
    ],
    [
        'name' => 'Not a URL',
        'input' => 'Lorem ipsum',
        'expected' => false,
    ],
];
foreach ($use_cases as $uc) {
    $result = url_starts_with('http://test.localhost', $uc['input']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        test_log("result = {$result}");
        return false;
    }
}

// Tear down
unset($use_cases, $result);

return true;

<?php

command_line_only();

$use_cases = [
    [
        'name' => 'File name (no path)',
        'input' => 'test.jpg',
        'expected' => 'jpg',
    ],
    [
        'name' => 'File name (with path)',
        'input' => '/tmp/test.jpg',
        'expected' => 'jpg',
    ],
    [
        'name' => 'Double extension',
        'input' => 'test.jpg.php',
        'expected' => 'php',
    ],
    [
        'name' => 'Null bytes',
        'input' => "test.php\0.jpg",
        'expected' => '',
    ],
    [
        'name' => 'Windows (DOS) 8.3 short path (allowed only if an actual file)',
        'input' => 'HTACCE~1',
        'expected' => '',
    ],
    [
        'name' => 'Hidden file without extension',
        'input' => '.hidden-file',
        'expected' => '',
    ],
    [
        'name' => 'Hidden file with extension',
        'input' => '.hidden-file.jpg',
        'expected' => 'jpg',
    ],
];
foreach ($use_cases as $uc) {
    $result = parse_filename_extension($uc['input']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases, $result);

return true;

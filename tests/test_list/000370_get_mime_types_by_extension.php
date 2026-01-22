<?php

command_line_only();

$use_cases = [
    [
        'name' => 'Finding the MIME type for single entries',
        'input' => 'jpg',
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'Extension matching multiple types',
        'input' => 'mp4',
        'expected' => ['video/mp4', 'audio/mp4', 'video/x-m4v'],
    ],
    [
        'name' => 'Unknown/missing type returns an empty array',
        'input' => 'invalidExt',
        'expected' => [],
    ],
    [
        'name' => 'Matching is case-insensitive',
        'input' => 'JpG',
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'Treat bad MIME type (e.g. only spaces) as unknown',
        'setup' => static fn() => $GLOBALS['mime_types_by_extension']['testOnlySpaces'] = '   ',
        'input' => 'testOnlySpaces',
        'expected' => [],
    ],
];
foreach ($use_cases as $uc) {
    // Set up the use case environment
    if(isset($uc['setup']))
        {
        $uc['setup']();
        }

    $result = get_mime_types_by_extension($uc['input']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        test_log('result = ' . print_r($result, true) . PHP_EOL);
        return false;
    }
}

// Tear down
unset($use_cases, $result);

return true;

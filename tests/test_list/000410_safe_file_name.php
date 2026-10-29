<?php

command_line_only();

$use_cases = [
    [
        'name' => 'Filename containing only valid characters inside the ascii character set. Validated with the default method.',
        'input' => ['my_file1_2-3'],
        'expected' => 'my_file1_2-3',
    ],
    [
        'name' => 'Filename containing invalid characters including non ascii characters which are replaced away. Validated with the default method.',
        'input' => ['my.file1/!Í'],
        'expected' => 'myfile1',
    ],
    [
        'name' => 'Filename containing spaces to be replaced with underscores. Validated with the default method.',
        'input' => ['my file1'],
        'expected' => 'my_file1',
    ],
    [
        'name' => 'Filename containing only valid ascii characters validated with the $extended parameter rather than default method.',
        'input' => ['my_file1_2-3', true],
        'expected' => 'my_file1_2-3',
    ],
    [
        'name' => 'Filename containing invalid characters which are replaced away, validated with the $extended parameter rather than default method.',
        'input' => ['my.file1/!', true],
        'expected' => 'myfile1',
    ],
    [
        'name' => 'Filename containing spaces to be replaced with underscore. Validated with the $extended parameter rather than default method.',
        'input' => ['my file1ă', true],
        'expected' => 'my_file1ă',
    ],
    [
        'name' => 'Filename containing only valid characters including non ascii characters. Successful validation requires the $extended parameter.',
        'input' => ['test-ă_fileÍ', true],
        'expected' => 'test-ă_fileÍ',
    ],
];
foreach ($use_cases as $uc) {
    $result = safe_file_name(...$uc['input']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";

        test_log("- result   = {$result}");
        test_log("- expected = {$uc['expected']}");

        return false;
    }
}

// Tear down
unset($use_cases, $result);

return true;

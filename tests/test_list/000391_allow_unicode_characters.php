<?php

command_line_only();

$use_cases = [
    [
        'name' => 'Characters like trademark/copyright should be stripped',
        'input' => ['Trademark™ Product® 😊 Free© Trial ℠ ℞.'],
        'expected' => 'Trademark Product Free Trial',
    ],
    [
        'name' => 'Any kind of invisible characters (e.g. zero-width) should be stripped',
        'input' => ['A zero-width character (between the letter - A​B)'],
        'expected' => 'A zerowidth character between the letter AB',
    ],
    [
        'name' => 'Support multi lines by default',
        'input' => ["Lorem ipsum dolor sit amet, consectetur adipiscing elit.\r\nDuis a accumsan elit."],
        'expected' => "Lorem ipsum dolor sit amet consectetur adipiscing elit\r\nDuis a accumsan elit",
    ],
    [
        'name' => 'Allow * (used for wildcard search), if applicable',
        'input' => ['Test*', ['*']],
        'expected' => 'Test*',
    ],
    [
        'name' => 'Allow ! (used for special search), if applicable',
        'input' => ['!collection123', ['!']],
        'expected' => '!collection123',
    ],
    [
        'name' => 'Allow - (used for NOT searches), if applicable',
        'input' => ['test -ignoreKeyword', ['-']],
        'expected' => 'test -ignoreKeyword',
    ],
    [
        'name' => 'Allow multiple extra characters, if applicable',
        'input' => ['test* -ignoreKeyword', ['-', '*']],
        'expected' => 'test* -ignoreKeyword',
    ],
];
foreach ($use_cases as $uc) {
    $result = allow_unicode_characters(...$uc['input']);
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

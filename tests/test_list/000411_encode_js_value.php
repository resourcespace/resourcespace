<?php

command_line_only();

$use_cases = [
    ['Simple text should be left alone (treated as string)', 'Foo bar', '"Foo bar"'],
    ['Double quotes should be encoded', '"', '"\u0022"'],
    ['Single quotes should be encoded', "'", '"\u0027"'],
    ['Less/greater than sign should be encoded', '<>', '"\u003C\u003E"'],
    ['Ampersand should be encoded', '&', '"\u0026"'],
    ['Backslash should be escaped', '\\', '"\\\\"'],
    ['Slashes should NOT be encoded', '/path/to/page.php', '"/path/to/page.php"'],
    [
        "Invalid/malformed UTF-8 characters (e.g. \x80) will be converted to the replacement character (\0xfffd)",
        "\x80",
        '"\ufffd"'
    ],
    [
        'URL query string should be encoded (see the ampersand and backslash use cases)',
        generateURL($baseurl, ['param1' => 'val1', 'param2' => 'val2']) . '#fragment',
        sprintf(
            '"%s"',
            str_replace('&', '\u0026', generateURL($baseurl, ['param1' => 'val1', 'param2' => 'val2']) . '#fragment')
        ),
    ],
];
foreach ($use_cases as [$use_case, $input, $expected]) {
    $result = encode_js_value($input);
    if ($expected !== $result) {
        echo "Use case: {$use_case} - ";
        test_log("expected >>>{$expected}<<<");
        test_log("result   >>>{$result}<<<");
        return false;
    }
}

// Tear down
unset($use_cases, $result);

return true;

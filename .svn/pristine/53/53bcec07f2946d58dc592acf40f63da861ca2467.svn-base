<?php

command_line_only();

// --- Set up
$scramble_key_cache = $scramble_key;
$scramble_key = 'test_scramble_key_evalSIG';
$log_use_case = static function (string $code) {
    test_log(PHP_EOL . '# Extra info:');
    test_log("- code = ---{$code}---");
    test_log('- sign_code = "' . sign_code($code) . '"');
};
// --- End of Set up

$use_cases = [
    [
        'name' => 'No need to check without code value',
        'code' => '',
        'expected' => '',
    ],
    [
        'name' => 'No need to check when code value is just space',
        'code' => '  ',
        'expected' => '',
    ],
    [
        'name' => 'New code requires to be signed',
        'code' => '$some_var = true;',
        'expected' => '',
    ],
    [
        'name' => 'Code already signed will pass the check',
        'code' => '//SIG125bc1382bbfa15f176a26d1f7caff608ff49a637022267411ad5b6a6e5ee1b4
$some_var = true;',
        'expected' => '$some_var = true;',
    ],
    [
        'name' => 'Code signed multiple times should pass the check too',
        'code' => '//SIGcfd973d77a5f8c5f7dca8bb00dd2be37519a5655c6fce000bb2b6b67e78d3ec1
//SIG125bc1382bbfa15f176a26d1f7caff608ff49a637022267411ad5b6a6e5ee1b4
$some_var = true;',
        'expected' => '//SIG125bc1382bbfa15f176a26d1f7caff608ff49a637022267411ad5b6a6e5ee1b4
$some_var = true;',
    ],
];
foreach ($use_cases as $uc) {
    $result = eval_check_signed($uc['code']);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";

        test_log("result = '{$result}'");
        test_log("expected = '{$uc['expected']}'");
        $log_use_case($uc['code']);

        return false;
    }
}

// Tear down
$scramble_key = $scramble_key_cache;
unset($use_cases, $result, $scramble_key_cache, $log_use_case);

return true;

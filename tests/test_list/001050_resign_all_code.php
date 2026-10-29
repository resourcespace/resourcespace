<?php

command_line_only();

// --- Set up
$run_id = test_generate_random_ID(5);
test_log("Run ID - {$run_id}");
$test_run_name = sprintf('test_%s-%s', test_get_file_id(__FILE__), $run_id);

$scramble_key_cache = $scramble_key;
$scramble_key = 'test_scramble_key_resignAllCodeSIG';

resign_all_code(false, false); # Sign all pre-existing code, have a clean slate

$ug_ref = save_usergroup(0, ['name' => $test_run_name]);

$generate_signed_code = static fn (string $code): string => sprintf("//SIG%s\n%s", sign_code($code), $code);
// --- End of Set up

$use_cases = [
    [
        'name' => 'No need to sign if there\'s no code provided',
        'expected' => '',
    ],
    [
        'name' => 'First time, the code should require signing',
        'setup' => static fn() => save_usergroup($ug_ref, ['config_options' => '$foo = "bar";']),
        'expected' => "usergroup -> config_options -> {$ug_ref}\n\$foo = \"bar\";",
    ],
    [
        'name' => 'Code already signed will be left alone',
        'setup' => static fn() => save_usergroup($ug_ref, ['config_options' => $generate_signed_code('$foo = "bar";')]),
        'expected' => '',
    ],
    [
        'name' => 'Modified code (previously signed) should require signing',
        'setup' => static fn() => save_usergroup(
            $ug_ref,
            ['config_options' => $generate_signed_code('$foo = "bar";') . '$new_addition = true;']
        ),
        'expected' => "usergroup -> config_options -> {$ug_ref}\n"
            . "//SIGe062e4968a31bdb459613bd1038431864bcfc82fcec48d52f12aab7225e2a84d\n"
            . '$foo = "bar";$new_addition = true;',
    ],
    [
        'name' => 'Code signed multiple time will be left alone',
        'setup' => static fn() => save_usergroup(
            $ug_ref,
            ['config_options' => $generate_signed_code($generate_signed_code('$foo = "bar";'))]
        ),
        'expected' => "usergroup -> config_options -> {$ug_ref}\n"
            . "//SIG7f69744b3b559b1c106d9d8a9f6137d1c0b8b7b909ea11df02acecd5a9ed9222\n"
            . "//SIGe062e4968a31bdb459613bd1038431864bcfc82fcec48d52f12aab7225e2a84d\n"
            . '$foo = "bar";',
    ],
];
foreach ($use_cases as $uc) {
    // Set up the use case environment
    if (isset($uc['setup'])) {
        $uc['setup']();
    }

    $result = trim(cast_echo_to_string(
        resign_all_code(...),
        [
            'confirm' => true,
            'output' => false,
            'output_changes_only' => true
        ]
    ));
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";

        test_log("result = '{$result}'");
        test_log("expected = '{$uc['expected']}'");

        $record = get_usergroup($ug_ref);
        test_log(PHP_EOL . '# Extra info:');
        test_log("- UG #{$ug_ref}");
        test_log("- config_options = ```{$record['config_options']}```");

        return false;
    }
}

// Tear down
$scramble_key = $scramble_key_cache;
unset($run_id, $use_cases, $result, $scramble_key_cache);

return true;

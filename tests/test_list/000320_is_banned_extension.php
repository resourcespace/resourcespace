<?php

command_line_only();

$use_cases = [
    ['Extension longer than 10 chars should be banned', 'longfileextension', true],
    ['Empty string should be banned', '', true],
    ['Double quotes should be banned (legacy - before having ps_*)', '"', true],
    ['Path traversal attempt should be banned - #1', '.', true],
    ['Path traversal attempt should be banned - #2', '../../somefile.jpg', true],
    ['Known banned extension not allowed', 'php', true],
    ['Known banned extension not allowed (case insensitive)', 'PHP', true],
    ['Bypassing known banned extension using a dot', '.php', true],
    ['Bypassing known banned extension using two dots', '..php', true],
    ['Invalid extension format should always ban - #1', '.mp4', true],
    ['Invalid extension format should always ban - #2', 'fil.mp4', true],
    ['Invalid extension format should always ban - #3', 'fil.php', true],
    ['Known allowed extension should be OK', 'jpg', false],
    ['Known allowed extension should be OK (case insensitive)', 'JPG', false],
    ['Format allows underscores', 'fe_launch', false],
    ['Format allows dashes', 'n-gage', false],
];
foreach ($use_cases as [$name, $input, $expected]) {
    if ($expected !== is_banned_extension($input)) {
        echo "Use case: {$name} - ";
        return false;
    }
}

// Tear down
unset($use_cases);

return true;

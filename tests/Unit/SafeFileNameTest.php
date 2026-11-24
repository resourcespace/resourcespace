<?php

declare(strict_types=1);

it('normalises file names using the default character set', function (string $input, string $expected): void {
    expect(safe_file_name($input))->toBe($expected);
})->with([
    'letters, digits, underscores and dashes' => ['my_file1_2-3', 'my_file1_2-3'],
    'punctuation and non-ASCII letters are removed' => ['my.file1/!Í', 'myfile1'],
    'spaces become underscores' => ['my file1', 'my_file1'],
]);

it('normalises file names using the extended character set', function (string $input, string $expected): void {
    expect(safe_file_name($input, true))->toBe($expected);
})->with([
    'letters, digits, underscores and dashes' => ['my_file1_2-3', 'my_file1_2-3'],
    'punctuation is removed' => ['my.file1/!', 'myfile1'],
    'spaces become underscores and accented letters remain' => ['my file1ă', 'my_file1ă'],
    'non-ASCII letters are preserved' => ['test-ă_fileÍ', 'test-ă_fileÍ'],
]);

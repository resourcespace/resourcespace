<?php

declare(strict_types=1);

$saved_use_error_exception = [];
beforeEach(function () use (&$saved_use_error_exception): void {
    $saved_use_error_exception = array_intersect_key($GLOBALS, ['use_error_exception' => true]);
});

afterEach(function () use (&$saved_use_error_exception): void {
    unset($GLOBALS['use_error_exception']);
    foreach ($saved_use_error_exception as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('parses the filename extension', function (string $filename, string $expected): void {
    expect(parse_filename_extension($filename))->toBe($expected);
})->with([
    'File name (no path)' => [
        'test.jpg',
        'jpg',
    ],
    'File name (with path)' => [
        '/tmp/test.jpg',
        'jpg',
    ],
    'Double extension' => [
        'test.jpg.php',
        'php',
    ],
    'Null bytes' => [
        "test.php\0.jpg",
        '',
    ],
    'Windows (DOS) 8.3 short path (allowed only if an actual file)' => [
        'HTACCE~1',
        '',
    ],
    'Hidden file without extension' => [
        '.hidden-file',
        '',
    ],
    'Hidden file with extension' => [
        '.hidden-file.jpg',
        'jpg',
    ],
]);

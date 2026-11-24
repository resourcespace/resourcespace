<?php

declare(strict_types=1);

$saved_banned_extensions = [];
beforeEach(function () use (&$saved_banned_extensions): void {
    $saved_banned_extensions = array_intersect_key($GLOBALS, ['banned_extensions' => true]);
    $GLOBALS['banned_extensions'] = ['php'];
});

afterEach(function () use (&$saved_banned_extensions): void {
    unset($GLOBALS['banned_extensions']);
    foreach ($saved_banned_extensions as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('checks whether the file extension is banned', function (string $extension, bool $expected): void {
    expect(is_banned_extension($extension))->toBe($expected);
})->with([
    'Extension longer than 10 chars should be banned' => ['longfileextension', true],
    'Empty string should be banned' => ['', true],
    'Double quotes should be banned (legacy - before having ps_*)' => ['"', true],
    'Path traversal attempt should be banned - #1' => ['.', true],
    'Path traversal attempt should be banned - #2' => ['../../somefile.jpg', true],
    'Known banned extension not allowed' => ['php', true],
    'Known banned extension not allowed (case insensitive)' => ['PHP', true],
    'Bypassing known banned extension using a dot' => ['.php', true],
    'Bypassing known banned extension using two dots' => ['..php', true],
    'Invalid extension format should always ban - #1' => ['.mp4', true],
    'Invalid extension format should always ban - #2' => ['fil.mp4', true],
    'Invalid extension format should always ban - #3' => ['fil.php', true],
    'Known allowed extension should be OK' => ['jpg', false],
    'Known allowed extension should be OK (case insensitive)' => ['JPG', false],
    'Format allows underscores' => ['fe_launch', false],
    'Format allows dashes' => ['n-gage', false],
]);

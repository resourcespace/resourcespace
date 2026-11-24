<?php

declare(strict_types=1);

it('looks up MIME types by extension', function (string $extension, array $expected): void {
    expect(get_mime_types_by_extension($extension))->toBe($expected);
})->with([
    'single MIME type' => ['jpg', ['image/jpeg']],
    'multiple MIME types in preference order' => ['mp4', ['video/mp4', 'audio/mp4', 'video/x-m4v']],
    'unknown extension' => ['invalidExt', []],
    'case-insensitive extension' => ['JpG', ['image/jpeg']],
]);

$original_mime_types = [];
it('ignores a MIME type consisting only of whitespace', function () use (&$original_mime_types): void {
    $original_mime_types = $GLOBALS['mime_types_by_extension'];
    $GLOBALS['mime_types_by_extension']['testonlyspaces'] = '   ';

    expect(get_mime_types_by_extension('testOnlySpaces'))->toBe([]);
})->after(function () use (&$original_mime_types): void {
    $GLOBALS['mime_types_by_extension'] = $original_mime_types;
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;

it('uses the file name to determine the possible MIME types when no file is available', function (
    string $path,
    ?string $extension,
    ?bool $by_contents,
    array $expected,
): void {
    $missing = sys_get_temp_dir() . '/pest-mime-' . generateRandomTestID(16) . $path;
    expect(get_mime_type($missing, $extension, $by_contents))->toBe($expected);
})->with([
    'extension from name' => ['.jpg', null, null, ['image/jpeg']],
    'explicit extension' => ['.jpg', 'jpg', null, ['image/jpeg']],
    'unknown extension' => ['', null, false, ['application/octet-stream']],
    'multiple known types' => ['.mp4', 'mp4', null, ['video/mp4', 'audio/mp4', 'video/x-m4v']],
]);

$text_file = null;
afterEach(function () use (&$text_file): void {
    if (is_resource($text_file)) {
        fclose($text_file);
        $text_file = null;
    }
});

it('can detect MIME from contents even when the supplied extension disagrees', function () use (&$text_file): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('exiftool')) {
        $this->markTestSkipped('File-content detection requires ExifTool.');
    }
    $text_file = tmpfile();
    expect($text_file)->toBeResource();
    expect(fwrite($text_file, "A plain text fixture.\n"))->toBeGreaterThan(0);
    $path = stream_get_meta_data($text_file)['uri'];
    expect(get_mime_type($path, 'jpg', true))->toBe(['text/plain'])
        ->and(get_mime_type($path, 'jpg', false))->toBe(['image/jpeg'])
        ->and(get_mime_type(testFixture('Images/image_1.jpg'), null, true))->toBe(['image/jpeg']);
});

<?php

declare(strict_types=1);

it('uses the temporary identifier to distinguish uploads of the same file', function (): void {
    $first_id = str_repeat('a', 32);
    $second_id = str_repeat('b', 32);
    $first = get_tus_upload_filename('replacement-file.tif', $first_id);
    $second = get_tus_upload_filename('replacement-file.tif', $second_id);

    expect($first)->toBeString()->toStartWith($first_id . '-')->toEndWith('.tif')
        ->and($second)->toBeString()->toStartWith($second_id . '-')->not->toBe($first);
});

it('rejects invalid temporary upload identifiers', function (string $id): void {
    expect(get_tus_upload_filename('replacement-file.tif', $id))->toBeFalse();
})->with(['path segment' => '../invalid', 'trailing newline' => str_repeat('a', 32) . "\n"]);

it('retains the legacy key when no temporary identifier is provided', function (): void {
    expect(get_tus_upload_filename('replacement-file.tif', '', 2))->toStartWith('2-');
});

it('rejects a completed upload after extra data is appended', function (): void {
    $completed = tmpfile();
    expect(is_resource($completed))->toBeTrue();
    $content = str_repeat('ResourceSpace upload data ', 128);
    $path = stream_get_meta_data($completed)['uri'];
    $size = strlen($content);

    expect(fwrite($completed, $content))->toBe($size)
        ->and(completed_upload_size_matches($path, (string) $size))->toBeTrue()
        ->and(fwrite($completed, substr($content, 0, 256)))->toBe(256)
        ->and(completed_upload_size_matches($path, (string) $size))->toBeFalse();
    fclose($completed);
});

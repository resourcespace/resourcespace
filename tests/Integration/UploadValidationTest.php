<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
$directory = '';
$destination = '';
beforeEach(function () use (&$fixtures, &$directory, &$destination): void {
    $fixtures = new IntegrationFixtures(['valid_upload_paths', 'use_error_exception']);
    $id = generateRandomTestID(12);
    $directory = sys_get_temp_dir() . '/pest-upload-' . $id;
    $destination = get_temp_dir(false) . '/pest_upload_' . $id . '.bin';
    expect(mkdir($directory, 0700))->toBeTrue();
    $fixtures->onCleanup(static function () use ($directory, $destination): void {
        rcRmdir($directory);
        if (is_file($destination)) {
            unlink($destination);
        }
    });
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('rejects invalid local input before moving it into the filestore', function (
    string $filename,
    ?string $bytes,
    array $processor,
    ProcessFileUploadErrorCondition $expected,
) use (&$directory, &$destination): void {
    $source = $directory . '/' . $filename;
    if ($bytes !== null) {
        expect(file_put_contents($source, $bytes))->toBe(strlen($bytes));
    }
    $result = process_file_upload(new SplFileInfo($source), new SplFileInfo($destination), $processor);
    expect($result)->toBe(['success' => false, 'error' => $expected])
        ->and($destination)->not->toBeFile();
})->with([
    'missing' => ['missing.txt', null, [], ProcessFileUploadErrorCondition::MissingSourceFile],
    'special' => ['.htaccess', 'fixture', [], ProcessFileUploadErrorCondition::SpecialFile],
    'banned' => ['disabled.php', 'fixture', [], ProcessFileUploadErrorCondition::InvalidExtension],
    'banned in dry run' => [
        'disabled.php', 'fixture', ['file_move' => 'dry_run'], ProcessFileUploadErrorCondition::InvalidExtension,
    ],
    'binary with image extension' => [
        'invalid.jpg', str_repeat("\x00\xFF", 32), [], ProcessFileUploadErrorCondition::InvalidMimeType,
    ],
    'unsafe MIME with image extension' => [
        'mismatch.jpg', '<?php /* MIME fixture */', [], ProcessFileUploadErrorCondition::MimeTypeMismatch,
    ],
]);

it('uses the upload path policy for local sources', function () use (&$destination): void {
    $GLOBALS['valid_upload_paths'] = [];
    $result = process_file_upload(new SplFileInfo(__FILE__), new SplFileInfo($destination), []);
    expect($result)->toBe(['success' => false, 'error' => ProcessFileUploadErrorCondition::InvalidUploadPath]);
});

it('raises a developer error for invalid upload arguments', function (
    string $argument,
    string $message,
) use (&$directory, &$destination): void {
    $path = $directory . '/input.txt';
    expect(file_put_contents($path, 'Fixture'))->toBe(7);
    $source = $argument === 'http' ? ['name' => 'input.txt', 'tmp_name' => $path] : new SplFileInfo($path);
    $target = match ($argument) {
        'directory' => get_temp_dir(false),
        'target' => $directory . '/outside.txt',
        default => $destination,
    };
    $processor = $argument === 'mode' ? ['file_move' => 'undefined'] : [];
    $GLOBALS['use_error_exception'] = true;
    expect(fn () => process_file_upload($source, new SplFileInfo($target), $processor))
        ->toThrow(ErrorException::class, $message);
})->with([
    'unverified multipart source' => ['http', 'Invalid $source input'],
    'directory destination' => ['directory', 'Destination path must be for a file'],
    'disallowed destination' => ['target', 'Destination path not allowed'],
    'unknown processor' => ['mode', 'Invalid upload (file move) processor'],
]);

it('logs unknown file extensions and accepts safe MIME mismatches', function () use (
    &$fixtures,
    &$directory,
    &$destination,
): void {
    $extension = generateRandomTestID(10);
    $note = "Unknown MIME type for file extension '{$extension}'";
    $fixtures->onCleanup(static function () use ($note): void {
        ps_query('DELETE FROM activity_log WHERE log_code = ? AND note = ?', ['s', LOG_CODE_SYSTEM, 's', $note]);
    });
    $unknown = $directory . '/unknown.' . $extension;
    $safe = $directory . '/image.txt';
    expect(file_put_contents($unknown, 'Unknown type'))->toBe(12)
        ->and(copy(testFixture('Images/image_1.jpg'), $safe))->toBeTrue();
    foreach ([$unknown, $safe] as $source) {
        $result = process_file_upload(
            new SplFileInfo($source),
            new SplFileInfo($destination),
            ['file_move' => 'copy']
        );
        expect($result['success'])->toBeTrue()->and(file_get_contents($destination))->toBe(file_get_contents($source));
    }
    expect((int) ps_value(
        'SELECT COUNT(*) AS value FROM activity_log WHERE log_code = ? AND note = ?',
        ['s', LOG_CODE_SYSTEM, 's', $note],
        0
    ))->toBe(1);
});

it('moves a genuine multipart upload and keeps an invalid dry run out of the filestore', function (
    string $filename,
    string $bytes,
    string $mode,
    ?ProcessFileUploadErrorCondition $error,
) use (&$fixtures, &$directory): void {
    $id = generateRandomTestID(12);
    $input = $directory . '/' . $filename;
    $target = get_temp_dir(false) . '/pest_multipart_' . $id . '.bin';
    $fixtures->onCleanup(static function () use ($target): void {
        if (is_file($target)) {
            unlink($target);
        }
    });
    expect(file_put_contents($input, $bytes))->toBe(strlen($bytes));
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $response = $server->request('/tests/Fixtures/multipart-processor.php', [
        'fixture' => $id, 'mode' => $mode, 'file' => new CURLFile($input),
    ], ['Origin: ' . $GLOBALS['baseurl']]);
    expect($response['status'])->toBe(200, substr(strip_tags($response['body']), 0, 300))
        ->and(json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['success' => $error === null, 'error' => $error?->name]);
    if ($error === null) {
        expect(file_get_contents($target))->toBe($bytes);
    } else {
        expect($target)->not->toBeFile();
    }
})->with([
    'plain text' => ['upload.txt', "File body\n", '', null],
    'CSV with explicit MIME policy' => ['upload.csv', "x,y\n1,2", 'csv', null],
    'empty dry run' => ['empty.txt', '', 'dry_run', ProcessFileUploadErrorCondition::EmptySourceFile],
]);

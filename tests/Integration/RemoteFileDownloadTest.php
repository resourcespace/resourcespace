<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['valid_upload_remote_sources', 'use_error_exception']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('copies a supported local file into temporary storage and reuses its destination', function () use (&$fixtures): void {
    $input = testFixture('Images/image_1.jpg');
    $key = generateRandomTestID(12);
    $output = temp_local_download_remote_file($input, $key);
    if (is_string($output)) {
        $fixtures->onCleanup(static function () use ($output): void {
            unlink($output);
            rmdir(dirname($output));
        });
    }
    expect($output)->toBeString()->toBeFile()->not->toBe($input);
    expect(hash_file('sha256', $output))->toBe(hash_file('sha256', $input))
        ->and(temp_local_download_remote_file($input, $key))->toBe($output);
});

it('rejects unsupported file types and invalid transfer keys at the copy boundary', function (): void {
    expect(temp_local_download_remote_file('/a-test-file.php'))->toBeFalse()
        ->and(temp_local_download_remote_file('http://127.0.0.1/a-test-file.php'))->toBeFalse()
        ->and(temp_local_download_remote_file(testFixture('Images/image_1.jpg'), 'invalid-key'))->toBeFalse();
});

it('downloads exact bytes from static and allowlisted application download sources', function (
    bool $dynamic,
) use (&$fixtures): void {
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));

    $input = testFixture('Images/image_1.jpg');
    $url = $server->url . '/tests/Fixtures/Files/Images/image_1.jpg';
    if ($dynamic) {
        $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
        $original = get_resource_path($resource, true, '', true, 'jpg');
        expect(copy($input, $original))->toBeTrue();
        $endpoint = $server->url . '/pages/download.php';
        $GLOBALS['valid_upload_remote_sources'] = [$endpoint];
        $url = generateURL($endpoint, [
            'ref' => $resource,
            'ext' => 'jpg',
            'access_key' => generate_temp_download_key($GLOBALS['userref'], $resource, ''),
        ]);
    }

    $output = temp_local_download_remote_file($url, generateRandomTestID(12));
    if (is_string($output)) {
        $fixtures->onCleanup(static function () use ($output): void {
            unlink($output);
            rmdir(dirname($output));
        });
    }
    expect($output)->toBeString()->toBeFile();
    expect(hash_file('sha256', $output))->toBe(hash_file('sha256', $input))
        ->and(pathinfo($output, PATHINFO_EXTENSION))->toBe('jpg');
})->with(['static file' => [false], 'download attachment' => [true]]);

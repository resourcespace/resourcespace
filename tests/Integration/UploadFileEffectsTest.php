<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'valid_upload_paths', 'enable_thumbnail_creation_on_upload', 'replace_resource_preserve_option',
        'notify_on_resource_change_days', 'alternative_file_previews',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('creates and preserves the correct files for each upload mode', function (
    string $mode,
    bool $source_exists,
    bool $destination_exists,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $source = sys_get_temp_dir() . "/rs_upload_{$id}.txt";
    $destination = get_temp_dir(false) . "/rs_upload_destination_{$id}.txt";
    $content = "Pest upload bytes\n";
    $fixtures->onCleanup(static function () use ($source, $destination): void {
        foreach ([$source, $destination] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    });
    expect(file_put_contents($source, $content))->toBe(strlen($content));

    $result = process_file_upload(new SplFileInfo($source), new SplFileInfo($destination), ['file_move' => $mode]);
    expect($result['success'] ?? false)->toBeTrue()
        ->and(is_file($source))->toBe($source_exists)
        ->and(is_file($destination))->toBe($destination_exists);
    if ($source_exists) {
        expect(file_get_contents($source))->toBe($content);
    }
    if ($destination_exists) {
        expect(file_get_contents($destination))->toBe($content);
    }
})->with([
    'copy' => ['copy', true, true],
    'dry run' => ['dry_run', true, false],
    'rename' => ['rename', false, true],
]);

it('preserves the existing destination when a replacement is rejected', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $source = sys_get_temp_dir() . "/rs_upload_{$id}.txt";
    $destination = get_temp_dir(false) . "/rs_upload_destination_{$id}.txt";
    $fixtures->onCleanup(static function () use ($source, $destination): void {
        foreach ([$source, $destination] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    });
    expect(file_put_contents($source, ''))->toBe(0)
        ->and(file_put_contents($destination, 'Existing bytes'))->toBeInt();

    $result = process_file_upload(new SplFileInfo($source), new SplFileInfo($destination), ['file_move' => 'rename']);
    expect($result)->toBe(['success' => false, 'error' => ProcessFileUploadErrorCondition::EmptySourceFile])
        ->and($source)->toBeFile()
        ->and(file_get_contents($destination))->toBe('Existing bytes');
});

it('restores the backed-up file after replacement and logs the reverted version', function () use (&$fixtures): void {
    require_once dirname(__DIR__, 2) . '/include/image_processing.php';
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
    $GLOBALS['enable_thumbnail_creation_on_upload'] = false;
    $GLOBALS['replace_resource_preserve_option'] = true;
    $GLOBALS['alternative_file_previews'] = false;
    $GLOBALS['notify_on_resource_change_days'] = 0;

    $resource = $fixtures->resource(1, 0);
    $first = testFixture('Images/image_1.jpg');
    $second = testFixture('Images/simple_with_embedded_metadata.jpg');
    $first_hash = hash_file('sha256', $first);
    $second_hash = hash_file('sha256', $second);
    $replacement = get_temp_dir(false) . '/replacement_' . generateRandomTestID(12) . '.jpg';
    $fixtures->onCleanup(static function () use ($replacement): void {
        if (is_file($replacement)) {
            unlink($replacement);
        }
    });
    expect(copy($second, $replacement))->toBeTrue()
        ->and($second_hash)->not->toBe($first_hash)
        ->and(upload_file($resource, true, file_path: $first, deletesource: false))->toBeTrue();

    $path = get_resource_path($resource, true, '', false, 'jpg');
    expect(hash_file('sha256', $path))->toBe($first_hash);
    expect(replace_resource_file($resource, $replacement, true))->toBeTrue();
    expect(hash_file('sha256', $path))->toBe($second_hash);
    $alternatives = get_alternative_files($resource);
    expect($alternatives)->toHaveCount(1)
        ->and(array_column(get_resource_log($resource)['data'], 'type'))->toContain(LOG_CODE_REPLACED);

    expect(revert_resource_file($resource, ['previous_file_alt_ref' => $alternatives[0]['ref']], false))->toBeTrue();
    expect(hash_file('sha256', $path))->toBe($first_hash);
    $revert = ps_query(
        'SELECT previous_file_alt_ref FROM resource_log WHERE resource = ? AND type = ? AND notes = ?',
        ['i', $resource, 's', LOG_CODE_UPLOADED, 's', $GLOBALS['lang']['revert_log_note']]
    );
    expect($revert)->toHaveCount(1);
    $backed_up = get_resource_path($resource, true, '', false, 'jpg', alternative: $revert[0]['previous_file_alt_ref']);
    expect(hash_file('sha256', $backed_up))->toBe($second_hash);
});

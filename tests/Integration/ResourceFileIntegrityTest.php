<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
$script_type = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'file_integrity_checks', 'file_integrity_ignore_states', 'file_integrity_ignore_resource_types',
        'file_checksums', 'file_checksums_offline', 'file_checksums_50k', 'enable_thumbnail_creation_on_upload',
        'valid_upload_paths',
    ]);
    $GLOBALS['enable_thumbnail_creation_on_upload'] = false;
    $GLOBALS['file_integrity_checks'] = true;
    $GLOBALS['file_checksums'] = true;
    $GLOBALS['file_checksums_offline'] = false;
    $GLOBALS['file_checksums_50k'] = false;
    $GLOBALS['file_integrity_ignore_states'] = [];
    $GLOBALS['file_integrity_ignore_resource_types'] = [];
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
});
afterEach(function () use (&$fixtures, &$script_type): void {
    try {
        $fixtures?->cleanUp();
    } finally {
        if ($script_type !== null) {
            ps_query('DELETE FROM resource_type WHERE ref = ?', ['i', $script_type]);
            clear_query_cache('schema');
            $script_type = null;
        }
    }
});

it('checks presence and checksums and excludes configured resource types and workflow states', function () use (&$fixtures): void {
    $refs = [$present, $missing, $changed] = [
        $fixtures->resource(1, 0), $fixtures->resource(2, 0), $fixtures->resource(1, 2),
    ];
    foreach ($refs as $resource) {
        expect(upload_file($resource, true, false, false, testFixture('Images/image_1.jpg'), false, false))
            ->toBeTrue();
        expect(generate_file_checksum($resource, 'jpg'))->toBeTrue();
    }
    expect(validate_resource_files($refs, ['file_exists' => true]))
        ->toEqual([$present => true, $missing => true, $changed => true]);

    expect(unlink(get_resource_path($missing, true, '', false, 'jpg')))->toBeTrue();
    expect(validate_resource_files($refs, ['file_exists' => true]))
        ->toEqual([$present => true, $missing => false, $changed => true]);

    $GLOBALS['file_integrity_ignore_states'] = [2];
    $GLOBALS['file_integrity_ignore_resource_types'] = [2];
    $match_fixtures = static fn (array $row): bool => in_array($row['ref'], $refs, true);
    expect(array_column(array_filter(get_resources_to_validate(), $match_fixtures), 'ref'))->toBe([$present]);

    $GLOBALS['file_integrity_ignore_states'] = [];
    $GLOBALS['file_integrity_ignore_resource_types'] = [];
    $path = get_resource_path($changed, true, '', false, 'jpg');
    expect(file_put_contents($path, 'Changed after verification', FILE_APPEND))->toBeGreaterThan(0);
    $candidates = array_values(get_resource_data_batch($refs));
    expect(array_column($candidates, 'ref'))->toEqualCanonicalizing($refs);
    expect(check_resources($candidates))->toEqualCanonicalizing([$missing, $changed]);
});

it('reports a checksum written with the previous setting without marking it as corrupt', function () use (
    &$fixtures,
    &$script_type,
): void {
    $script_type = create_resource_type('Integrity fixture ' . generateRandomTestID(12));
    $ref = $fixtures->resource($script_type, 0);
    expect(upload_file($ref, true, false, false, testFixture('Images/image_1.jpg'), false, false))->toBeTrue();
    $original = get_resource_path($ref, true, '', false, 'jpg');
    expect(file_put_contents($original, str_repeat('x', 65536), FILE_APPEND))->toBe(65536)
        ->and(generate_file_checksum($ref, 'jpg'))->toBeTrue();

    $output = LocalTestScript::run('pages/tools/validate_resource_files.php', [
        'file_integrity_checks' => true,
        'file_checksums' => true,
        'file_checksums_50k' => true,
        'file_integrity_ignore_states' => [],
        'file_integrity_ignore_resource_types' => array_values(array_diff(
            array_column(get_resource_types(), 'ref'),
            [$script_type]
        )),
    ], ['--silence-notices']);
    expect($output)->toContain('old checksum recorded: ' . $ref, 'There were 0 failures')
        ->and((int) get_resource_data($ref, false)['integrity_fail'])->toBe(0);
});

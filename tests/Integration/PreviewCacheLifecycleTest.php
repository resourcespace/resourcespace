<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'system_read_only', 'preview_generate_max_file_size', 'minimal_previews_sizes',
        'minimal_preview_creation_exclude_extensions', 'enable_thumbnail_creation_on_upload', 'image_alternatives',
        'alternative_file_previews', 'previews_allow_enlarge', 'offline_job_queue', 'use_error_exception',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('adds preview cache columns and the composite index to the previous schema', function (): void {
    // Connection-local tables exercise the normal schema updater without changing existing test data.
    $GLOBALS['use_error_exception'] = true;
    ps_query('CREATE TEMPORARY TABLE previous_resource_dimensions LIKE resource_dimensions', dbstruct: false);
    ps_query('CREATE TEMPORARY TABLE previous_resource LIKE resource', dbstruct: false);
    try {
        ps_query(
            'CREATE TEMPORARY TABLE resource_dimensions LIKE previous_resource_dimensions',
            dbstruct: false
        );
        ps_query('CREATE TEMPORARY TABLE resource LIKE previous_resource', dbstruct: false);
        ps_query('ALTER TABLE resource_dimensions DROP INDEX resource_preview,
            DROP COLUMN preview_size, DROP COLUMN file_modified, DROP COLUMN path_hash', dbstruct: false);
        ps_query('ALTER TABLE resource DROP COLUMN blurhash', dbstruct: false);
        CheckDBStruct(RESOURCESPACE_BASE_PATH . '/dbstruct');

        $dimensions = array_column(ps_query('SHOW COLUMNS FROM resource_dimensions'), null, 'Field');
        expect($dimensions)->toHaveKeys(['preview_size', 'file_modified', 'path_hash']);
        $index = array_values(array_filter(
            ps_query('SHOW INDEX FROM resource_dimensions'),
            static fn (array $row): bool => $row['Key_name'] === 'resource_preview'
        ));
        expect(array_column($index, 'Column_name'))->toBe(['resource', 'preview_size'])
            ->and(array_map('intval', array_column($index, 'Non_unique')))->toBe([0, 0]);
        $columns = array_column(ps_query('SHOW COLUMNS FROM resource'), null, 'Field');
        expect($columns['blurhash']['Null'])->toBe('YES');
    } finally {
        ps_query('DROP TEMPORARY TABLE IF EXISTS resource, resource_dimensions,
            previous_resource, previous_resource_dimensions', dbstruct: false);
    }
});

it('caches only valid fresh preview dimensions and preserves original size information', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $path = get_resource_path($resource, true, 'scr', true, 'jpg');
    $image_at = static function (int $width, int $height) use ($path): void {
        expect(imagejpeg(imagecreatetruecolor($width, $height), $path))->toBeTrue();
    };
    $cached = static fn (): array => ps_query(
        'SELECT * FROM resource_dimensions WHERE resource = ? AND preview_size IS NOT NULL',
        ['i', $resource]
    );
    $original = static fn (): array => ps_query(
        'SELECT * FROM resource_dimensions WHERE resource = ? AND preview_size IS NULL',
        ['i', $resource]
    );
    ps_query(
        'INSERT INTO resource_dimensions (resource, width, height, file_size, page_count) VALUES (?, 1000, 2000, 12345, 7)',
        ['i', $resource]
    );
    $initial = $original();
    $image_at(40, 20);
    $sizes = static fn (bool $exists = true): array =>
        array_column(get_image_sizes($resource, false, 'jpg', $exists), null, 'id');
    expect($sizes()['scr'])->toMatchArray(['width' => 40, 'height' => 20])
        ->and($cached())->toHaveCount(1);

    ps_query('UPDATE resource_dimensions SET width = 41 WHERE resource = ? AND preview_size = ?', ['i', $resource, 's', 'scr']);
    expect($sizes()['scr']['width'])->toBe(41);
    $image_at(60, 30);
    expect(touch($path, time() + 5))->toBeTrue();
    expect($sizes()['scr'])->toMatchArray(['width' => 60, 'height' => 30])
        ->and($cached())->toHaveCount(1)
        ->and($original())->toBe($initial)
        ->and((int) get_page_count(get_resource_data($resource, false)))->toBe(7);
    expect(array_column(do_search('!propertiesorientation:landscape'), 'ref'))->not->toContain($resource);

    $permissions = $GLOBALS['userpermissions'];
    $GLOBALS['userpermissions'][] = 'T1_scr';
    expect($sizes())->not->toHaveKey('scr');
    $GLOBALS['userpermissions'] = $permissions;
    clear_preview_size_cache($resource);
    expect($cached())->toBeEmpty();

    $GLOBALS['system_read_only'] = true;
    expect(get_preview_size_info($resource, 'scr', $path, [])['width'])->toBe(60)
        ->and($cached())->toBeEmpty();
    $GLOBALS['system_read_only'] = false;
    expect(file_put_contents($path, 'Not an image'))->toBeInt();
    expect(get_preview_size_info($resource, 'scr', $path, [])['width'])->toBe(0)
        ->and($cached())->toBeEmpty()
        ->and(unlink($path))->toBeTrue();
    expect($sizes(false)['scr'])->toMatchArray(['width' => 0, 'filesize' => formatfilesize(0)])
        ->and($cached())->toBeEmpty();
});

it('invalidates preview dimensions on rotation and deletion', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Rotating previews requires ImageMagick.');
    }
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $path = get_resource_path($resource, true, 'pre', true, 'jpg');
    $entries = static fn (): int => (int) ps_value(
        'SELECT COUNT(*) AS value FROM resource_dimensions WHERE resource = ? AND preview_size IS NOT NULL',
        ['i', $resource],
        0
    );
    expect(imagejpeg(imagecreatetruecolor(40, 20), $path))->toBeTrue();
    expect(get_preview_size_info($resource, 'pre', $path, [])['width'])->toBe(40)
        ->and($entries())->toBe(1);

    expect(tweak_preview_images($resource, 90, 1))->toBeTrue()->and($entries())->toBe(0);
    get_image_sizes($resource, true);
    expect($entries())->toBeGreaterThan(0);
    delete_previews($resource);
    expect($entries())->toBe(0);
});

it('generates minimal and full previews, image alternatives and refreshed thumbnail hashes', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Thumbnail previews and conversion require ImageMagick.');
    }
    $GLOBALS['enable_thumbnail_creation_on_upload'] = false;
    $GLOBALS['offline_job_queue'] = false;
    $GLOBALS['minimal_previews_sizes'] = ['col', 'thm', 'pre'];
    $GLOBALS['minimal_preview_creation_exclude_extensions'] = [];
    $GLOBALS['image_alternatives'] = [];
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original = get_resource_path($resource, true, '', true, 'jpg');
    expect(copy(testFixture('Images/image_1.jpg'), $original))->toBeTrue();

    expect(start_previews($resource))->toBe(2);
    foreach (['col', 'thm', 'pre'] as $size) {
        expect(get_resource_path($resource, true, $size, false))->toBeFile();
    }
    expect(get_resource_path($resource, true, 'lpr', false))->not->toBeFile();
    expect(get_resource_data($resource, false)['blurhash'])->toBeString()->toHaveLength(28);

    $GLOBALS['previews_allow_enlarge'] = true;
    $GLOBALS['image_alternatives'] = [[
        'name' => 'PNG file', 'source_extensions' => 'jpg', 'source_params' => '', 'filename' => 'converted',
        'target_extension' => 'png', 'params' => '-density 300', 'icc' => false,
    ]];
    expect(create_previews($resource, extension: 'jpg'))->toBeTrue();
    expect(get_resource_path($resource, true, 'lpr', false))->toBeFile();
    $alternatives = get_alternative_files($resource);
    expect($alternatives)->toHaveCount(1);
    $alt = $alternatives[0]['ref'];
    $converted = get_resource_path($resource, true, '', false, 'png', alternative: $alt);
    expect(getimagesize($converted)[2] ?? null)->toBe(IMAGETYPE_PNG);

    ps_query('UPDATE resource SET blurhash = NULL WHERE ref = ?', ['i', $resource]);
    create_previews($resource, extension: 'jpg', onlysizes: ['thm']);
    $hash = get_resource_data($resource, false)['blurhash'];
    expect($hash)->toBeString()->toHaveLength(28);
    delete_previews($resource, $alt);
    expect(get_resource_data($resource, false)['blurhash'])->toBe($hash);
    expect(tweak_preview_images($resource, 90, 0))->toBeTrue()
        ->and(get_resource_data($resource, false)['blurhash'])->not->toBe($hash);
    delete_previews($resource);
    expect(get_resource_data($resource, false)['blurhash'])->toBeNull();
});

it('backfills existing thumbnail hashes in bounded batches without modifying file dates', function () use (&$fixtures): void {
    $previous_cursor = get_sysvar('blurhash_backfill_cursor', (string) PHP_INT_MAX);
    $previous_run = get_sysvar('last_blurhash_backfill', '1970-01-01');
    $fixtures->onCleanup(static function () use ($previous_cursor, $previous_run): void {
        set_sysvar('blurhash_backfill_cursor', $previous_cursor);
        set_sysvar('last_blurhash_backfill', $previous_run);
    });
    $missing = $fixtures->resource(1, 0, file_extension: 'jpg');
    $valid = $fixtures->resource(1, 0, file_extension: 'jpg');
    $path = get_resource_path($valid, true, 'thm', true, 'jpg');
    expect(copy(testFixture('Images/image_1.jpg'), $path))->toBeTrue();
    ps_query(
        'UPDATE resource SET has_image = 1, preview_extension = ?, file_modified = NOW() WHERE ref IN (?, ?)',
        ['s', 'jpg', 'i', $valid, 'i', $missing]
    );
    $original_date = get_resource_data($valid, false)['file_modified'];
    set_sysvar('blurhash_backfill_cursor', $valid + 1);
    expect(backfill_resource_blurhashes(1))->toBe(1)
        ->and((int) get_sysvar('blurhash_backfill_cursor'))->toBe($valid)
        ->and(get_resource_data($valid, false)['blurhash'])->toHaveLength(28)
        ->and(get_resource_data($valid, false)['file_modified'])->toBe($original_date)
        ->and(backfill_resource_blurhashes(1))->toBe(0)
        ->and((int) get_sysvar('blurhash_backfill_cursor'))->toBe($missing)
        ->and(get_resource_data($missing, false)['blurhash'])->toBeNull();

    set_sysvar('blurhash_backfill_cursor', '1');
    expect(backfill_resource_blurhashes(1))->toBe(0)
        ->and((int) get_sysvar('blurhash_backfill_cursor'))->toBe(PHP_INT_MAX);
    set_sysvar('blurhash_backfill_cursor', '1');
    set_sysvar('last_blurhash_backfill', '1970-01-01');
    require RESOURCESPACE_BASE_PATH . '/batch/cron_jobs/017_blurhash.php';
    expect(get_sysvar('last_blurhash_backfill'))->not->toBe('1970-01-01')
        ->and((int) get_sysvar('blurhash_backfill_cursor'))->toBe(PHP_INT_MAX);
});

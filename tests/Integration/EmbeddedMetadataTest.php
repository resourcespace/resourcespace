<?php

declare(strict_types=1);

use Montala\ResourceSpace\CommandPlaceholderArg;
use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
$tool = null;
beforeEach(function () use (&$fixtures, &$tool): void {
    $fixtures = new IntegrationFixtures([
        'exiftool_write', 'exiftool_write_option', 'exiftool_remove_existing', 'force_exiftool_write_metadata',
        'camera_autorotation', 'icc_extraction', 'imagemagick_mpr', 'valid_upload_paths',
        'enable_thumbnail_creation_on_upload', 'offline_job_queue', 'upload_then_process',
    ]);
    /** @var ResourceSpaceBootTestCase $this */
    $tool = get_utility_path('exiftool');
    if (!$tool) {
        $this->markTestSkipped('Embedded metadata checks require ExifTool.');
    }

    $GLOBALS['exiftool_write'] = true;
    $GLOBALS['exiftool_write_option'] = true;
    $GLOBALS['exiftool_remove_existing'] = true;
    $GLOBALS['force_exiftool_write_metadata'] = true;
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('prefers the later embedded metadata mapping and imports separate keyword values', function () use (&$fixtures, &$tool): void {
    $field = $fixtures->field('Embedded keywords', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['exiftool_field' => true]);
    expect(save_resource_type_field($field, $columns, ['exiftool_field' => 'IPTC:Keywords,XMP:Keywords']))->toBeTrue();
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $path = get_resource_path($resource, true, '', true, 'jpg');
    expect(imagejpeg(imagecreatetruecolor(48, 32), $path))->toBeTrue();

    $arguments = ['%%IMAGE%%' => new CommandPlaceholderArg($path, is_valid_rs_path(...))];
    run_command("{$tool} -m -overwrite_original -IPTC:Keywords=Apples -XMP:Keywords=Oranges %%IMAGE%%", true, $arguments);
    extract_exif_comment($resource, 'jpg');
    expect(get_data_by_field($resource, $field))->toBe('Oranges');

    run_command("{$tool} -m -overwrite_original -XMP:Keywords='Apples, Oranges' %%IMAGE%%", true, $arguments);
    extract_exif_comment($resource, 'jpg');
    expect(get_data_by_field($resource, $field))->toBe('Apples, Oranges')
        ->and(array_column(get_data_by_field($resource, $field, false), 'name'))
        ->toEqualCanonicalizing(['Apples', 'Oranges']);

    expect(save_resource_type_field($field, $columns, ['exiftool_field' => 'IPTC:Keywords']))->toBeTrue();
    extract_exif_comment($resource, 'jpg');
    expect(get_data_by_field($resource, $field))->toBe('Apples');
});

it('writes editable metadata without exporting fields marked read-only or modifying the original', function () use (&$fixtures, &$tool): void {
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original = get_resource_path($resource, true, '', true, 'jpg');
    expect(imagejpeg(imagecreatetruecolor(48, 32), $original))->toBeTrue();
    $errors = [];
    foreach ([
        ['XMP:Title', false, 'Editable copyright café'],
        ['IPTC:Credit', true, 'Read-only credit'],
        ['EXIF:Model', true, 'Read-only camera'],
    ] as [$mapping, $read_only, $value]) {
        $field = $fixtures->field('Export field ' . $mapping);
        $columns = array_intersect_key(get_resource_type_field_columns(), ['exiftool_field' => true, 'read_only' => true]);
        expect(save_resource_type_field($field, $columns, ['exiftool_field' => $mapping, 'read_only' => (int) $read_only]))
            ->toBeTrue();
        expect(update_field($resource, $field, $value, $errors))->toBeTrue();
    }
    $hash = hash_file('sha256', $original);
    $copy = write_metadata($original, $resource);
    expect($copy)->toBeString()->toBeFile();
    $fixtures->onCleanup(static function () use ($copy): void {
        if (is_file($copy)) {
            unlink($copy);
        }
    });

    $result = run_command("{$tool} -j -XMP:Title -IPTC:Credit -EXIF:Model %%IMAGE%%", true, [
        '%%IMAGE%%' => new CommandPlaceholderArg($copy, is_valid_rs_path(...)),
    ]);
    $metadata = json_decode($result, true, flags: JSON_THROW_ON_ERROR)[0];
    expect($metadata['Title'] ?? null)->toBe('Editable copyright café')
        ->and($metadata)->not->toHaveKeys(['Credit', 'Model'])
        ->and(hash_file('sha256', $original))->toBe($hash);
});

it('strips embedded metadata from a generated copy and leaves a readable image without a backup', function () use (&$fixtures, &$tool): void {
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original = get_resource_path($resource, true, '', true, 'jpg');
    expect(imagejpeg(imagecreatetruecolor(48, 32), $original))->toBeTrue();
    $title = 'Café 日本語 – metadata';
    $field = $fixtures->field('Embedded Unicode title');
    $columns = array_intersect_key(get_resource_type_field_columns(), ['exiftool_field' => true]);
    expect(save_resource_type_field($field, $columns, ['exiftool_field' => 'XMP:Title']))->toBeTrue();
    $errors = [];
    expect(update_field($resource, $field, $title, $errors))->toBeTrue();
    $hash = hash_file('sha256', $original);
    $copy = write_metadata($original, $resource);
    expect($copy)->toBeString()->toBeFile();
    $fixtures->onCleanup(static function () use ($copy): void {
        foreach ([$copy, $copy . '_original'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    });
    $arguments = ['%%IMAGE%%' => new CommandPlaceholderArg($copy, is_valid_rs_path(...))];
    $read = static fn (): array => json_decode(
        run_command("{$tool} -j -XMP:Title -EXIF:Artist -GPS:all %%IMAGE%%", true, $arguments),
        true,
        flags: JSON_THROW_ON_ERROR
    )[0];
    expect($read()['Title'] ?? null)->toBe($title)
        ->and(hash_file('sha256', $original))->toBe($hash);

    run_command(
        "{$tool} -overwrite_original -EXIF:Artist=FixtureArtist -GPSLatitude=51.5 -GPSLatitudeRef=N %%IMAGE%%",
        true,
        $arguments
    );
    $before = $read();
    expect($before['Artist'] ?? null)->toBe('FixtureArtist')->and($before)->toHaveKey('GPSLatitude');
    expect(stripMetadata($copy))->toBeTrue();
    $after = $read();
    expect(array_intersect_key($after, array_flip(['Title', 'Artist', 'GPSLatitude'])))->toBeEmpty()
        ->and(getimagesize($copy)[2] ?? null)->toBe(IMAGETYPE_JPEG)
        ->and(is_file($copy . '_original'))->toBeFalse()
        ->and(stripMetadata($original . '.missing'))->toBeFalse();
});

it('imports the date portion and retains the time only in a date-time field', function () use (&$fixtures, &$tool): void {
    $date = $fixtures->field('EXIF capture date', 0, FIELD_TYPE_DATE);
    $date_time = $fixtures->field('EXIF capture date and time', 0, FIELD_TYPE_DATE_AND_OPTIONAL_TIME);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['exiftool_field' => true]);
    foreach ([$date, $date_time] as $field) {
        expect(save_resource_type_field($field, $columns, ['exiftool_field' => 'DateTimeOriginal']))->toBeTrue();
    }
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $path = get_resource_path($resource, true, '', true, 'jpg');
    expect(imagejpeg(imagecreatetruecolor(48, 32), $path))->toBeTrue();
    run_command(
        "{$tool} -overwrite_original -EXIF:DateTimeOriginal='2024:03:14 09:26:53' %%IMAGE%%",
        true,
        ['%%IMAGE%%' => new CommandPlaceholderArg($path, is_valid_rs_path(...))]
    );

    extract_exif_comment($resource, 'jpg');
    expect(get_data_by_field($resource, $date))->toBe('2024-03-14')
        ->and(get_data_by_field($resource, $date_time))->toStartWith('2024-03-14 09:26');
});

it('honours upload rotation and removes original EXIF metadata from generated thumbnails', function (
    bool $rotate,
    bool $extract_icc,
) use (&$fixtures, &$tool): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Preview generation requires ImageMagick.');
    }

    $GLOBALS['camera_autorotation'] = true;
    $GLOBALS['icc_extraction'] = $extract_icc;
    $GLOBALS['imagemagick_mpr'] = !$extract_icc;
    $GLOBALS['enable_thumbnail_creation_on_upload'] = true;
    $GLOBALS['offline_job_queue'] = false;
    $GLOBALS['upload_then_process'] = false;
    $GLOBALS['valid_upload_paths'][] = get_temp_dir(false);
    $source = get_temp_dir(false) . '/orientation_' . generateRandomTestID(12) . '.jpg';
    $fixtures->onCleanup(static function () use ($source): void {
        if (is_file($source)) {
            unlink($source);
        }
    });
    expect(imagejpeg(imagecreatetruecolor(96, 64), $source))->toBeTrue();
    $tags = ['%%IMAGE%%' => new CommandPlaceholderArg($source, is_valid_rs_path(...))];
    run_command("{$tool} -overwrite_original -Orientation#=6 -EXIF:Artist=UploadedFixture %%IMAGE%%", true, $tags);

    $resource = $fixtures->resource(1, 0);
    expect(upload_file($resource, true, autorotate: $rotate, file_path: $source, deletesource: false))->toBeTrue();
    $original = get_resource_path($resource, true, '', false, 'jpg');
    $thumbnail = get_resource_path($resource, true, 'thm', false, 'jpg');
    expect($thumbnail)->toBeFile();
    $dimensions = getimagesize($original);
    expect([$dimensions[0], $dimensions[1]])->toBe($rotate ? [64, 96] : [96, 64]);

    $read = static fn (string $path): array => json_decode(
        run_command("{$tool} -j -n -EXIF:Orientation -EXIF:Artist %%IMAGE%%", true, [
            '%%IMAGE%%' => new CommandPlaceholderArg($path, is_valid_rs_path(...)),
        ]),
        true,
        flags: JSON_THROW_ON_ERROR
    )[0];
    expect($read($original)['Orientation'] ?? null)->toBe($rotate ? 1 : 6);
    expect($read($thumbnail))->not->toHaveKey('Artist')->not->toHaveKey('Orientation');
})->with([
    'rotation disabled' => [false, false],
    'rotation enabled' => [true, false],
    'ICC extraction without MPR' => [false, true],
]);

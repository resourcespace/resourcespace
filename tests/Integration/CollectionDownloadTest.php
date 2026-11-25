<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require_once RESOURCESPACE_BASE_PATH . '/include/csv_export_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'use_zip_extension', 'download_filename_format', 'userpermissions', 'exiftool_write_option',
        'collection_download_original', 'restricted_full_download', 'open_access_for_contributor',
        'get_resource_data_cache', 'resource_access_cache', 'download_access_cache', 'p', 'pextension',
    ]);
    $GLOBALS['use_zip_extension'] = true;
    $GLOBALS['collection_download_original'] = true;
    $GLOBALS['restricted_full_download'] = false;
    $GLOBALS['open_access_for_contributor'] = false;
    $GLOBALS['download_filename_format'] = '%resource.%extension';
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('archives permitted originals and logs only resources included in the ZIP', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!class_exists(ZipArchive::class)) {
        $this->markTestSkipped('Collection ZIP downloads require ZipArchive.');
    }
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'ZIP ' . generateRandomTestID(16));
    $rows = [];
    $contents = [];
    $expected_downloads = [];
    $cases = [
        [RESOURCE_ACCESS_FULL, true],
        [RESOURCE_ACCESS_FULL, true],
        [RESOURCE_ACCESS_CONFIDENTIAL, true],
        [RESOURCE_ACCESS_FULL, false],
        [RESOURCE_ACCESS_RESTRICTED, true],
    ];
    foreach ($cases as [$access, $exists]) {
        $ref = $fixtures->resource(1, 0, file_extension: 'txt');
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
        if ((int) get_resource_data($ref, false)['access'] !== $access) {
            expect(put_resource_data($ref, ['access' => $access]))->toBeTrue();
        }
        $contents[$ref] = 'Original bytes ' . $ref;
        if ($exists) {
            expect(file_put_contents(get_resource_path($ref, true, '', true, 'txt'), $contents[$ref]))->toBeInt();
        }
        $expected_downloads[$ref] = (int) ($access === RESOURCE_ACCESS_FULL && $exists);
        $rows[] = get_resource_data($ref, false);
    }

    $GLOBALS['userpermissions'] = ['s', 'g'];
    $GLOBALS['resource_access_cache'] = [];
    $GLOBALS['download_access_cache'] = [];
    $result = process_collection_download([
        'collection' => $collection,
        'collectiondata' => get_collection($collection),
        'size' => '',
        'text' => '',
        'collection_resources' => $rows,
        'id' => 'archive_' . generateRandomTestID(16),
        'settings_id' => '',
        'usage' => -1,
        'usagecomment' => '',
        'useoriginal' => true,
        'includetext' => true,
        'exiftool_write_option' => false,
    ]);
    $archive = $result['path'] ?? '';
    expect($archive)->toBeFile();
    $fixtures->onCleanup(static fn () => unlink($archive));
    $zip = new ZipArchive();
    expect($zip->open($archive))->toBeTrue();
    $fixtures->onCleanup(static fn () => $zip->close());

    foreach ($expected_downloads as $ref => $count) {
        expect($zip->getFromName($ref . '.txt'))->toBe($count === 1 ? $contents[$ref] : false);
        expect((int) ps_value(
            'SELECT COUNT(*) AS value FROM resource_log WHERE resource = ? AND type = ?',
            ['i', $ref, 's', LOG_CODE_DOWNLOADED],
            0
        ))->toBe($count);
    }
    expect($zip->numFiles)->toBe(3);
});

it('includes an alternative file and exported metadata in the same ZIP', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!class_exists(ZipArchive::class)) {
        $this->markTestSkipped('Collection ZIP downloads require ZipArchive.');
    }
    $title = 'Archive metadata ' . generateRandomTestID(16);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Export ' . $title);
    $ref = $fixtures->resource(1, 0, file_extension: 'txt');
    $errors = [];
    expect(add_resource_to_collection($ref, $collection))->toBeTrue()
        ->and(update_field($ref, $GLOBALS['view_title_field'], $title, $errors))->toBeTrue();
    $alternative = add_alternative_file($ref, 'Alternate data', '', 'alt.txt', 'txt');
    expect($alternative)->toBeInt();
    $original_path = get_resource_path($ref, true, '', true, 'txt');
    $alternative_path = get_resource_path($ref, true, '', true, 'txt', alternative: $alternative);
    expect(file_put_contents($original_path, 'Original text'))->toBeInt()
        ->and(file_put_contents($alternative_path, 'Alternative text'))->toBeInt();

    $result = process_collection_download([
        'collection' => $collection,
        'collectiondata' => get_collection($collection),
        'size' => '',
        'text' => '',
        'collection_resources' => [get_resource_data($ref, false)],
        'id' => 'archive_' . generateRandomTestID(16),
        'settings_id' => '',
        'usage' => -1,
        'usagecomment' => '',
        'useoriginal' => true,
        'includetext' => false,
        'include_alternatives' => true,
        'include_csv_file' => true,
        'exiftool_write_option' => false,
    ]);
    $archive = $result['path'] ?? '';
    expect($archive)->toBeFile();
    $fixtures->onCleanup(static fn () => unlink($archive));
    $zip = new ZipArchive();
    expect($zip->open($archive))->toBeTrue();
    $fixtures->onCleanup(static fn () => $zip->close());

    $csv = $zip->getFromName("Col-{$collection}-metadata-export.csv");
    expect($zip->getFromName("{$ref}.txt"))->toBe('Original text')
        ->and($zip->getFromName("{$ref}_{$alternative}.txt"))->toBe('Alternative text')
        ->and($csv)->toBeString();
    $lines = preg_split('/\R/', trim($csv));
    $data = str_getcsv($lines[1], escape: '');
    expect($data)->toContain((string) $ref)->toContain($title);
});

it('delivers the chosen image size in ZIP and TAR archives from the collection download form', function (
    string $format,
    string $choice,
    string $file_size,
) use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    $tar = $format === 'tar' ? (new ExecutableFinder())->find('tar') : null;
    if ($format === 'tar' && (PHP_OS_FAMILY !== 'Linux' || $tar === null)) {
        $this->markTestSkipped('The application TAR command requires Linux and GNU tar.');
    }
    if ($format === 'zip' && !class_exists(ZipArchive::class)) {
        $this->markTestSkipped('Collection ZIP downloads require ZipArchive.');
    }

    $id = generateRandomTestID(12);
    $user = $fixtures->user('archive_sizes_' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $collection = $fixtures->collection($user, 'Sized downloads ' . $id);
    $ref = $fixtures->resource(1, 0, $user, file_extension: 'jpg');
    expect(add_resource_to_collection($ref, $collection))->toBeTrue();
    $files = [];
    foreach (['' => [640, 320], 'scr' => [320, 160]] as $size => [$width, $height]) {
        $path = get_resource_path($ref, true, $size, true, 'jpg');
        $image = imagecreatetruecolor($width, $height);
        expect(imagejpeg($image, $path))->toBeTrue();
        $files[$size] = file_get_contents($path);
    }

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $directory = get_temp_dir(false, 'pest_download_sizes_' . $id);
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $server->configure([
        'baseurl' => $server->url,
        'tempdir' => $directory,
        'collection_download' => true,
        'collection_download_original' => true,
        'collection_download_tar_size' => 1,
        'offline_job_queue' => false,
        'download_usage' => false,
        'terms_download' => false,
        'exiftool_write' => false,
        'download_filename_format' => '%resource.%extension',
    ]);
    $headers = $server->sessionHeaders($session);
    $url = '/pages/collection_download.php?collection=' . $collection;
    $form = $server->request($url, headers: $headers);
    expect($form['status'])->toBe(200)->and($form['body'])->toContain('id="downloadsize"');
    $tokens = LocalTestServer::hiddenFields($form['body'], '//form[@id="collection_download_form"]');
    expect($tokens)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $download = $server->request($url, [
        'submitted' => 'true',
        'collection' => $collection,
        'size' => $choice,
        'tardownload' => $format === 'tar' ? 'on' : 'off',
    ] + $tokens, $headers);
    expect($download['status'])->toBe(200)
        ->and($download['headers']['content-type'])->toStartWith('application/' . $format);
    $path = $directory . '/download.' . $format;
    expect(file_put_contents($path, $download['body']))->toBeGreaterThan(0);
    $name = $ref . '.jpg';
    if ($tar !== null) {
        $list = new Process([$tar, '-tf', $path]);
        $list->mustRun();
        expect(trim($list->getOutput()))->toBe($name);
        $extract = new Process([$tar, '-xOf', $path, '--', $name]);
        $extract->mustRun();
        expect($extract->getOutput())->toBe($files[$file_size]);
    } else {
        $archive = new ZipArchive();
        expect($archive->open($path))->toBeTrue();
        try {
            expect($archive->numFiles)->toBe(1)->and($archive->getFromName($name))->toBe($files[$file_size]);
        } finally {
            $archive->close();
        }
    }
})->with([
    'TAR original' => ['tar', 'original', ''],
    'TAR screen' => ['tar', 'scr', 'scr'],
    'ZIP largest' => ['zip', 'largest', ''],
    'ZIP screen' => ['zip', 'scr', 'scr'],
]);

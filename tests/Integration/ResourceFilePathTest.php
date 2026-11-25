<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'hide_real_filepath', 'scramble_key', 'syncdir', 'originals_separate_storage', 'fstemplate_alt_threshold',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('builds consistent scrambled paths and URLs for tagged versions of a resource', function () use (&$fixtures): void {
    $GLOBALS['hide_real_filepath'] = false;
    $GLOBALS['originals_separate_storage'] = false;
    $GLOBALS['fstemplate_alt_threshold'] = 0;
    $GLOBALS['scramble_key'] = 'resource-path fixture seed';
    $ref = $fixtures->resource(1, 0, file_extension: 'jpg');
    $disk = get_resource_path($ref, true, generate: false);
    $folder = dirname($disk);
    $fixtures->onCleanup(static function () use ($folder): void {
        if (is_dir($folder)) {
            rmdir($folder);
        }
    });
    expect($folder)->not->toBeDirectory();
    expect(get_resource_path($ref, true))->toBe($disk);
    expect($folder)->toBeDirectory();
    expect(basename($folder))->toMatch('/^\d_[a-f0-9]{15}$/');
    expect(basename($disk))->toMatch("/^{$ref}_[a-f0-9]{15}\\.jpg$/");
    expect(get_resource_path($ref, true, null))->toBe($disk);

    $preview = get_resource_path($ref, true, 'pre', false);
    expect($preview)->not->toBe($disk);
    expect(get_resource_path($ref, true, 'pre', false, 'mp4'))->toBe(substr($preview, 0, -3) . 'mp4');
    expect(get_resource_path($ref, true, 'pre', false, null))->toBe($preview);
    expect(get_resource_path($ref, true, 'pre', false, 'php'))->toBe(substr($preview, 0, -3) . 'php');
    expect(basename(get_resource_path($ref, true, 'pre', false, scramble: false)))->toBe("{$ref}pre.jpg");
    expect(basename(get_resource_path($ref, true, 'pre', false, page: 3)))->toMatch("/^{$ref}pre_3_[a-f0-9]{15}\\.jpg$/");
    expect(basename(get_resource_path($ref, true, 'pre', false, watermarked: true)))
        ->toMatch("/^{$ref}pre_wm_[a-f0-9]{15}\\.jpg$/");
    expect(basename(get_resource_path($ref, true, 'pre', false, alternative: 10)))
        ->toMatch("/^{$ref}pre_alt_10_[a-f0-9]{15}\\.jpg$/");

    $url = rtrim($GLOBALS['storageurl'], '/') . substr($preview, strlen(rtrim($GLOBALS['storagedir'], '/')));
    expect(get_resource_path($ref, false, 'pre', false, file_modified: '1234'))->toBe($url . '?v=1234')
        ->and(get_resource_path($ref, false, 'pre', false, includemodified: false))->toBe($url);
    expect(get_resource_path($ref, true, '/../../../some/file', false))
        ->toBe(get_resource_path($ref, true, 'somefile', false));
    expect(get_resource_path($ref, true, 'pre', false, '/../../../some/file'))
        ->toBe(get_resource_path($ref, true, 'pre', false, 'somefile'));

    $GLOBALS['hide_real_filepath'] = true;
    $download = parse_url(get_resource_path($ref, false, 'pre', false, page: 3, file_modified: '1234'));
    parse_str($download['query'], $params);
    expect($download['path'])->toEndWith('/pages/download.php')
        ->and($params)->toMatchArray(['ref' => (string) $ref, 'size' => 'pre', 'page' => '3', 'v' => '1234']);
});

it('validates current and future file paths against the configured storage roots', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $photo = get_resource_path($resource, true, '', true, 'jpg');
    $future = get_resource_path($resource, true, 'future', false, 'jpg');
    $temporary = get_temp_dir(false) . '/pest_' . generateRandomTestID(10) . '.txt';
    $directory = sys_get_temp_dir() . '/rs_path_' . generateRandomTestID(10);
    $link = $GLOBALS['storagedir'] . '/fixture_' . generateRandomTestID(10);
    $GLOBALS['syncdir'] = $directory . '/sync';
    $sync = $GLOBALS['syncdir'] . '/sync.jpg';
    $unrelated = $directory . '/unrelated.txt';
    $fixtures->onCleanup(static function () use ($temporary, $directory, $sync, $unrelated, $link): void {
        if (is_link($link)) {
            unlink($link);
        }
        foreach ([$temporary, $sync, $unrelated] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ([$directory . '/sync', $directory] as $folder) {
            if (is_dir($folder)) {
                rmdir($folder);
            }
        }
    });
    expect(mkdir($GLOBALS['syncdir'], 0700, true))->toBeTrue();
    foreach ([$photo, $sync, $temporary, $unrelated] as $path) {
        expect(file_put_contents($path, 'path fixture'))->toBeInt();
    }

    expect(is_valid_rs_path(RESOURCESPACE_BASE_PATH . '/gfx/watermark.png'))->toBeTrue()
        ->and(is_valid_rs_path($photo))->toBeTrue()
        ->and(is_valid_rs_path($future))->toBeTrue()
        ->and(is_valid_rs_path($sync))->toBeTrue()
        ->and(is_valid_rs_path($temporary))->toBeTrue()
        ->and(is_valid_rs_path($unrelated))->toBeFalse()
        ->and(is_valid_rs_path($GLOBALS['storagedir'] . '/../../index.php'))->toBeFalse()
        ->and(is_valid_rs_path($GLOBALS['storagedir'] . '/../../no-fixture.php'))->toBeFalse()
        ->and(is_valid_rs_path(dirname($photo) . "/some'bad_file.jpg"))->toBeFalse();

    expect(symlink($directory, $link))->toBeTrue();
    expect(is_valid_rs_path($link . '/unrelated.txt'))->toBeTrue()
        ->and(is_valid_rs_path($link . '/future.jpg', [$link]))->toBeTrue();
});

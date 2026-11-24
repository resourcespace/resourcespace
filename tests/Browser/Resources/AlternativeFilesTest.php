<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\AlternativeFilesPage;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

$alternative_fixture = new class () {
    public int|false|null $resource = null;
    public bool $restoreLightbox = false;
    public ?array $previousUser = null;
    public array $runtimeSettings = [];
    public string $originalPath;
    public string|false $originalHash;
    public string $originalPreviewPath;
    public string|false $originalPreviewHash;
};

include_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

beforeEach(function () use ($alternative_fixture): void {
    $alternative_fixture->resource = null;
    $alternative_fixture->restoreLightbox = false;
    $alternative_fixture->previousUser = $GLOBALS['userdata'] ?? null;
    $alternative_fixture->runtimeSettings = [];

    // These fixtures require completed previews, rather than an external job worker.
    $settings = [
        '_GET' => [],
        '_POST' => [],
        '_REQUEST' => [],
        '_FILES' => [],
        'offline_job_queue' => false,
        'upload_then_process' => false,
        'enable_thumbnail_creation_on_upload' => true,
        'image_alternatives' => [],
        'resource_deletion_state' => null,
        'valid_upload_paths' => array_merge(
            $GLOBALS['valid_upload_paths'],
            [RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files'],
        ),
    ];
    foreach ($settings as $name => $value) {
        $alternative_fixture->runtimeSettings[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
        $GLOBALS[$name] = $value;
    }

    setTestConfiguration(<<<'PHP'
        $alternative_file_previews = true;
        $alternative_file_previews_mouseover = false;
        $offline_job_queue = false;
        $upload_then_process = false;
        $enable_thumbnail_creation_on_upload = true;
        $image_alternatives = [];
        $resource_view_use_pre = true;
        $retina_mode = false;
        $terms_download = false;
        $download_usage = false;
        $save_as = false;
    PHP);

    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $alternative_fixture->resource = create_resource(1, 0);
    expect($alternative_fixture->resource)->toBeInt()->toBeGreaterThan(0);
    expect(upload_file(
        $alternative_fixture->resource,
        no_exif: true,
        file_path: testFixture('Images/image_1.jpg'),
        deletesource: false,
    ))->toBeTrue();

    $alternative_fixture->originalPath = get_resource_path($alternative_fixture->resource, true, '', false, 'jpg');
    $alternative_fixture->originalHash = hash_file('sha256', $alternative_fixture->originalPath);
    $alternative_fixture->originalPreviewPath = get_resource_path($alternative_fixture->resource, true, 'pre', false, 'jpg');
    expect($alternative_fixture->originalPreviewPath)->toBeFile();
    $alternative_fixture->originalPreviewHash = hash_file('sha256', $alternative_fixture->originalPreviewPath);
    expect($alternative_fixture->originalHash)->toBe(hash_file('sha256', testFixture('Images/image_1.jpg')));
});

afterEach(function () use ($alternative_fixture): void {
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $deleted = true;
    if (is_int($alternative_fixture->resource ?? null)) {
        $deleted = delete_resource($alternative_fixture->resource);
    }
    $restored_lightbox = true;
    if ($alternative_fixture->restoreLightbox ?? false) {
        $restored_lightbox = activate_plugin('lightbox_preview');
    }
    setTestConfiguration('');
    foreach ($alternative_fixture->runtimeSettings ?? [] as $name => [$existed, $value]) {
        if ($existed) {
            $GLOBALS[$name] = $value;
        } else {
            unset($GLOBALS[$name]);
        }
    }
    if (isset($alternative_fixture->previousUser)) {
        setup_user($alternative_fixture->previousUser);
    }
    expect($deleted)
        ->toBeTrue('Unable to remove the alternative-file test resource.')
        ->and($restored_lightbox)
        ->toBeTrue('Unable to restore the lightbox plugin.');
});

test('uploading an alternative file creates its previews without changing the original', function () use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($alternative_fixture->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($alternative_fixture->resource, $fixture);

    foreach (['thm', 'col', 'pre'] as $size) {
        $path = get_resource_path($alternative_fixture->resource, true, $size, false, 'jpg', alternative: $alternative);
        expect($path)->toBeFile();
        expect(getimagesize($path))->not->toBeFalse();
    }
    $thumbnail = get_resource_path($alternative_fixture->resource, true, 'col', false, 'jpg', alternative: $alternative);
    $page->assertImageMatchesFile("#alt_file_preview_{$alternative} img.AltThumb", $thumbnail);
    expect(hash_file('sha256', $alternative_fixture->originalPath))
        ->toBe($alternative_fixture->originalHash)
        ->and(hash_file('sha256', $alternative_fixture->originalPreviewPath))
        ->toBe($alternative_fixture->originalPreviewHash);
});

test('PDF and MP3 alternative files have a View in browser option for the correct file', function (
    string $fixture,
    string $content_type,
) use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $page->uploadFiles($alternative_fixture->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($alternative_fixture->resource, $fixture);
    $selector = "#alt_file_preview_{$alternative} + tr .DownloadButton a";
    $page
        ->assertVisible($selector)
        ->assertSeeIn($selector, 'View in browser')
        ->assertAttributeContains($selector, 'href', "alternative={$alternative}")
        ->assertAttributeContains($selector, 'href', 'noattach=true');

    $js_selector = encode_js_value($selector);
    $response = $page->script(<<<JS
        async () => {
            const response = await fetch(document.querySelector({$js_selector}).href);
            const digest = await crypto.subtle.digest('SHA-256', await response.arrayBuffer());
            return {
                status: response.status,
                type: response.headers.get('content-type'),
                disposition: response.headers.get('content-disposition'),
                hash: Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('')
            };
        }
    JS);
    expect($response['status'])
        ->toBe(200)
        ->and($response['type'])
        ->toContain($content_type)
        ->and(strtolower($response['disposition'] ?? ''))
        ->toContain('inline')
        ->and($response['hash'])
        ->toBe(hash_file('sha256', testFixture($fixture)));
})->with([
    'PDF' => ['Documents/basic.pdf', 'application/pdf'],
    'MP3' => ['Audio/simple_short.mp3', 'audio/mpeg'],
]);

test('the alternative download button returns the alternative bytes rather than the original', function () use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($alternative_fixture->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($alternative_fixture->resource, $fixture);
    $page->click("#alt_file_preview_{$alternative} .DownloadButton a:text-is(\"Download\")")
        ->assertAttributeContains('#dlIFrm', 'src', '/pages/download_progress.php');

    // Pest's browser bridge has no download-event API. Follow the real button's
    // iframe URL and its progress-page redirect, retaining the browser's session.
    $response = $page->script(<<<'JS'
        async () => {
            const progress = await fetch(document.querySelector('#dlIFrm').src);
            if (!progress.ok) throw new Error('Download progress request failed: ' + progress.status);
            const html = await progress.text();
            const redirect = html.match(/document\.location='([^']+)'/);
            if (!redirect) throw new Error('Download progress page did not provide a download URL');
            const response = await fetch(new URL(redirect[1], progress.url));
            const digest = await crypto.subtle.digest('SHA-256', await response.arrayBuffer());
            return {
                status: response.status,
                disposition: response.headers.get('content-disposition'),
                hash: Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('')
            };
        }
    JS);
    expect($response['status'])
        ->toBe(200)
        ->and(strtolower($response['disposition'] ?? ''))
        ->toContain('attachment')
        ->and($response['hash'])
        ->toBe(hash_file('sha256', testFixture($fixture)))
        ->not->toBe($alternative_fixture->originalHash);
});

test('Use as preview image replaces the resource preview with the selected alternative', function () use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($alternative_fixture->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($alternative_fixture->resource, $fixture);
    $preview = get_resource_path($alternative_fixture->resource, true, 'pre', false, 'jpg');
    $previous_hash = hash_file('sha256', $preview);
    $page
        ->manage($alternative_fixture->resource)
        ->click("#altlistrow{$alternative} a:text-is(\"Use as preview image\")")
        ->waitForAjax()
        // CentralSpacePost retains the form action in history after an HTTP redirect.
        ->assertPresent('#ResourceDownloadOptions')
        ->assertVisible('#previewimage');

    expect(hash_file('sha256', $preview))->not->toBe($previous_hash);

    /**
     * Sample JPEG colours for comparison after preview resizing and re-encoding.
     *
     * @param string $path JPEG file to sample
     * @return list<int> RGB channels from an 8 by 8 thumbnail
     */
    $image_signature = static function (string $path): array {
        $image = imagecreatefromjpeg($path);
        expect($image)->not->toBeFalse();
        $sample = imagescale($image, 8, 8);
        expect($sample)->not->toBeFalse();
        $signature = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                array_push($signature, ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
            }
        }

        return $signature;
    };

    $expected = $image_signature(testFixture($fixture));
    $actual = $image_signature($preview);
    $difference = array_sum(array_map(static fn (int $a, int $b): int => abs($a - $b), $actual, $expected));
    expect($difference / count($expected))->toBeLessThan(15);
    [$width, $height] = getimagesize($preview);
    expect(abs(($width / $height) - (4 / 3)))->toBeLessThan(0.02);
    $page->assertImageMatchesFile('#previewimage', $preview);
    $page->view($alternative_fixture->resource);
    $page->assertImageMatchesFile('#previewimage', $preview);
    expect(hash_file('sha256', $alternative_fixture->originalPath))->toBe($alternative_fixture->originalHash);
});

test('deleting alternative files removes only the selected files and their previews', function (bool $multiple) use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource = $alternative_fixture->resource;
    $fixtures = [
        'first.jpg' => 'Images/image_1.jpg',
        'second.jpg' => 'Images/simple_with_embedded_metadata.jpg',
        'third.jpg' => 'Images/image_1.jpg',
    ];
    foreach ($fixtures as $filename => $fixture) {
        $source = testFixture($fixture);
        $size = filesize($source);
        expect($size)->toBeInt()->toBeGreaterThan(0);
        $alternative = add_alternative_file($resource, $filename, '', $filename, 'jpg', $size);
        expect($alternative)->toBeInt()->toBeGreaterThan(0);
        $destination = get_resource_path($resource, true, '', true, 'jpg', alternative: $alternative);
        expect(copy($source, $destination))->toBeTrue();
        create_previews($resource, extension: 'jpg', alternative: $alternative);
    }
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $files = get_alternative_files($resource);
    expect($files)->toHaveCount(3);
    $selected = array_slice($files, 0, $multiple ? 2 : 1);
    $survivors = array_slice($files, count($selected));
    $paths = [];
    foreach ($files as $file) {
        foreach (['', 'thm', 'col', 'pre'] as $size) {
            $path = get_resource_path($alternative_fixture->resource, true, $size, false, 'jpg', alternative: (int) $file['ref']);
            expect($path)->toBeFile();
            $paths[$file['ref']][] = $path;
        }
    }

    $page->manage($alternative_fixture->resource);
    // Accept the native confirmation without bypassing either deletion handler.
    $page->script('window.confirm = () => true');
    if ($multiple) {
        foreach ($selected as $file) {
            $page->check("#altlistrow{$file['ref']} input[type=checkbox]");
        }
        $page->click('#deletechecked');
    } else {
        $page->click("#altlistrow{$selected[0]['ref']} a:text-is(\"Delete\")");
    }
    $page->waitForAjax();
    foreach ($selected as $file) {
        $page->assertNotPresent("#altlistrow{$file['ref']}");
        expect(get_alternative_file($alternative_fixture->resource, $file['ref']))->toBeFalse();
        foreach ($paths[$file['ref']] as $path) {
            clearstatcache(true, $path);
            expect(file_exists($path))->toBeFalse();
        }
    }
    $page->refresh()->waitForAjax();
    foreach ($selected as $file) {
        $page->assertNotPresent("#altlistrow{$file['ref']}");
    }
    foreach ($survivors as $file) {
        $page->assertPresent("#altlistrow{$file['ref']}");
        expect(get_alternative_file($alternative_fixture->resource, $file['ref']))->toBeArray();
        foreach ($paths[$file['ref']] as $path) {
            expect($path)->toBeFile();
        }
    }
    expect(hash_file('sha256', $alternative_fixture->originalPath))
        ->toBe($alternative_fixture->originalHash)
        ->and(hash_file('sha256', $alternative_fixture->originalPreviewPath))
        ->toBe($alternative_fixture->originalPreviewHash);
})->with(['single deletion' => false, 'multiple deletion' => true]);

test('the alternative thumbnail opens the selected alternative image on the preview page', function () use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $alternative_fixture->restoreLightbox = in_array('lightbox_preview', array_column(get_active_plugins(), 'name'), true);
    setTestConfiguration('$plugins = array_values(array_diff($plugins, ["lightbox_preview"]));');
    deactivate_plugin('lightbox_preview');
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($alternative_fixture->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($alternative_fixture->resource, $fixture);
    $page
        ->click("#alt_file_preview_{$alternative} .AlternativeFileImage a")
        ->waitForAjax()
        ->assertPathIs('/pages/preview.php')
        ->assertQueryStringHas('alternative', (string) $alternative);

    // Preview.php prefers scr and falls back to pre. Resolve the expected alternative
    // independently, rather than using the preview-selection helper under test.
    $expected = get_resource_path($alternative_fixture->resource, true, 'scr', false, 'jpg', alternative: $alternative);
    if (!is_file($expected)) {
        $expected = get_resource_path($alternative_fixture->resource, true, 'pre', false, 'jpg', alternative: $alternative);
    }
    $page->assertImageMatchesFile('#PreviewImageLarge', $expected);
});

test('alternative preview mouseover preserves the initial image and restores it on mouse leave', function () use ($alternative_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    setTestConfiguration('$alternative_file_previews_mouseover = true;');
    $page->uploadFiles(
        $alternative_fixture->resource,
        testFixture('Images/image_1.jpg'),
        testFixture('Images/simple_with_embedded_metadata.jpg'),
    );
    $files = get_alternative_files($alternative_fixture->resource);
    expect($files)->toHaveCount(2);
    $original_preview = get_resource_path($alternative_fixture->resource, true, 'pre', false, 'jpg');

    $page->view($alternative_fixture->resource);
    // Both a fresh View load and a reload must begin with the resource's own preview.
    for ($load = 0; $load < 2; $load++) {
        if ($load > 0) {
            $page->refresh()->waitForAjax();
        }
        $original_src = $page->script('document.querySelector("#previewimage").getAttribute("src")');
        $page->assertImageMatchesFile('#previewimage', $original_preview);
        foreach ($files as $file) {
            $alternative = (int) $file['ref'];
            $expected = get_resource_path($alternative_fixture->resource, true, 'pre', false, 'jpg', alternative: $alternative);
            $page->hover("#alt_file_preview_{$alternative}");
            $js_original_src = encode_js_value($original_src);
            $page->assertScript(
                "() => document.querySelector('#previewimage').getAttribute('src') !== {$js_original_src}",
            );
            $page->assertImageMatchesFile('#previewimage', $expected);
            $page->hover('#ssearchbox')
                ->assertAttribute('#previewimage', 'src', $original_src);
            $page->assertImageMatchesFile('#previewimage', $original_preview);
        }
    }
});

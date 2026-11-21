<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\Browser\AlternativeFilesPage;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

include_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

beforeEach(function (): void {
    $this->resource = null;
    $this->restoreLightbox = false;
    $this->previousUser = $GLOBALS['userdata'] ?? null;
    $this->runtimeSettings = [];

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
        $this->runtimeSettings[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
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
    $this->resource = create_resource(1, 0);
    expect($this->resource)->toBeInt()->toBeGreaterThan(0);
    expect(upload_file(
        $this->resource,
        no_exif: true,
        file_path: testFixture('Images/image_1.jpg'),
        deletesource: false,
    ))->toBeTrue();

    $this->originalPath = get_resource_path($this->resource, true, '', false, 'jpg');
    $this->originalHash = hash_file('sha256', $this->originalPath);
    $this->originalPreviewPath = get_resource_path($this->resource, true, 'pre', false, 'jpg');
    expect($this->originalPreviewPath)->toBeFile();
    $this->originalPreviewHash = hash_file('sha256', $this->originalPreviewPath);
    expect($this->originalHash)->toBe(hash_file('sha256', testFixture('Images/image_1.jpg')));
});

afterEach(function (): void {
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $deleted = true;
    if (is_int($this->resource ?? null)) {
        $deleted = delete_resource($this->resource);
    }
    $restored_lightbox = true;
    if ($this->restoreLightbox ?? false) {
        $restored_lightbox = activate_plugin('lightbox_preview');
    }
    setTestConfiguration('');
    foreach ($this->runtimeSettings ?? [] as $name => [$existed, $value]) {
        if ($existed) {
            $GLOBALS[$name] = $value;
        } else {
            unset($GLOBALS[$name]);
        }
    }
    if (isset($this->previousUser)) {
        setup_user($this->previousUser);
    }
    expect($deleted)
        ->toBeTrue('Unable to remove the alternative-file test resource.')
        ->and($restored_lightbox)
        ->toBeTrue('Unable to restore the lightbox plugin.');
});

test('uploading an alternative file creates its previews without changing the original', function (): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($this->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($this->resource, $fixture);

    foreach (['thm', 'col', 'pre'] as $size) {
        $path = get_resource_path($this->resource, true, $size, false, 'jpg', alternative: $alternative);
        expect($path)->toBeFile();
        expect(getimagesize($path))->not->toBeFalse();
    }
    $thumbnail = get_resource_path($this->resource, true, 'col', false, 'jpg', alternative: $alternative);
    $page->assertImageMatchesFile("#alt_file_preview_{$alternative} img.AltThumb", $thumbnail);
    expect(hash_file('sha256', $this->originalPath))
        ->toBe($this->originalHash)
        ->and(hash_file('sha256', $this->originalPreviewPath))
        ->toBe($this->originalPreviewHash);
});

test('PDF and MP3 alternative files have a View in browser option for the correct file', function (
    string $fixture,
    string $content_type,
): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $page->uploadFiles($this->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($this->resource, $fixture);
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

test('the alternative download button returns the alternative bytes rather than the original', function (): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($this->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($this->resource, $fixture);
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
        ->not->toBe($this->originalHash);
});

test('Use as preview image replaces the resource preview with the selected alternative', function (): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($this->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($this->resource, $fixture);
    $preview = get_resource_path($this->resource, true, 'pre', false, 'jpg');
    $previous_hash = hash_file('sha256', $preview);
    $page
        ->manage($this->resource)
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
    $page->view($this->resource);
    $page->assertImageMatchesFile('#previewimage', $preview);
    expect(hash_file('sha256', $this->originalPath))->toBe($this->originalHash);
});

test('deleting alternative files removes only the selected files and their previews', function (bool $multiple): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $page->uploadFiles(
        $this->resource,
        testFixture('Images/image_1.jpg'),
        testFixture('Images/simple_with_embedded_metadata.jpg'),
    );
    // A separate upload permits another copy of the same fixture in Uppy.
    $page->uploadFiles($this->resource, testFixture('Images/image_1.jpg'));
    $files = get_alternative_files($this->resource);
    expect($files)->toHaveCount(3);
    $selected = array_slice($files, 0, $multiple ? 2 : 1);
    $survivors = array_slice($files, count($selected));
    $paths = [];
    foreach ($files as $file) {
        foreach (['', 'thm', 'col', 'pre'] as $size) {
            $path = get_resource_path($this->resource, true, $size, false, 'jpg', alternative: (int) $file['ref']);
            expect($path)->toBeFile();
            $paths[$file['ref']][] = $path;
        }
    }

    $page->manage($this->resource);
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
        expect(get_alternative_file($this->resource, $file['ref']))->toBeFalse();
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
        expect(get_alternative_file($this->resource, $file['ref']))->toBeArray();
        foreach ($paths[$file['ref']] as $path) {
            expect($path)->toBeFile();
        }
    }
    expect(hash_file('sha256', $this->originalPath))
        ->toBe($this->originalHash)
        ->and(hash_file('sha256', $this->originalPreviewPath))
        ->toBe($this->originalPreviewHash);
})->with(['single deletion' => false, 'multiple deletion' => true]);

test('the alternative thumbnail opens the selected alternative image on the preview page', function (): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    $this->restoreLightbox = in_array('lightbox_preview', array_column(get_active_plugins(), 'name'), true);
    setTestConfiguration('$plugins = array_values(array_diff($plugins, ["lightbox_preview"]));');
    deactivate_plugin('lightbox_preview');
    $fixture = 'Images/simple_with_embedded_metadata.jpg';
    $page->uploadFiles($this->resource, testFixture($fixture));
    $alternative = $page->expectUploadedFileId($this->resource, $fixture);
    $page
        ->click("#alt_file_preview_{$alternative} .AlternativeFileImage a")
        ->waitForAjax()
        ->assertPathIs('/pages/preview.php')
        ->assertQueryStringHas('alternative', (string) $alternative);

    // Preview.php prefers scr and falls back to pre. Resolve the expected alternative
    // independently, rather than using the preview-selection helper under test.
    $expected = get_resource_path($this->resource, true, 'scr', false, 'jpg', alternative: $alternative);
    if (!is_file($expected)) {
        $expected = get_resource_path($this->resource, true, 'pre', false, 'jpg', alternative: $alternative);
    }
    $page->assertImageMatchesFile('#PreviewImageLarge', $expected);
});

test('alternative preview mouseover preserves the initial image and restores it on mouse leave', function (): void {
    $page = new AlternativeFilesPage($this->loginAsAdmin());
    setTestConfiguration('$alternative_file_previews_mouseover = true;');
    $page->uploadFiles(
        $this->resource,
        testFixture('Images/image_1.jpg'),
        testFixture('Images/simple_with_embedded_metadata.jpg'),
    );
    $files = get_alternative_files($this->resource);
    expect($files)->toHaveCount(2);
    $original_preview = get_resource_path($this->resource, true, 'pre', false, 'jpg');

    $page->view($this->resource);
    // Both a fresh View load and a reload must begin with the resource's own preview.
    for ($load = 0; $load < 2; $load++) {
        if ($load > 0) {
            $page->refresh()->waitForAjax();
        }
        $original_src = $page->script('document.querySelector("#previewimage").getAttribute("src")');
        $page->assertImageMatchesFile('#previewimage', $original_preview);
        foreach ($files as $file) {
            $alternative = (int) $file['ref'];
            $expected = get_resource_path($this->resource, true, 'pre', false, 'jpg', alternative: $alternative);
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

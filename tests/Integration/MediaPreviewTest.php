<?php

declare(strict_types=1);

use Montala\ResourceSpace\CommandPlaceholderArg;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;
use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
require_once RESOURCESPACE_BASE_PATH . '/include/video_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'valid_upload_paths', 'enable_thumbnail_creation_on_upload', 'offline_job_queue', 'upload_then_process',
        'ffmpeg_preview', 'ffmpeg_preview_seconds', 'ffmpeg_preview_extension', 'image_alternatives',
        'ffmpeg_preview_options', 'ffmpeg_snapshot_frames', 'ffmpeg_alternatives',
        'preview_tiles', 'preview_tiles_create_auto', 'preview_tile_size', 'preview_tile_scale_factors',
        'previews_allow_enlarge', 'preview_generate_max_file_size', 'use_error_exception',
    ]);
    $GLOBALS['use_error_exception'] = true;
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
    $GLOBALS['enable_thumbnail_creation_on_upload'] = true;
    $GLOBALS['offline_job_queue'] = false;
    $GLOBALS['upload_then_process'] = false;
    $GLOBALS['image_alternatives'] = [];
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('creates a PDF preview without altering the original document', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    $ghostscript = get_utility_path('ghostscript');
    if (!$ghostscript || !get_utility_path('im-convert')) {
        $this->markTestSkipped('PDF previews require Ghostscript and ImageMagick.');
    }
    $fixture = testFixture('Documents/basic.pdf');
    $probe = run_command(
        "{$ghostscript} -q -dNOPAUSE -dBATCH -sDEVICE=nullpage %%PDF%%",
        true,
        ['%%PDF%%' => new CommandPlaceholderArg(
            $fixture,
            static fn (string $path): bool => is_valid_upload_path($path, $GLOBALS['valid_upload_paths'])
        )]
    );
    if (str_contains((string) $probe, 'Permission denied')) {
        $this->markTestSkipped('Ghostscript cannot read PDFs in this checkout.');
    }
    $resource = $fixtures->resource(2, 0);
    expect(upload_file($resource, no_exif: true, file_path: $fixture, deletesource: false))->toBeTrue();

    $original = get_resource_path($resource, true, '', false, 'pdf');
    $preview = get_resource_path($resource, true, 'pre', false, 'jpg');
    expect(hash_file('sha256', $original))->toBe(hash_file('sha256', $fixture))
        ->and($preview)->toBeFile();
    $size = getimagesize($preview);
    expect($size[2] ?? null)->toBe(IMAGETYPE_JPEG)
        ->and($size[0] ?? 0)->toBeGreaterThan(0)
        ->and($size[1] ?? 0)->toBeGreaterThan(0);
});

it('recreates a shorter playable video preview without modifying the original', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('ffmpeg') || !get_utility_path('ffprobe')) {
        $this->markTestSkipped('Video previews require FFmpeg and FFprobe.');
    }
    $GLOBALS['ffmpeg_preview'] = true;
    $GLOBALS['ffmpeg_preview_extension'] = 'mp4';
    $GLOBALS['ffmpeg_preview_options'] = '-c:v libx264 -pix_fmt yuv420p -an -movflags +faststart';
    $GLOBALS['ffmpeg_snapshot_frames'] = 1;
    $GLOBALS['ffmpeg_preview_seconds'] = 4;
    $resource = $fixtures->resource(3, 0);
    $fixture = testFixture('Videos/short_5seconds.mp4');
    expect(upload_file($resource, no_exif: true, file_path: $fixture, deletesource: false))->toBeTrue();
    $original = get_resource_path($resource, true, '', false, 'mp4');
    $preview = get_resource_path($resource, true, 'pre', false, 'mp4');
    expect($preview)->toBeFile();
    $duration = (float) (get_video_info($preview)['streams'][0]['duration'] ?? 0);
    expect($duration)->toBeGreaterThan(3.5)->toBeLessThan(4.5);

    $GLOBALS['ffmpeg_preview_seconds'] = 2;
    $GLOBALS['ffmpeg_alternatives'] = [[
        'name' => 'Lower resolution', 'filename' => 'lower', 'extension' => 'mp4', 'lines_min' => 240,
        'params' => '-vf scale=120:-2 -c:v libx264 -pix_fmt yuv420p -an',
    ]];
    create_previews($resource, extension: 'mp4');
    expect($preview)->toBeFile();
    $duration = (float) (get_video_info($preview)['streams'][0]['duration'] ?? 0);
    expect($duration)->toBeGreaterThan(1.5)->toBeLessThan(2.5)
        ->and(hash_file('sha256', $original))->toBe(hash_file('sha256', $fixture));

    $alternatives = get_alternative_files($resource);
    expect($alternatives)->toHaveCount(1);
    $alternative = $alternatives[0];
    expect($alternative)->toMatchArray([
        'name' => 'Lower resolution', 'file_name' => 'lower.mp4', 'file_extension' => 'mp4',
    ]);
    $rendered = get_resource_path($resource, true, '', false, 'mp4', alternative: $alternative['ref']);
    $stream = get_video_info($rendered)['streams'][0];
    expect([(int) $stream['width'], (int) $stream['height']])->toBe([120, 120]);

    $user = $fixtures->user('video_sources_' . generateRandomTestID(10), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'hide_real_filepath' => true, 'ffmpeg_preview_extension' => 'mp4',
        'video_preview_original' => false, 'session_autologout' => false,
        'videojs_resolution_selection' => [
            ['label' => 'Main', 'name' => ''], ['label' => 'Low', 'name' => 'Lower resolution'],
        ],
        'videojs_resolution_selection_default_res' => 'Main',
    ]);
    foreach (['pre' => false, 'videojs' => true] as $expected_size => $use_original) {
        $server->configure(['video_preview_original' => $use_original]);
        $view = $server->request('/pages/view.php?ref=' . $resource, headers: $server->sessionHeaders($session));
        expect($view['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $sources = (new DOMXPath($document))->query('//video/source');
        expect($sources->length)->toBe(2);
        $params = [];
        foreach ($sources as $source) {
            parse_str(parse_url($source->getAttribute('src'), PHP_URL_QUERY) ?? '', $url_params);
            $params[] = $url_params;
        }
        expect($params[0]['size'])->toBe($expected_size)
            ->and($params[1]['alternative'])->toBe((string) $alternative['ref']);
    }
});

it('writes tiles at each configured scale from the stored original', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Image tiles require ImageMagick.');
    }
    $GLOBALS['preview_tiles'] = true;
    $GLOBALS['preview_tiles_create_auto'] = true;
    $GLOBALS['preview_tile_size'] = 64;
    $GLOBALS['preview_tile_scale_factors'] = [1, 2, 4];
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original = get_resource_path($resource, true, '', true, 'jpg');
    $image = imagecreatetruecolor(272, 272);
    expect($image)->toBeInstanceOf(GdImage::class);
    expect(imagejpeg($image, $original))->toBeTrue();
    create_previews($resource, extension: 'jpg', ingested: true, checksum_required: false, onlysizes: ['tiles']);

    foreach ([1, 2, 4] as $scale) {
        $source_size = 64 * $scale;
        $tile = get_resource_path($resource, true, "tile_{$scale}_0_0_{$source_size}_{$source_size}");
        expect($tile)->toBeFile();
        $size = getimagesize($tile);
        expect($size[0] ?? null)->toBe(64)->and($size[1] ?? null)->toBe(64);
    }
});

it('generates an image preview with the same aspect ratio and colour as the original', function (
    string $extension,
) use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    $convert = get_utility_path('im-convert');
    if (!$convert) {
        $this->markTestSkipped('Image preview generation requires ImageMagick.');
    }
    $GLOBALS['preview_generate_max_file_size'] = 20;
    $GLOBALS['preview_tiles'] = false;
    $GLOBALS['previews_allow_enlarge'] = true;
    $directory = get_temp_dir(false, 'preview-fixture-' . generateRandomTestID(12));
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $source = $directory . '/source.' . $extension;
    $width = 640;
    $height = 320;
    $image = imagecreatetruecolor($width, $height);
    $colour = imagecolorallocate($image, 40, 120, 200);
    imagefill($image, 0, 0, $colour);

    if ($extension === 'tiff') {
        $png = $directory . '/conversion-source.png';
        expect(imagepng($image, $png))->toBeTrue();
        run_command("{$convert} %%SOURCE%% %%DESTINATION%%", false, [
            '%%SOURCE%%' => new CommandPlaceholderArg($png, 'is_valid_rs_path'),
            '%%DESTINATION%%' => new CommandPlaceholderArg($source, 'is_valid_rs_path'),
        ]);
    } elseif ($extension === 'svg') {
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}">
                <rect width="{$width}" height="{$height}" fill="#2878c8"/>
            </svg>
            SVG;
        expect(file_put_contents($source, $svg))->toBe(strlen($svg));
    } else {
        $save = match ($extension) {
            'jpg' => imagejpeg(...),
            'png' => imagepng(...),
            'gif' => imagegif(...),
        };
        expect($save($image, $source))->toBeTrue();
    }

    expect($source)->toBeFile();
    $ref = $fixtures->resource(1, 0);
    expect(upload_file($ref, no_exif: true, file_path: $source, deletesource: false))->toBeTrue();
    $original = get_resource_path($ref, true, '', false, $extension);
    expect($original)->toBeFile()->and(hash_file('sha256', $original))->toBe(hash_file('sha256', $source));
    $preview = get_resource_path($ref, true, 'pre', false, 'jpg');
    expect($preview)->toBeFile();
    $dimensions = getimagesize($preview);
    expect($dimensions[2])->toBe(IMAGETYPE_JPEG)
        ->and($dimensions[0] / $dimensions[1])->toEqualWithDelta(2.0, 0.02);
    $jpeg = imagecreatefromjpeg($preview);
    $pixel = imagecolorat($jpeg, (int) ($dimensions[0] / 2), (int) ($dimensions[1] / 2));
    foreach ([16 => 40, 8 => 120, 0 => 200] as $shift => $expected) {
        expect(($pixel >> $shift) & 0xFF)->toEqualWithDelta($expected, 8);
    }
})->with(['JPEG' => 'jpg', 'PNG' => 'png', 'GIF' => 'gif', 'TIFF' => 'tiff', 'SVG' => 'svg']);

it('uses original MP3 audio and creates a playable MP3 for other supported formats', function (
    string $extension,
    ?string $codec,
) use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    $ffmpeg = get_utility_path('ffmpeg');
    if (!$ffmpeg || !get_utility_path('ffprobe')) {
        $this->markTestSkipped('Audio preview generation requires FFmpeg and FFprobe.');
    }
    $directory = get_temp_dir(false, 'audio-fixture-' . generateRandomTestID(12));
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $input = testFixture('Audio/simple_short.mp3');
    $source = $directory . '/audio.' . $extension;
    if ($codec === null) {
        expect(copy($input, $source))->toBeTrue();
    } else {
        $staged = $directory . '/input.mp3';
        expect(copy($input, $staged))->toBeTrue();
        run_command("{$ffmpeg} -v error -y -i %%INPUT%% -t 1 -vn -ac 1 -c:a {$codec} %%OUTPUT%%", false, [
            '%%INPUT%%' => new CommandPlaceholderArg($staged, 'is_valid_rs_path'),
            '%%OUTPUT%%' => new CommandPlaceholderArg($source, 'is_valid_rs_path'),
        ]);
    }
    expect($source)->toBeFile();
    $resource = $fixtures->resource(4, 0);
    expect(upload_file($resource, no_exif: true, file_path: $source, deletesource: false))->toBeTrue();

    $original = get_resource_path($resource, true, '', false, $extension);
    $playback = get_resource_path($resource, true, '', false, 'mp3');
    expect($playback)->toBeFile()
        ->and(hash_file('sha256', $original))->toBe(hash_file('sha256', $source))
        ->and($playback === $original)->toBe($extension === 'mp3');
    $info = get_video_info($playback);
    expect(array_column($info['streams'], 'codec_name'))->toContain('mp3');
    $audio = array_values(array_filter($info['streams'], static fn (array $stream): bool => $stream['codec_type'] === 'audio'));
    expect((float) $audio[0]['duration'])->toBeGreaterThan(0.5);
})->with([
    'MP3' => ['mp3', null],
    'AAC' => ['aac', 'aac'],
    'WMA' => ['wma', 'wmav2'],
    'OGG' => ['ogg', 'libvorbis'],
]);

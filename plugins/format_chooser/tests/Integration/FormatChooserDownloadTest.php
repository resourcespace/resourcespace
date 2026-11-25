<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('converts image downloads to configured formats and preserves original JPEG bytes', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Format conversion requires ImageMagick.');
    }
    require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
    $user = $fixtures->user('formatreader' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $ref = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original_path = get_resource_path($ref, true, '', true, 'jpg');
    $image = imagecreatetruecolor(1600, 800);
    expect($image)->toBeInstanceOf(GdImage::class);
    expect(imagejpeg($image, $original_path))->toBeTrue();
    expect(create_previews($ref, extension: 'jpg', ingested: true, checksum_required: false))->toBeTrue();
    $original_bytes = file_get_contents($original_path);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'session_autologout' => false, 'terms_download' => false,
        'exiftool_write' => false, 'imagemagick_preserve_profiles' => true,
    ]);
    $headers = $server->sessionHeaders($session);
    $original_url = '/pages/download.php?' . http_build_query(['ref' => $ref, 'ext' => 'jpg']);
    $baseline = $server->request($original_url, headers: $headers);
    expect($baseline['status'])->toBe(200);
    expect(hash('sha256', $baseline['body']))->toBe(hash('sha256', $original_bytes));

    $plugin = 'format_chooser';
    $fixtures->plugin($plugin);
    $config = [
        'format_chooser_input_formats' => ['JPG', 'PNG'],
        'format_chooser_output_formats' => ['JPG', 'PNG', 'TIF', 'GIF'],
    ];
    set_plugin_config($plugin, $config);
    $with_plugin = $server->request($original_url, headers: $headers);
    expect($with_plugin['status'])->toBe(200);
    expect(hash('sha256', $with_plugin['body']))->toBe(hash('sha256', $original_bytes));

    $view_url = '/pages/view.php?ref=' . $ref;
    $view = $server->request($view_url, headers: $headers);
    expect($view['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $formats = '//select[option[@value="JPG"] and option[@value="GIF"]]';
    expect((new DOMXPath($document))->evaluate('count(' . $formats . ')'))->toBeGreaterThan(0);

    $screen = getimagesize(get_resource_path($ref, true, 'scr', false, 'jpg'));
    expect($screen)->toBeArray();
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'tif' => 'image/tiff', 'gif' => 'image/gif'] as $ext => $mime) {
        $route = '/plugins/format_chooser/pages/convert.php?' . http_build_query([
            'ref' => $ref, 'ext' => $ext, 'size' => 'scr',
        ]);
        $conversion = $server->request($route, headers: $headers);
        expect($conversion['status'])->toBe(302);
        $download = parse_url($conversion['headers']['location']);
        expect($download['path'])->toBe('/pages/download.php');
        $file = $server->request($download['path'] . '?' . $download['query'], headers: $headers);
        expect($file['status'])->toBe(200)->and($file['headers']['content-disposition'] ?? '')->toContain('.' . $ext);
        $image_info = getimagesizefromstring($file['body']);
        expect($image_info)->toBeArray();
        expect(array_slice($image_info, 0, 2))->toBe(array_slice($screen, 0, 2));
        expect($image_info['mime'])->toBe($mime);
    }

    $config['format_chooser_input_formats'] = ['PNG'];
    set_plugin_config($plugin, $config);
    $unsupported = $server->request($view_url, headers: $headers);
    expect($unsupported['status'])->toBe(200);
    $document->loadHTML($unsupported['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->evaluate('count(' . $formats . ')'))->toBe(0.0);
    expect(hash_file('sha256', $original_path))->toBe(hash('sha256', $original_bytes));
});

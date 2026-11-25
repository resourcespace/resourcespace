<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('moves slideshow images in both directions and honours the Home and Login visibility flags', function () use (
    &$fixtures,
): void {
    require_once RESOURCESPACE_BASE_PATH . '/include/slideshow_functions.php';
    $admin = $fixtures->user('slideshowadmin' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $admin,
    ]);
    $resources = [$fixtures->resource(1, 0), $fixtures->resource(1, 0)];
    $slides = [];
    $bytes = [];
    $paths = [];
    foreach ($resources as $index => $resource) {
        $slide = set_slideshow(null, $resource, 1, 0);
        expect($slide)->toBeInt()->toBeGreaterThan(0);
        $fixtures->onCleanup(static fn () => delete_slideshow($slide));
        $path = RESOURCESPACE_BASE_PATH . '/' . $GLOBALS['homeanim_folder'] . '/' . $slide . '.jpg';
        $image = imagecreatetruecolor(64 + $index * 8, 40);
        expect($image)->toBeInstanceOf(GdImage::class);
        expect(imagejpeg($image, $path))->toBeTrue();
        $slides[] = $slide;
        $paths[] = $path;
        $bytes[] = hash_file('sha256', $path);
    }

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false, 'anonymous_login' => null]);
    $headers = $server->sessionHeaders($session);
    $home = static fn (): array => $server->request('/pages/home.php', headers: $headers);
    $login = static fn (): array => $server->request('/login.php', headers: $server->sessionHeaders(''));
    $first_url = '/pages/download.php?slideshow=' . $slides[0];
    $second_url = '/pages/download.php?slideshow=' . $slides[1];
    $path = '/pages/admin/admin_manage_slideshow.php';
    $page = $server->request($path, headers: $headers);
    expect($page['status'])->toBe(200);
    foreach ($slides as $slide) {
        expect($page['body'])->toContain('id="slideshow_' . $slide . '"');
    }
    $post = static function (string $token_name, array $data) use ($server, $path, $headers, $session): array {
        return $server->request($path, [
            'ajax' => 'true',
            $GLOBALS['CSRF_token_identifier'] => generateCSRFToken($session, $token_name),
        ] + $data, $headers);
    };
    foreach ([['moveup', 1], ['movedown', 0]] as [$direction, $index]) {
        $reorder = $post('ReorderSlideshowImage', ['action' => $direction, 'slideshow_id' => $slides[$index]]);
        expect($reorder['status'])->toBe(200);
        $result = json_decode($reorder['body'], true, flags: JSON_THROW_ON_ERROR);
        expect((int) $result['sibling'])->toBe($slides[1 - $index]);
        $data = array_column(get_slideshow_files_data(), 'resource_ref', 'ref');
        expect([(int) $data[$slides[0]], (int) $data[$slides[1]]])->toBe(array_reverse($resources));
        expect(array_map(static fn (string $path): string => hash_file('sha256', $path), $paths))
            ->toBe(array_reverse($bytes));
        $home_page = $home();
        expect($home_page['status'])->toBe(200)->and($home_page['body'])->toContain($first_url, $second_url);
        expect(strpos($home_page['body'], $first_url))->toBeLessThan(strpos($home_page['body'], $second_url));
        $resources = array_reverse($resources);
        $bytes = array_reverse($bytes);
    }

    expect($login()['body'])->not->toContain($first_url, $second_url);
    expect($post('SetSlideshowFlag', [
        'action' => 'set_flag', 'slideshow_id' => $slides[0], 'flag' => 'homepage_show', 'value' => 0,
    ])['status'])->toBe(200);
    expect($post('SetSlideshowFlag', [
        'action' => 'set_flag', 'slideshow_id' => $slides[0], 'flag' => 'login_show', 'value' => 1,
    ])['status'])->toBe(200);
    expect($home()['body'])->not->toContain($first_url);
    expect($home()['body'])->toContain($second_url);
    expect($login()['body'])->toContain($first_url);
    expect($login()['body'])->not->toContain($second_url);
});

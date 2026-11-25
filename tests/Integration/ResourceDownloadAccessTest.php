<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;
use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'restricted_full_download', 'open_access_for_contributor', 'get_resource_data_cache', 'resource_access_cache', 'download_access_cache']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('enforces download size and access policy together with user grants', function () use (&$fixtures): void {
    global $userref, $restricted_full_download, $open_access_for_contributor;

    $resource = $fixtures->resource(1, 0);
    $id = generateRandomTestID(8);
    $group = $fixtures->group('Download access ' . $id, 's,g,f*');
    $reader = $fixtures->user('downloadreader' . $id, $group);
    $admin_data = get_user($userref);
    $reader_data = get_user($reader);

    $fixtures->onCleanup(static function () use ($reader, $resource): void {
        ps_query('DELETE FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $reader]);
    });
    $open_access_for_contributor = false;
    $cases = [
        [RESOURCE_ACCESS_FULL, false, '', true],
        [RESOURCE_ACCESS_RESTRICTED, false, '', false],
        [RESOURCE_ACCESS_RESTRICTED, true, '', true],
        [RESOURCE_ACCESS_RESTRICTED, false, 'thm', true],
        [RESOURCE_ACCESS_CONFIDENTIAL, true, '', false],
        [RESOURCE_ACCESS_CONFIDENTIAL, true, 'thm', false],
    ];
    foreach ($cases as [$access, $allow_original, $size, $expected]) {
        setup_user($admin_data);
        if ((int) get_resource_data($resource, false)['access'] !== $access) {
            expect(put_resource_data($resource, ['access' => $access]))->toBeTrue();
        }
        setup_user($reader_data);
        $GLOBALS['get_resource_data_cache'] = [];
        $GLOBALS['resource_access_cache'] = [];
        $GLOBALS['download_access_cache'] = [];
        $restricted_full_download = $allow_original;
        expect(resource_download_allowed($resource, $size, 1))->toBe(
            $expected,
            "Unexpected download access for state {$access}, size '{$size}', full download enabled " . (int) $allow_original
        );
    }

    // A specific user grant opens a confidential original; expiration removes that access.
    ps_query(
        'INSERT INTO resource_custom_access (resource, user, access, user_expires) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))',
        ['i', $resource, 'i', $userref, 'i', RESOURCE_ACCESS_FULL]
    );
    $GLOBALS['resource_access_cache'] = [];
    expect(resource_download_allowed($resource, '', 1) !== true)->toBeFalse('Valid user grant did not allow download - ');
    ps_query(
        'UPDATE resource_custom_access SET user_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE resource = ? AND user = ?',
        ['i', $resource, 'i', $userref]
    );
    $GLOBALS['resource_access_cache'] = [];
    expect(resource_download_allowed($resource, '', 1) !== false)->toBeFalse('Expired user grant still allowed download - ');
});

it('blocks the configured preview size for a specific resource type', function () use (&$fixtures): void {
    $ref = $fixtures->resource(1, 0);
    $group = $fixtures->group('Preview size ' . generateRandomTestID(8), 's,g,f*,T1_scr');
    $reader = $fixtures->user('previewreader' . generateRandomTestID(8), $group);
    setup_user(get_user($reader));

    expect(resource_download_allowed($ref, 'scr', 1, -1))->toBeFalse()
        ->and(resource_download_allowed($ref, 'thm', 1, -1))->toBeTrue();
});

it('resolves the resource type and size permission independently', function (
    array $permissions,
    int $type,
    string $size,
    bool $denied,
): void {
    $GLOBALS['userpermissions'] = $permissions;
    expect(resource_has_access_denied_by_RT_size($type, $size))->toBe($denied);
})->with([
    'unrestricted' => [[], 1, 'scr', false],
    'type blocked' => [['T1'], 1, 'scr', true],
    'original blocked' => [['T1_'], 1, '', true],
    'preview blocked' => [['T1_scr'], 1, 'scr', true],
    'different preview' => [['T1_scr'], 1, 'thm', false],
    'different type' => [['T1_scr'], 2, 'scr', false],
]);

it('blocks downloads at the group limit and permits them after logged downloads age out', function () use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $group = $fixtures->group('Download quota ' . $id, 's,g,d,q,n,f*');
    expect(save_usergroup($group, ['download_limit' => 3, 'download_log_days' => 10]))->not->toBeFalse();
    $user = $fixtures->user('download_quota_' . $id, $group);
    $downloads = [$fixtures->resource(1, 0), $fixtures->resource(1, 0), $fixtures->resource(2, 0)];
    $candidate = $fixtures->resource(2, 0);
    $fixtures->onCleanup(static function () use ($user): void {
        ps_query('DELETE FROM resource_log WHERE user = ? AND type = ?', ['i', $user, 's', LOG_CODE_DOWNLOADED]);
    });
    setup_user(get_user($user));

    resource_log($downloads[0], LOG_CODE_DOWNLOADED, 0, 'Within quota');
    resource_log($downloads[1], LOG_CODE_DOWNLOADED, 0, 'Within quota');
    expect(get_user_downloads($user, 10))->toBe(2)
        ->and(resource_download_allowed($candidate, '', 1, -1))->toBeTrue();

    resource_log($downloads[2], LOG_CODE_DOWNLOADED, 0, 'Quota reached');
    expect(get_user_downloads($user, 10))->toBe(3)
        ->and(resource_download_allowed($candidate, '', 1, -1))->toBeFalse();

    ps_query(
        'UPDATE resource_log SET date = DATE_SUB(NOW(), INTERVAL 11 DAY) WHERE user = ? AND type = ?',
        ['i', $user, 's', LOG_CODE_DOWNLOADED]
    );
    expect(get_user_downloads($user, 10))->toBe(0)
        ->and(resource_download_allowed($candidate, '', 1, -1))->toBeTrue();
});

it('serves the original bytes and the stored Screen and Preview sizes through the download route', function () use (
    &$fixtures,
): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('ImageMagick is required to create download previews.');
    }
    require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
    $ref = $fixtures->resource(1, 0, file_extension: 'jpg');
    $original = get_resource_path($ref, true, '', true, 'jpg');
    $image = imagecreatetruecolor(1600, 800);
    expect($image)->toBeInstanceOf(GdImage::class);
    expect(imagejpeg($image, $original))->toBeTrue();
    $original_hash = hash_file('sha256', $original);
    create_previews($ref, extension: 'jpg', ingested: true, checksum_required: false, onlysizes: ['pre', 'scr']);

    $user = $fixtures->user('download_browser_' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false, 'terms_download' => false]);
    $headers = $server->sessionHeaders($session);
    $dimensions = [];
    foreach (['', 'scr', 'pre'] as $size) {
        $download = '/pages/download.php?' . http_build_query(['ref' => $ref, 'ext' => 'jpg', 'size' => $size]);
        $response = $server->request($download, headers: $headers);
        expect($response['status'])->toBe(200);
        expect($response['headers']['content-disposition'])->toContain('attachment', '.jpg');
        $actual = getimagesizefromstring($response['body']);
        expect($actual)->toBeArray();
        expect($actual[2])->toBe(IMAGETYPE_JPEG);
        $dimensions[$size] = $actual[0];
        $file = $size === '' ? $original : get_resource_path($ref, true, $size, false, 'jpg');
        expect($response['body'])->toBe(file_get_contents($file));
        if ($size === '') {
            expect([$actual[0], $actual[1]])->toBe([1600, 800])
                ->and(hash('sha256', $response['body']))->toBe($original_hash);
        } else {
            expect(abs($actual[0] / $actual[1] - 2))->toBeLessThan(0.02);
        }
    }

    expect($dimensions['scr'])->toBeLessThan($dimensions[''])
        ->and($dimensions['pre'])->toBeLessThan($dimensions['scr'])
        ->and(hash_file('sha256', $original))->toBe($original_hash);
});

it('recreates a configured custom image size and honours its restricted download setting', function () use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Custom image sizes require ImageMagick.');
    }
    require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
    $suffix = generateRandomTestID(12);
    $admin = $fixtures->user('sizeadmin' . $suffix, 3);
    $group = $fixtures->group('Size readers ' . $suffix, 's,g,f*');
    $reader = $fixtures->user('sizereader' . $suffix, $group);
    $sessions = [$admin => generateSecureKey(32), $reader => generateSecureKey(32)];
    foreach ($sessions as $user => $session) {
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $user]);
    }
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    expect(put_resource_data($resource, ['access' => RESOURCE_ACCESS_RESTRICTED]))->toBeTrue();
    $original = get_resource_path($resource, true, '', true, 'jpg');
    $image = imagecreatetruecolor(800, 400);
    expect($image)->toBeInstanceOf(GdImage::class);
    expect(imagejpeg($image, $original))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'session_autologout' => false, 'terms_download' => false,
        'restricted_full_download' => false, 'open_access_for_contributor' => false,
    ]);
    $admin_headers = $server->sessionHeaders($sessions[$admin]);
    $reader_headers = $server->sessionHeaders($sessions[$reader]);
    $size = 'qzx';
    expect(ps_value('SELECT ref AS value FROM preview_size WHERE id = ?', ['s', $size], 0))->toBe(0);
    $edit = '/pages/admin/admin_size_management_edit.php';
    $created = $server->request($edit, [
        $GLOBALS['CSRF_token_identifier'] => generateCSRFToken($sessions[$admin], 'admin_size_management_edit'),
        'newsizeid' => $size,
    ], $admin_headers);
    expect($created['status'])->toBe(302);
    $size_ref = ps_value('SELECT ref AS value FROM preview_size WHERE id = ?', ['s', $size], 0);
    expect($size_ref)->toBeGreaterThan(0);
    $fixtures->onCleanup(static function () use ($size_ref): void {
        ps_query('DELETE FROM preview_size WHERE ref = ?', ['i', $size_ref]);
        clear_query_cache('schema');
    });
    $edit .= '?ref=' . $size_ref;
    $form = $server->request($edit, headers: $admin_headers);
    expect($form['status'])->toBe(200);
    $fields = LocalTestServer::hiddenFields($form['body'], '//form[@id="mainform"]');
    expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $name = 'Catalogue delivery ' . $suffix;
    $download = '/pages/download.php?' . http_build_query(['ref' => $resource, 'ext' => 'jpg', 'size' => $size]);
    $preview = get_resource_path($resource, true, $size, false, 'jpg');
    $fixtures->onCleanup(static function () use ($preview): void {
        if (is_file($preview)) {
            unlink($preview);
        }
    });
    foreach ([[240, 120, false], [320, 160, true]] as [$width, $height, $restricted]) {
        $options = $fields + ['name' => $name, 'width' => $width, 'height' => $height, 'allowpreview' => '1'];
        if ($restricted) {
            $options['allowrestricted'] = '1';
        }
        expect($server->request($edit, $options, $admin_headers)['status'])->toBe(302);
        clear_query_cache('schema');
        create_previews($resource, extension: 'jpg', ingested: true, checksum_required: false, onlysizes: ['thm', 'pre', $size]);
        expect($preview)->toBeFile();
        expect(array_slice(getimagesize($preview), 0, 2))->toBe([$width, $height]);
        $available = array_column(get_image_sizes($resource), 'name', 'id');
        expect($available[$size] ?? null)->toBe($name);
        $received = $server->request($download, headers: $reader_headers);
        if ($restricted) {
            expect($received['headers']['content-disposition'])->toContain('attachment');
            expect($received['body'])->toBe(file_get_contents($preview));
        } else {
            expect($received['headers'])->not->toHaveKey('content-disposition');
        }
    }
    expect($server->request($edit, $fields + ['deleteme' => '1'], $admin_headers)['status'])->toBe(302);
    clear_query_cache('schema');
    expect(array_column(get_image_sizes($resource), 'id'))->not->toContain($size);
});

it('uses terms and usage in the View download link according to signed overrides', function (
    string $scope,
    bool $terms,
    bool $usage,
    string $expected,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Download route ' . $id, 's,g,f*,v');
    $user = $fixtures->user('download_route_' . $id, $group);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $ref = $fixtures->resource(1, 0, file_extension: 'jpg');
    expect(copy(testFixture('Images/image_1.jpg'), get_resource_path($ref, true, '', true, 'jpg')))->toBeTrue();

    $code = '$terms_download = ' . var_export($terms, true) . ';' . "\n"
        . '$download_usage = ' . var_export($usage, true) . ';';
    $signed = '//SIG' . sign_code($code) . "\n" . $code;
    if ($scope === 'group') {
        expect(save_usergroup($group, ['config_options' => $signed]))->toBeTrue();
    } elseif ($scope === 'resource type') {
        $prior = ps_value('SELECT config_options AS value FROM resource_type WHERE ref = ?', ['i', 1], '');
        $fixtures->onCleanup(static fn () => save_resource_type(1, ['config_options' => $prior]));
        expect(save_resource_type(1, ['config_options' => $signed]))->toBeTrue();
    }

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url,
        'session_autologout' => false,
        'execution_lockout' => false,
        'terms_download' => $scope === 'system' ? $terms : $scope === 'resource type',
        'download_usage' => $scope === 'system' ? $usage : $scope === 'resource type',
    ]);
    $headers = $server->sessionHeaders($session);
    $view = $server->request('/pages/view.php?ref=' . $ref, headers: $headers);
    expect($view['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $links = (new DOMXPath($document))->query("//td[contains(@class,'DownloadButton')]/a[@id='downloadlink']");
    expect($links)->toBeInstanceOf(DOMNodeList::class);
    $link = $links->item(0);
    expect($link)->toBeInstanceOf(DOMElement::class);
    if ($expected === 'download_progress.php') {
        expect($link->getAttribute('onclick'))->toContain('/pages/download_progress.php');
        return;
    }
    $href = parse_url($link->getAttribute('href'));
    expect($href['path'])->toBe('/pages/' . $expected);
    $form = $server->request($href['path'] . '?' . $href['query'], headers: $headers);
    expect($form['status'])->toBe(200);
    if ($terms) {
        expect($form['body'])->toContain('id="iaccept"');
    } else {
        expect($form['body'])->toContain('name="usage"');
    }
})->with([
    'system terms' => ['system', true, false, 'terms.php'],
    'group enables terms' => ['group', true, false, 'terms.php'],
    'resource type keeps both enabled' => ['resource type', true, true, 'terms.php'],
    'resource type keeps only terms' => ['resource type', true, false, 'terms.php'],
    'resource type keeps only usage' => ['resource type', false, true, 'download_usage.php'],
    'resource type disables both' => ['resource type', false, false, 'download_progress.php'],
]);

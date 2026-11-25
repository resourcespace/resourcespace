<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once RESOURCESPACE_BASE_PATH . '/include/api_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/api_bindings.php';

$fixtures = null;
$server = null;
beforeAll(function () use (&$server): void {
    $server = new LocalTestServer();
});
afterAll(function () use (&$server): void {
    $server?->close();
});
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('runs API test-tool requests and renders optional arguments, results and the matching documentation link', function () use (
    &$fixtures,
    &$server,
): void {
    $suffix = generateRandomTestID(12);
    $admin = $fixtures->user('apitool' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $admin]);
    $photo = $fixtures->resource(1, 0);
    $document = $fixtures->resource(2, 0);
    $server->configure(['baseurl' => $server->url, 'enable_remote_apis' => true, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $path = '/pages/api_test.php';
    $form = $server->request($path . '?api_function=do_search', headers: $headers);
    expect($form['status'])->toBe(200);
    $hidden = LocalTestServer::hiddenFields($form['body'], '//form[@id="api-form"]');
    expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $dom = new DOMDocument();
    $dom->loadHTML($form['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    expect($xpath->evaluate('count(//input[@name="restypes" and @disabled])'))->toBe(1.0)
        ->and($xpath->evaluate('count(//input[@name="send_restypes" and @type="checkbox"])'))->toBe(1.0)
        ->and($xpath->evaluate('count(//a[@href="https://www.resourcespace.com/knowledge-base/api/do_search"])'))
        ->toBe(1.0);

    $call = static function (string $function, array $arguments) use ($server, $path, $headers, $hidden, $dom): array {
        $request = array_replace($hidden, ['api_function' => $function, 'submitting' => 'true'], $arguments);
        $response = $server->request($path, $request, $headers);
        expect($response['status'])->toBe(200);
        $dom->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $output = $xpath->evaluate('string(//pre[@class="codeoutput"])');
        expect($output)->toContain('Query: function=' . $function, "Response:\n");
        expect($xpath->evaluate('count(//pre[@class="codeexample"])'))->toBeGreaterThan(0);

        [$query, $json] = explode("Response:\n", $output, 2);
        return [$query, json_decode(trim($json), true, flags: JSON_THROW_ON_ERROR)];
    };
    $search = ['search' => '!list' . $photo . ':' . $document];
    [, $all_results] = $call('do_search', $search);
    expect(array_column($all_results, 'ref'))->toEqualCanonicalizing([$photo, $document]);
    [$query, $narrow_results] = $call('do_search', $search + ['send_restypes' => 'yes', 'restypes' => '2']);
    expect($query)->toContain('&restypes=2');
    expect(array_column($narrow_results, 'ref'))->toBe([$document]);

    [, $created] = $call('create_resource', ['resource_type' => '1']);
    expect($created)->toBeInt()->toBeGreaterThan(0);
    $fixtures->trackResource($created);
    $title = 'Created and edited by the API tool ' . $suffix;
    [, $updated] = $call('update_field', [
        'resource' => $created, 'field' => $GLOBALS['view_title_field'], 'value' => $title,
    ]);
    expect($updated)->toBeTrue();
    expect(get_data_by_field($created, (int) $GLOBALS['view_title_field'], true))->toBe($title);
    [, $invalid] = $call('get_resource_data', ['resource' => 'unknown-resource']);
    expect($invalid)->toBeFalse();
});

it('authenticates, dispatches and audits GET and POST calls to the API', function (bool $post) use (&$fixtures, &$server): void {
    $name = 'api_http_' . generateRandomTestID(12);
    $unknown = $name . '_missing';
    $user = $fixtures->user($name, 3);
    ps_query('UPDATE user SET approved = 1, session = ? WHERE ref = ?', ['s', generateSecureKey(32), 'i', $user]);
    unset($GLOBALS['udata_cache'][$user]);
    $fixtures->onCleanup(static function () use ($name, $unknown): void {
        ps_query('DELETE FROM api_log WHERE user IN (?, ?)', ['s', $name, 's', $unknown]);
    });
    $key = get_api_key($name);
    $request = static function (string $query, string $sign, string $username) use ($post, &$server): array {
        $path = '/api/index.php';
        if ($post) {
            return $server->request($path, ['user' => $username, 'sign' => $sign, 'query' => $query . '&sign=' . $sign]);
        }

        return $server->request($path . '?' . $query . '&sign=' . $sign);
    };
    $latest_log = static fn (string $username): mixed => ps_value(
        'SELECT `function` AS value FROM api_log WHERE `user` = ? ORDER BY ref DESC LIMIT 1',
        ['s', $username],
        null
    );

    foreach ([$name, $unknown] as $username) {
        $query = http_build_query(['user' => $username, 'function' => 'do_search', 'search' => '!list0']);
        $response = $request($query, str_repeat('0', 64), $username);
        expect($response['status'])->toBe(401)->and($latest_log($username))->toBe('do_search');
    }
    $create = http_build_query(['user' => $name, 'function' => 'create_resource', 'resource_type' => 1, 'archive' => 0]);
    $response = $request($create, hash('sha256', $key . $create), $name);
    expect($response['status'])->toBe(200)->and($latest_log($name))->toBe('create_resource');
    $ref = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
    expect($ref)->toBeInt()->toBeGreaterThan(0);
    $fixtures->trackResource($ref);

    $search = http_build_query(['user' => $name, 'function' => 'do_search', 'search' => "!list{$ref}"]);
    $response = $request($search, hash('sha256', $key . $search), $name);
    expect($response['status'])->toBe(200)->and($latest_log($name))->toBe('do_search');
    expect(array_column(json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR), 'ref'))->toBe([$ref]);

    $prefix = http_build_query(['user' => $name, 'function' => 'get_resource_data']);
    $argument = $post ? 'param1' : 'resource';
    $query = $prefix . '&' . $argument . '=' . $ref;
    $signature = hash('sha256', $key . $query);
    $with_transport = $post
        ? 'ajax=true&' . $query . '&pretty=true'
        : $prefix . '&ajax=true&' . $argument . '=' . $ref;
    $response = $request($with_transport, $signature, $name);
    expect($response['status'])->toBe(200);
    expect((int) (json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR)['ref'] ?? 0))->toBe($ref);
    $response = $request($with_transport, hash('sha256', $key . $prefix), $name);
    expect($response['status'])->toBe(401);
    foreach (['&' . $argument . '=0', '&funct%69on=get_resource_data'] as $invalid) {
        $ambiguous = $query . $invalid;
        $response = $request($ambiguous, hash('sha256', $key . $ambiguous), $name);
        expect($response['status'])->toBe(400);
    }

    $update = http_build_query([
        'user' => $name, 'function' => 'put_resource_data', 'resource' => $ref,
        'data' => ['access' => RESOURCE_ACCESS_RESTRICTED, 'archive' => 0],
    ]);
    $response = $request($update, hash('sha256', $key . $update), $name);
    expect($response['status'])->toBe(200)
        ->and(json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR))->toBeTrue()
        ->and((int) get_resource_data($ref, false)['access'])->toBe(RESOURCE_ACCESS_RESTRICTED);

    foreach (['userkey' => $key, 'sessionkey' => get_session_api_key($user)] as $mode => $signing_key) {
        $query = $prefix . '&resource=' . $ref;
        expect(check_api_key($name, $query . '&ajax=true', hash('sha256', $signing_key . $query), $mode))->toBeTrue();
        expect(check_api_key($name, $query, hash('sha256', $signing_key . $prefix), $mode))->toBeFalse();
        $duplicate = $query . '&resource=0';
        expect(check_api_key($name, $duplicate, hash('sha256', $signing_key . $duplicate), $mode))->toBeFalse()
            ->and(execute_api_call($duplicate))->toBeFalse();
    }
})->with(['GET' => false, 'POST' => true]);

it('uploads a multipart original through the signed API and requires an uploaded file', function () use (
    &$fixtures,
    &$server,
): void {
    $name = 'api_multipart_' . generateRandomTestID(12);
    $user = $fixtures->user($name, 3);
    ps_query('UPDATE user SET approved = 1 WHERE ref = ?', ['i', $user]);
    $fixtures->onCleanup(static fn () => ps_query('DELETE FROM api_log WHERE user = ?', ['s', $name]));
    $resource = $fixtures->resource(1, 0, $user);
    $query = http_build_query([
        'user' => $name, 'function' => 'upload_multipart', 'ref' => $resource, 'no_exif' => 1, 'revert' => 0,
    ]);
    $path = '/api/index.php?' . $query . '&sign=' . hash('sha256', get_api_key($name) . $query);
    $input = testFixture('Images/image_1.jpg');
    $upload = ['file' => new CURLFile($input, 'image/jpeg', 'multipart.jpg')];

    $get = $server->request($path);
    expect($get['status'])->toBe(405);
    $empty = $server->request($path, ['placeholder' => new CURLFile($input)]);
    expect($empty['status'])->toBe(400)
        ->and(json_decode($empty['body'], true, flags: JSON_THROW_ON_ERROR)['status'])->toBe('fail');
    $response = $server->request($path, $upload);
    expect($response['status'])->toBe(204);
    $original = get_resource_path($resource, true, '', false, 'jpg');
    expect($original)->toBeFile();
    expect(hash_file('sha256', $original))->toBe(hash_file('sha256', $input))
        ->and(get_data_by_field($resource, $GLOBALS['filename_field']))->toBe('multipart.jpg');
});

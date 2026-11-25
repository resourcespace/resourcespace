<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once RESOURCESPACE_BASE_PATH . '/include/api_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/user_api_session_functions.php';

$fixtures = null;
$app = null;
$account = [];
$endpoint = '/pages/user/user_api_session.php';
$exchange = '/pages/user/user_api_session_exchange.php';
$headers = [];
beforeEach(function () use (&$fixtures, &$app, &$account, &$headers): void {
    $fixtures = new IntegrationFixtures(['api_issue_valid_destinations', 'enable_remote_apis']);
    $id = generateRandomTestID(24);
    $group = $fixtures->group('API session users ' . $id, 's,g,c,e0,f*');
    $name = 'sessionuser' . $id;
    $user = $fixtures->user($name, $group);
    $session = generateSecureKey(32);
    $email = $name . '@example.invalid';
    $fullname = 'Session fixture ' . $id;
    ps_query(
        'UPDATE user SET approved = 1, session = ?, email = ?, fullname = ?, last_active = NOW() WHERE ref = ?',
        ['s', $session, 's', $email, 's', $fullname, 'i', $user]
    );
    unset($GLOBALS['udata_cache'][$user]);
    $fixtures->onCleanup(static function () use ($user, $name): void {
        ps_query('DELETE FROM user_api_session_code WHERE user = ?', ['i', $user]);
        ps_query('DELETE FROM api_log WHERE user = ?', ['s', $name]);
    });

    $app = new LocalTestServer();
    $fixtures->onCleanup($app->close(...));
    $account = compact('id', 'group', 'name', 'user', 'session', 'email', 'fullname');
    $app->configure([
        'baseurl' => $app->url,
        'CSRF_enabled' => true,
        'enable_remote_apis' => true,
        'session_autologout' => false,
        'terms_login' => false,
        'use_db_transaction' => false,
        'anonymous_login' => null,
    ]);
    $headers = $app->sessionHeaders($session);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

$form = static function (array $response): array {
    expect($response['status'])->toBe(200);
    $values = LocalTestServer::hiddenFields($response['body'], '//form[contains(@action, "user_api_session.php")]');
    if ($values !== []) {
        expect($response['headers']['referrer-policy'] ?? '')->toBe('same-origin');
    }
    return $values;
};

it('preserves the callback selection and posts only the approved identity fields', function () use (
    &$fixtures,
    &$app,
    &$account,
    &$headers,
    $endpoint,
    $form,
): void {
    $callback = new LocalTestServer();
    $fixtures->onCleanup($callback->close(...));
    $callback_path = '/tests/Fixtures/api-session-callback.php?fixture=' . $account['id'];
    $first = $callback->url . $callback_path . '&mode=primary';
    $second = $callback->url . $callback_path . '&mode=secondary';
    $directory = sys_get_temp_dir() . '/pest-callback-' . $account['id'];
    expect(mkdir($directory, 0700))->toBeTrue();
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $record_path = $directory . '/received.json';
    $destination = [
        'name' => 'Consent fixture', 'querydata' => ['username', 'email', 'fullname'], 'stateparam' => 'state',
    ];

    $state = 'state<&"value';
    foreach ([
        [$first, [], $first],
        [$first, ['url' => $second], $first],
        [[$first, $second], ['url' => $first], $first],
        [[$first, $second], ['url' => $second], $second],
    ] as [$allowed, $query, $expected]) {
        $app->configure(['api_issue_valid_destinations' => ['callback' => $destination + ['url' => $allowed]]]);
        $fields = $form($app->request($endpoint . '?' . http_build_query($query + [
            'system' => 'callback', 'state' => $state,
        ]), headers: $headers));
        expect($fields)->toMatchArray(['state' => $state, 'url' => $expected, 'system' => 'callback']);
        $response = $app->request($endpoint, $fields, $headers);
        expect($response['status'])->toBe(200)->and($response['body'])->toContain('Callback received');
        $callback_record = json_decode(file_get_contents($record_path), true, flags: JSON_THROW_ON_ERROR);
        expect($callback_record['path'])->toBe(substr($expected, strlen($callback->url)));
        expect(array_diff_key($callback_record['post'], ['sessionkey' => true]))->toBe([
            'state' => $state, 'username' => $account['name'], 'email' => $account['email'],
            'fullname' => $account['fullname'],
        ]);
        expect(hash_equals(hash('sha256', get_session_api_key($account['user'])), $callback_record['post']['sessionkey']))
            ->toBeTrue();
        unlink($record_path);
    }

    foreach ([[], ['url' => $second . '/unlisted'], ['url' => [$second]]] as $invalid) {
        $request = ['system' => 'callback', 'state' => $state] + $invalid;
        expect($form($app->request($endpoint . '?' . http_build_query($request), headers: $headers)))->toBeEmpty();
        $response = $app->request($endpoint, $request + [
            'submit' => 'true', $GLOBALS['CSRF_token_identifier'] => $fields[$GLOBALS['CSRF_token_identifier']],
        ], $headers);
        expect($response['status'])->toBe(200)
            ->and($response['body'])->toContain($GLOBALS['lang']['user_api_session_invalid_system'])
            ->and($record_path)->not->toBeFile();
    }
});

it('requires POST consent and exchanges the resulting code once for the permitted identity', function () use (
    &$app,
    &$account,
    &$headers,
    $endpoint,
    $exchange,
    $form,
): void {
    $callback = $app->url . '/callback?client=fixture&mode=live';
    $other = $app->url . '/Callback?client=fixture&mode=live';
    $destination = [
        'name' => 'Code fixture', 'delivery' => 'redirect_code', 'url' => [$callback, $other],
        'querydata' => ['username', 'email'], 'stateparam' => 'connection_state',
    ];
    $app->configure(['api_issue_valid_destinations' => ['redirect' => $destination]]);
    $verifier = generateSecureKey(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $user = $account['user'];
    $count = static fn (): int => (int) ps_value(
        'SELECT COUNT(*) AS value FROM user_api_session_code WHERE user = ?', ['i', $user], 0
    );
    $request = [
        'system' => 'redirect', 'url' => $callback, 'connection_state' => 'state<&"value',
        'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
    ];
    $fields = $form($app->request($endpoint . '?' . http_build_query($request), headers: $headers));
    expect($fields)->toMatchArray($request)->toHaveKey($GLOBALS['CSRF_token_identifier'])
        ->and($count())->toBe(0);
    foreach (['', 'tampered'] as $invalid_token) {
        $bad = [$GLOBALS['CSRF_token_identifier'] => $invalid_token] + $fields;
        expect($app->request($endpoint, $bad, $headers)['status'])->toBe(400)->and($count())->toBe(0);
    }
    $app->request($endpoint . '?' . http_build_query($fields), headers: $headers);
    expect($count())->toBe(0);
    foreach ([
        ['url' => $callback . '/unlisted'], ['url' => [$callback]], ['code_challenge_method' => 'plain'],
        ['code_challenge' => 'bad'], ['connection_state' => ''],
    ] as $override) {
        $invalid = $app->request($endpoint . '?' . http_build_query($override + $request), headers: $headers);
        expect($form($invalid))->toBeEmpty()->and($count())->toBe(0);
    }
    $cancelled = $app->request($endpoint, ['cancel' => 'true'] + $fields, $headers);
    expect($cancelled['status'])->toBe(303)->and($cancelled['headers']['referrer-policy'])->toBe('no-referrer');
    parse_str(parse_url($cancelled['headers']['location'], PHP_URL_QUERY), $parameters);
    expect($parameters)->toMatchArray(['error' => 'access_denied', 'connection_state' => $request['connection_state']])
        ->not->toHaveKey('code')->and($count())->toBe(0);

    $consented = $app->request($endpoint, $fields, $headers);
    expect($consented['status'])->toBe(303)->and($consented['headers']['referrer-policy'])->toBe('no-referrer');
    parse_str(parse_url($consented['headers']['location'], PHP_URL_QUERY), $parameters);
    expect($parameters)->toMatchArray(['connection_state' => $request['connection_state'], 'client' => 'fixture', 'mode' => 'live'])
        ->toHaveKey('code')->not->toHaveKeys(['sessionkey', 'username']);
    $code = $parameters['code'];
    $records = ps_query('SELECT * FROM user_api_session_code WHERE user = ?', ['i', $user]);
    expect($records)->toHaveCount(1);
    expect($records[0]['code_hash'])->toBe(hash('sha256', $code))
        ->and(str_contains(json_encode($records, JSON_THROW_ON_ERROR), $verifier))->toBeFalse()
        ->and(str_contains(json_encode($records, JSON_THROW_ON_ERROR), get_session_api_key($user)))->toBeFalse();

    $body = ['system' => 'redirect', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $callback];
    $get = $app->request($exchange . '?' . http_build_query($body));
    expect($get['status'])->toBe(405)->and($get['headers']['allow'])->toBe('POST');
    expect($app->request($exchange . '?code=' . $code, [])['status'])->toBe(400)
        ->and($app->request($exchange, $body, ['Content-Type: application/json'])['status'])->toBe(415);
    foreach ([
        ['code_verifier' => str_repeat('x', 43)], ['code' => [$code]], ['system' => 'unknown'],
        ['redirect_uri' => $other], ['code_verifier' => str_repeat('x', 129)],
    ] as $override) {
        expect($app->request($exchange, $override + $body)['status'])->toBe(400)->and($count())->toBe(1);
    }

    $result = $app->request($exchange, $body);
    expect($result['status'])->toBe(200)->and($result['headers']['cache-control'])->toBe('no-store');
    $identity = json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
    expect(array_diff_key($identity, ['sessionkey' => true]))->toBe([
        'username' => $account['name'], 'email' => $account['email'],
    ]);
    expect(hash_equals(get_session_api_key($user), $identity['sessionkey']))->toBeTrue()
        ->and($app->request($exchange, $body)['status'])->toBe(400)->and($count())->toBe(0);
    $query = http_build_query(['user' => $account['name'], 'function' => 'get_user_collections']);
    $api = $app->request('/api/index.php?' . $query . '&authmode=sessionkey&sign=' . hash('sha256', $identity['sessionkey'] . $query));
    expect($api['status'])->toBe(200)
        ->and(json_decode($api['body'], true, flags: JSON_THROW_ON_ERROR))->toBeArray();
    expect($app->request('/api/index.php?' . $query . '&authmode=sessionkey&sign=invalid')['status'])->toBe(401);
});

it('keeps native message access available when remote API credentials are disabled', function () use (
    &$fixtures,
    &$app,
    &$account,
    &$headers,
    $endpoint,
    $exchange,
    $form,
): void {
    $app->configure(['enable_remote_apis' => false, 'api_issue_valid_destinations' => [
        'redirect' => [
            'name' => 'Code fixture', 'delivery' => 'redirect_code', 'url' => $app->url . '/callback',
            'querydata' => ['username'], 'stateparam' => 'state',
        ],
    ]]);
    $user = $account['user'];
    $body = 'Native fixture message';
    message_add([$user], $body);
    $messages = [];
    expect(message_get($messages, $user))->toBeTrue();
    $fixtures->onCleanup(static fn () => message_remove($messages[0]['message_id']));
    $query = http_build_query(['function' => 'get_user_message', 'ref' => $messages[0]['message_id']]);
    $api = '/api/index.php?' . $query;
    foreach (['userkey' => get_api_key($account['name']), 'sessionkey' => get_session_api_key($user)] as $mode => $key) {
        $path = $api . '&user=' . $account['name'] . '&authmode=' . $mode;
        $signature = hash('sha256', $key . substr($path, strpos($path, '?') + 1));
        expect($app->request($path . '&sign=' . $signature)['status'])->toBe(403);
    }
    $response = $app->request($api . '&authmode=native', headers: $headers);
    expect($response['status'])->toBe(200)
        ->and(json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR)['message'])->toBe($body);

    $fields = [
        'system' => 'redirect', 'state' => 'pending', 'code_challenge' => str_repeat('A', 43),
        'code_challenge_method' => 'S256',
    ];
    expect($form($app->request($endpoint . '?' . http_build_query($fields), headers: $headers)))->toBeEmpty()
        ->and($app->request($exchange, [])['status'])->toBe(403);
});

it('rechecks destination, account and disclosure when redeeming a code', function () use (&$account, &$app, $exchange): void {
    $user = $account['user'];
    $callback = $app->url . '/callback';
    $destination = [
        'name' => 'Code lifecycle', 'delivery' => 'redirect_code', 'url' => $callback,
        'querydata' => ['username', 'email'], 'stateparam' => 'state',
    ];
    $GLOBALS['enable_remote_apis'] = true;
    $GLOBALS['api_issue_valid_destinations'] = ['lifecycle' => $destination];
    $verifier = generateSecureKey(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $issue = static fn (): string|false =>
        create_api_session_code($user, 'lifecycle', $callback, $challenge, $account['session']);
    $code = $issue();
    expect($code)->toBeString()->toHaveLength(64);
    $redeem = static fn (string $value): array|false =>
        redeem_api_session_code('lifecycle', $value, $verifier, $callback);

    ps_query('UPDATE user_api_session_code SET expires = UNIX_TIMESTAMP() - 1 WHERE user = ?', ['i', $user]);
    expect($redeem($code))->toBeFalse();
    $code = $issue();
    expect($code)->toBeString();
    expect((int) ps_value(
        'SELECT COUNT(*) AS value FROM user_api_session_code WHERE user = ?', ['i', $user], 0
    ))->toBe(1);

    foreach ([
        ['session', generateSecureKey(32)], ['session', ''], ['approved', 0], ['approved', 2],
        ['account_expires', '2000-01-01 00:00:00'], ['usergroup', 999999],
    ] as [$column, $value]) {
        ps_query("UPDATE user SET {$column} = ? WHERE ref = ?", ['s', $value, 'i', $user]);
        expect($redeem($code))->toBeFalse();
        ps_query(
            'UPDATE user SET session = ?, approved = 1, account_expires = NULL, usergroup = ? WHERE ref = ?',
            ['s', $account['session'], 'i', $account['group'], 'i', $user]
        );
    }
    ps_query('UPDATE usergroup SET download_limit = 1 WHERE ref = ?', ['i', $account['group']]);
    expect($redeem($code))->toBeFalse();
    ps_query('UPDATE usergroup SET download_limit = 0 WHERE ref = ?', ['i', $account['group']]);
    $GLOBALS['api_issue_valid_destinations']['lifecycle']['delivery'] = 'post';
    expect($redeem($code))->toBeFalse();
    $GLOBALS['api_issue_valid_destinations']['lifecycle'] = $destination;
    $GLOBALS['api_issue_valid_destinations']['lifecycle']['querydata'] = ['username', 'fullname'];
    $response = $redeem($code);
    expect(array_diff_key($response, ['sessionkey' => true]))->toBe(['username' => $account['name']]);
    expect(hash_equals(get_session_api_key($user), $response['sessionkey']))->toBeTrue();

    expect(get_api_session_destination('lifecycle')['url'])->toBe($callback)
        ->and(create_api_session_code($user, 'lifecycle', $callback . '2', $challenge, $account['session']))
        ->toBeFalse();
    foreach (['http://example.invalid/callback', 'https://example.invalid/callback#fragment'] as $blocked) {
        $GLOBALS['api_issue_valid_destinations']['lifecycle']['url'] = $blocked;
        expect(get_api_session_destination('lifecycle'))->toBeFalse();
    }
    $GLOBALS['api_issue_valid_destinations']['lifecycle'] = $destination;

    $code = $issue();
    $app->configure(['api_issue_valid_destinations' => ['lifecycle' => $destination]]);
    $other = new LocalTestServer();
    try {
        $multi = curl_multi_init();
        $handles = [];
        $post = http_build_query([
            'system' => 'lifecycle', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $callback,
        ]);
        foreach ([$app->url, $other->url] as $url) {
            $curl = curl_init($url . $exchange);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_POSTFIELDS => $post]);
            curl_multi_add_handle($multi, $curl);
            $handles[] = $curl;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0 && curl_multi_select($multi, 0.1) < 0) {
                usleep(1000);
            }
        } while ($running > 0 && $status === CURLM_OK);
        $responses = [];
        foreach ($handles as $curl) {
            $responses[] = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $curl);
        }
        curl_multi_close($multi);
        expect($responses)->toEqualCanonicalizing([200, 400]);
    } finally {
        $other->close();
    }
});

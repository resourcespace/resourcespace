<?php

command_line_only();
include_once dirname(__DIR__, 2) . '/include/api_functions.php';
include_once dirname(__DIR__, 2) . '/include/user_api_session_functions.php';

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $directory = sys_get_temp_dir() . '/rs_api_code_' . test_generate_random_ID(8);
    $servers = $server_pipes = [];
    $saved_keys = [$GLOBALS['scramble_key'], $GLOBALS['api_scramble_key']];
    $group = save_usergroup(0, ['name' => 'API code test', 'permissions' => 's,g,c,e0']);
    $user = new_user('api_code_' . test_generate_random_ID(6), $group);
    $session = generateSecureKey(32);
    try {
        mkdir($directory . '/pages/user', 0700, true);
        mkdir($directory . '/include');
        mkdir($directory . '/api');
        $root = dirname(__DIR__, 2);
        foreach (['user_api_session', 'user_api_session_exchange'] as $page) {
            copy($root . '/pages/user/' . $page . '.php', $directory . '/pages/user/' . $page . '.php');
        }
        copy($root . '/api/index.php', $directory . '/api/index.php');
        // The router boots the real installation in RS_TEST_MODE, then applies fixture configuration.
        // Keep real authentication, CSRF and API bindings; omit only the unrelated site chrome.
        foreach (['boot', 'header', 'footer'] as $include) {
            file_put_contents($directory . '/include/' . $include . '.php', '<?php');
        }
        foreach (
            ['api_functions', 'user_api_session_functions', 'authenticate', 'image_processing',
            'ajax_functions', 'api_bindings', 'login_functions', 'dash_functions'] as $include
        ) {
            file_put_contents(
                $directory . '/include/' . $include . '.php',
                '<?php require_once ' . var_export($root . '/include/' . $include . '.php', true) . ';'
            );
        }

        $addresses = [];
        for ($i = 0; $i < 2; $i++) {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            $check($socket !== false, 'Unable to reserve HTTP port');
            $addresses[] = stream_socket_get_name($socket, false);
            fclose($socket);
        }
        $base = 'http://' . $addresses[0];
        $return_url = $base . '/callback?client=cihub&mode=live';
        $other_url = $base . '/Callback?client=cihub&mode=live';
        $configuration = [
            'baseurl' => $base, 'baseurl_short' => '/', 'CSRF_enabled' => true,
            'scramble_key' => 'API code test signing fixture', 'api_scramble_key' => 'API code test API fixture',
            'enable_remote_apis' => true, 'session_autologout' => false, 'terms_login' => false,
            'use_db_transaction' => false,
            'plugins' => [], 'storagedir' => $GLOBALS['storagedir'],
            'api_issue_valid_destinations' => [
                'code_test' => [
                    'name' => 'Code test', 'delivery' => 'redirect_code', 'url' => [$return_url, $other_url],
                    'querydata' => ['username', 'email'], 'stateparam' => 'connection_state',
                ],
            ],
        ];
        $write_configuration = static function (array $configuration) use ($directory): void {
            file_put_contents($directory . '/config.json', json_encode($configuration));
        };
        $write_configuration($configuration);
        // Only fixture keys are written to disk; database credentials remain in the normal bootstrap.
        $GLOBALS['scramble_key'] = $configuration['scramble_key'];
        $GLOBALS['api_scramble_key'] = $configuration['api_scramble_key'];
        ps_query(
            'UPDATE user SET approved = 1, session = ?, email = ?, fullname = ?, last_active = NOW() WHERE ref = ?',
            ['s', $session, 's', 'code@example.com', 's', 'API code fixture', 'i', $user]
        );
        $router = '<?php define("RS_TEST_MODE", 1); $rs_test_mysql_db = "rs_test_db";'
            . '$disable_browser_check = true; $suppress_headers = true; require '
            . var_export($root . '/include/boot.php', true) . ';'
            . 'foreach (json_decode(file_get_contents(__DIR__ . "/config.json"), true) as $key => $value)'
            . '{ $GLOBALS[$key] = $value; }'
            . 'unset($anonymous_login); $route = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);'
            . 'if ($route === "/ready") { echo "ready"; return; }'
            . 'if ($route === "/callback") { file_put_contents(__DIR__ . "/callback_hit", "yes"); return; }'
            . 'if ($route === "/api") { chdir(__DIR__ . "/api"); include "index.php"; return; }'
            . 'chdir(__DIR__ . "/pages/user");'
            . 'if ($route === "/authorize") { $pagename = "user_api_session"; include "user_api_session.php"; return; }'
            . 'if ($route === "/exchange") { include "user_api_session_exchange.php"; return; }'
            . 'http_response_code(404);';
        file_put_contents($directory . '/router.php', $router);
        foreach ($addresses as $i => $address) {
            $pipes = [];
            $servers[] = proc_open(
                [PHP_BINARY, '-S', $address, $directory . '/router.php'],
                [['pipe', 'r'], ['file', $directory . '/server.log', 'a'], ['file', $directory . '/server.log', 'a']],
                $pipes
            );
            $server_pipes[] = $pipes;
            $check(is_resource($servers[$i]), 'Unable to start code exchange HTTP server');
        }

        $request = static function (
            string $path,
            ?array $post = null,
            bool $authenticated = true,
            array $extra_headers = []
        ) use (
            $base,
            $session
): array {
            $headers = [];
            $curl = curl_init($base . $path);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$headers) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return strlen($line);
                },
                CURLOPT_HTTPHEADER => $extra_headers,
            ]);
            if ($authenticated) {
                curl_setopt($curl, CURLOPT_COOKIE, 'user=' . $session);
            }
            if ($post !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
            }
            $body = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            return ['status' => $status, 'body' => $body, 'headers' => $headers];
        };
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if ($request('/ready', null, false)['body'] === 'ready') {
                $ready = true;
                break;
            }
            usleep(20000);
        }
        $check($ready, 'Code exchange HTTP server did not become ready');
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM'; // RFC 7636 test vector.
        $authorization = [
            'system' => 'code_test', 'url' => $return_url, 'connection_state' => 'state<&"value',
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ];
        $get_form = static function (array $params) use ($request, $check): array {
            $result = $request('/authorize?' . http_build_query($params));
            $check($result['status'] === 200, 'Consent GET failed');
            $check(
                ($result['headers']['referrer-policy'] ?? '') === 'same-origin',
                'Consent policy would suppress the same-origin POST Origin header'
            );
            $document = new DOMDocument();
            $document->loadHTML($result['body']);
            $values = [];
            foreach ($document->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('type') === 'hidden') {
                    $values[$input->getAttribute('name')] = $input->getAttribute('value');
                }
            }
            return $values;
        };
        $issue = static function () use ($get_form, $authorization, $request, $check): array {
            $form = $get_form($authorization);
            $check(
                ($form['connection_state'] ?? '') === $authorization['connection_state'],
                'Custom state name/value lost'
            );
            $check(($form['code_challenge'] ?? '') === $authorization['code_challenge'], 'PKCE challenge lost');
            $response = $request('/authorize', $form);
            $check(
                $response['status'] === 303,
                'Consent POST did not redirect (HTTP ' . $response['status'] . '): ' . strip_tags($response['body'])
            );
            $check(
                ($response['headers']['referrer-policy'] ?? '') === 'no-referrer',
                'Redirect missing referrer policy'
            );
            parse_str(parse_url($response['headers']['location'] ?? '', PHP_URL_QUERY) ?? '', $params);
            $check(($params['connection_state'] ?? '') === $authorization['connection_state'], 'Redirect state lost');
            $check(
                ($params['client'] ?? '') === 'cihub' && ($params['mode'] ?? '') === 'live',
                'Static return query lost'
            );
            $check(
                isset($params['code']) && !isset($params['sessionkey']) && !isset($params['username']),
                'Invalid redirect payload'
            );
            return $params;
        };
        $exchange = static function (
            string $code,
            array $overrides = []
        ) use (
            $request,
            $verifier,
            $return_url
        ): array {
            return $request('/exchange', $overrides + [
                'system' => 'code_test', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $return_url,
            ], false);
        };
        $count = static fn() => ps_value(
            'SELECT COUNT(*) value FROM user_api_session_code WHERE user = ?',
            ['i', $user],
            0
        );

        $form = $get_form($authorization);
        $check(isset($form['CSRFToken']), 'Consent form missing CSRF token');
        $check($count() === 0, 'Consent GET issued a code');
        foreach (['', 'tampered'] as $token) {
            $bad = $form;
            unset($bad['CSRFToken']);
            if ($token !== '') {
                $bad['CSRFToken'] = $token;
            }
            $check($request('/authorize', $bad)['status'] === 400 && $count() === 0, 'Invalid CSRF issued a code');
        }
        $request('/authorize?' . http_build_query($form));
        $check($count() === 0, 'GET submission issued a code');
        foreach (
            [['url' => $return_url . '/unlisted'], ['code_challenge_method' => 'plain'],
            ['code_challenge' => 'bad'], ['connection_state' => ''], ['url' => [$return_url]]] as $override
        ) {
            $invalid = $get_form($override + $authorization);
            $check($invalid === [] && $count() === 0, 'Invalid authorization rendered a consent form');
        }
        $cancel = $request('/authorize', ['cancel' => 'true'] + $form);
        parse_str(parse_url($cancel['headers']['location'] ?? '', PHP_URL_QUERY) ?? '', $canceled);
        $check(
            $cancel['status'] === 303 && ($canceled['error'] ?? '') === 'access_denied'
            && ($canceled['connection_state'] ?? '') === $authorization['connection_state'] && $count() === 0,
            'Cancellation issued a code or lost state'
        );
        $check(
            ($cancel['headers']['referrer-policy'] ?? '') === 'no-referrer',
            'Cancellation redirect missing referrer policy'
        );

        $code = $issue()['code'];
        $record = ps_query('SELECT * FROM user_api_session_code WHERE user = ?', ['i', $user])[0];
        $check($record['code_hash'] === hash('sha256', $code), 'Code not hashed in storage');
        $check(!str_contains(json_encode($record), $verifier)
            && !str_contains(json_encode($record), get_session_api_key($user)), 'Secret persisted in code record');
        $get_exchange = $request('/exchange?' . http_build_query([
            'system' => 'code_test', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $return_url,
        ]), null, false);
        $check(
            $get_exchange['status'] === 405 && ($get_exchange['headers']['allow'] ?? '') === 'POST',
            'Exchange accepted GET'
        );
        $check($request('/exchange?code=' . $code, [], false)['status'] === 400, 'Exchange accepted query fallback');
        $check(
            $request('/exchange', ['code' => $code], false, ['Content-Type: application/json'])['status'] === 415,
            'Exchange accepted wrong content type'
        );
        foreach (
            [['code_verifier' => str_repeat('x', 43)], ['code' => [$code]], ['system' => 'unknown'],
            ['redirect_uri' => $other_url], ['code_verifier' => str_repeat('x', 129)]] as $override
        ) {
            $check(
                $exchange($code, $override)['status'] === 400 && $count() === 1,
                'Invalid exchange succeeded or consumed code'
            );
        }
        $response = $exchange($code);
        $credentials = json_decode($response['body'], true);
        $check(
            $response['status'] === 200 && ($credentials['sessionkey'] ?? '') === get_session_api_key($user),
            'Valid exchange failed'
        );
        $check(
            ($credentials['email'] ?? '') === 'code@example.com' && !isset($credentials['fullname']),
            'Identity policy ignored'
        );
        $check(($response['headers']['cache-control'] ?? '') === 'no-store', 'Exchange cache policy missing');
        $check($exchange($code)['status'] === 400, 'Code replay succeeded');
        $check(!file_exists($directory . '/callback_hit'), 'ResourceSpace called the remote callback');

        $query = http_build_query(['user' => $credentials['username'], 'function' => 'get_user_collections']);
        $api = $request('/api?' . $query . '&authmode=sessionkey&sign='
            . hash('sha256', $credentials['sessionkey'] . $query), null, false);
        $check(
            $api['status'] === 200 && is_array(json_decode($api['body'], true)),
            'Retrieved key could not sign API call'
        );
        $check(
            $request('/api?' . $query . '&authmode=sessionkey&sign=invalid', null, false)['status'] === 401,
            'API accepted invalid signature'
        );

        $code = $issue()['code'];
        ps_query('UPDATE user_api_session_code SET expires = UNIX_TIMESTAMP() - 1 WHERE user = ?', ['i', $user]);
        $check($exchange($code)['status'] === 400, 'Expired code accepted');
        $code = $issue()['code'];
        $check($count() === 1, 'Expired records not cleaned on issuance');
        foreach (
            [['session', 'replacement'], ['session', ''], ['approved', 0], ['approved', 2],
            ['account_expires', '2000-01-01 00:00:00'],
            ['usergroup', 999999]] as [$column, $value]
        ) {
            // Identifiers are fixed test literals above; only values are bound.
            ps_query("UPDATE user SET {$column} = ? WHERE ref = ?", ['s', $value, 'i', $user]);
            $check($exchange($code)['status'] === 400, 'Changed session/ineligible account accepted');
            ps_query(
                'UPDATE user SET session = ?, approved = 1, account_expires = NULL, usergroup = ? WHERE ref = ?',
                ['s', $session, 'i', $group, 'i', $user]
            );
        }
        ps_query('UPDATE usergroup SET download_limit = 1 WHERE ref = ?', ['i', $group]);
        $check($exchange($code)['status'] === 400, 'API-restricted group accepted');
        ps_query('UPDATE usergroup SET download_limit = 0 WHERE ref = ?', ['i', $group]);
        $disabled = $configuration;
        $disabled['enable_remote_apis'] = false;
        $write_configuration($disabled);
        $check($exchange($code)['status'] === 403, 'Disabled remote APIs accepted exchange');
        $check($get_form($authorization) === [], 'Disabled remote APIs allowed consent');
        $changed = $configuration;
        $changed['api_issue_valid_destinations']['code_test']['delivery'] = 'post';
        $write_configuration($changed);
        $check($exchange($code)['status'] === 400, 'Disabled code delivery accepted exchange');
        $changed = $configuration;
        $changed['api_issue_valid_destinations']['code_test']['querydata'] = ['username', 'fullname'];
        $write_configuration($changed);
        $response = $exchange($code);
        $check(
            $response['status'] === 200
                && array_keys(json_decode($response['body'], true)) === ['sessionkey', 'username'],
            'Identity disclosure expanded after consent'
        );
        $write_configuration($configuration);

        $single = $configuration;
        $single['api_issue_valid_destinations']['code_test']['url'] = $return_url;
        $write_configuration($single);
        $single_form = $get_form(array_diff_key($authorization, ['url' => true]));
        $check(($single_form['url'] ?? '') === $return_url, 'Fixed URL required a URL parameter');
        $single_response = $request('/authorize', $single_form);
        parse_str(parse_url($single_response['headers']['location'] ?? '', PHP_URL_QUERY) ?? '', $single_params);
        $check($single_response['status'] === 303, 'Fixed URL consent did not redirect');
        $check(
            $exchange($single_params['code'], ['redirect_uri' => $other_url])['status'] === 400,
            'Fixed URL exchange accepted a different return URL'
        );
        $check($exchange($single_params['code'])['status'] === 200, 'Fixed URL exchange failed');
        foreach (
            ['http://example.com/callback', 'https://example.com/callback#fragment',
            'https://user:password@example.com/callback'] as $unsafe_url
        ) {
            $unsafe = $configuration;
            $unsafe['api_issue_valid_destinations']['code_test']['url'] = $unsafe_url;
            $write_configuration($unsafe);
            $check($get_form(['url' => $unsafe_url] + $authorization) === [], 'Unsafe callback configuration accepted');
        }
        $write_configuration($configuration);

        // Independent servers race to redeem one code; exactly one response may contain credentials.
        $code = $issue()['code'];
        $multi = curl_multi_init();
        $handles = [];
        foreach ($addresses as $address) {
            $handle = curl_init('http://' . $address . '/exchange');
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => http_build_query([
                    'system' => 'code_test', 'code' => $code, 'code_verifier' => $verifier,
                    'redirect_uri' => $return_url,
                ])]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running);
        $statuses = [];
        foreach ($handles as $handle) {
            $statuses[] = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        sort($statuses);
        $check($statuses === [200, 400], 'Concurrent redemption did not elect exactly one winner');
        return true;
    } catch (Throwable $error) {
        echo $error->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($servers as $i => $server) {
            if (is_resource($server)) {
                proc_terminate($server);
                foreach ($server_pipes[$i] as $pipe) {
                    fclose($pipe);
                }
                proc_close($server);
            }
        }
        [$GLOBALS['scramble_key'], $GLOBALS['api_scramble_key']] = $saved_keys;
        ps_query('DELETE FROM user_api_session_code WHERE user = ?', ['i', $user]);
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        delete_usergroup($group);
        if (is_dir($directory)) {
            rcRmdir($directory);
        }
    }
})();

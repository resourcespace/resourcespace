<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    // Run the real authentication and download entrypoints against the runner's isolated database/filestore.
    $directory = sys_get_temp_dir() . '/rs_http_' . test_generate_random_ID(8);
    $server = null;
    $pipes = [];
    $group = save_usergroup(0, ['name' => 'HTTP lifecycle', 'permissions' => 's,g,c,e0']);
    $user = new_user('http_' . test_generate_random_ID(6), $group);
    $name = get_user($user)['username'];
    $cookie_file = $directory . '/cookies';
    try {
        mkdir($directory, 0700);
        // Multi-digit IDs exercise the runner's even-spread filestore layout.
        do {
            $resource = create_resource(1, 0);
        } while ($resource < 10);
        $collection = create_collection($user, 'HTTP selection');
        add_resource_to_collection($resource, $collection, true);
        $bytes = "HTTP download fixture\n" . test_generate_random_ID(16);
        ps_query('UPDATE resource SET file_extension = ? WHERE ref = ?', ['s', 'txt', 'i', $resource]);
        $path = get_resource_path($resource, true, '', true, 'txt');
        file_put_contents($path, $bytes);
        ps_query('UPDATE user SET password = ?, approved = 1, last_active = NOW() WHERE ref = ?',
            ['s', rs_password_hash('RS' . $name . 'BrowserPassword1'), 'i', $user]);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $check($socket !== false, 'Unable to reserve HTTP port');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $configuration = [];
        foreach (['mysql_db', 'mysql_charset', 'mysql_server', 'mysql_server_port', 'mysql_username', 'mysql_password', 'read_only_db_username', 'read_only_db_password', 'storagedir', 'storageurl', 'scramble_key', 'api_scramble_key', 'password_hash_info', 'filestore_evenspread', 'originals_separate_storage', 'originals_separate_storage_ffmpegalts_as_previews', 'fstemplate_alt_threshold', 'fstemplate_alt_storagedir', 'fstemplate_alt_storageurl', 'fstemplate_alt_scramblekey', 'filestore_migrate', 'migrating_scrambled', 'scramble_key_old'] as $key) {
            if (array_key_exists($key, $GLOBALS)) {
                $configuration[$key] = $GLOBALS[$key];
            }
        }
        $configuration += ['baseurl' => 'http://' . $address, 'baseurl_short' => '/', 'CSRF_enabled' => true,
            'disable_browser_check' => true, 'session_autologout' => true, 'session_length' => 10,
            'terms_download' => false, 'download_usage' => false, 'exiftool_write' => false,
            'randomised_session_hash' => true, 'download_filename_format' => '%resource.%extension'];
        file_put_contents($directory . '/config.php', '<?php return ' . var_export($configuration, true) . ';');
        $root = dirname(__DIR__, 2);
        mkdir($directory . '/pages/ajax', 0700, true);
        mkdir($directory . '/include');
        // Keep endpoint code intact; the router already bootstraps the isolated application.
        file_put_contents($directory . '/include/boot.php', '<?php');
        foreach (['authenticate', 'ajax_functions', 'image_processing'] as $include) {
            file_put_contents($directory . '/include/' . $include . '.php', '<?php include_once ' . var_export($root . '/include/' . $include . '.php', true) . ';');
        }
        foreach (['header', 'footer'] as $include) {
            file_put_contents($directory . '/include/' . $include . '.php', '<?php');
        }
        copy($root . '/pages/download.php', $directory . '/pages/download.php');
        copy($root . '/pages/ajax/collections.php', $directory . '/pages/ajax/collections.php');
        $router = '<?php ' . "\n" . 'define("RS_TEST_MODE", 1); $disable_browser_check = true; $rs_test_mysql_db = ' . var_export($GLOBALS['mysql_db'], true) . ';' . "\n" . 'require ' . var_export($root . '/include/boot.php', true) . ";\n"
            . 'include ' . var_export($root . '/include/config.default.php', true) . ";\n"
            . 'foreach (require __DIR__ . "/config.php" as $key => $value) { $GLOBALS[$key] = $value; }' . "\n"
            . 'unset($anonymous_login); sql_connect();' . "\n"
            . 'include_once ' . var_export($root . '/include/image_processing.php', true) . ";\n"
            . '$endpoint = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);' . "\n"
            . 'if ($endpoint === "/ready") { echo "ready"; return; }' . "\n"
            . 'if ($endpoint === "/login") {' . "\n"
            . 'include_once ' . var_export($root . '/include/login_functions.php', true) . ";\n"
            . '$login = perform_login(getval("username", ""), getval("password", ""));'
            . 'if ($login["valid"]) { set_login_cookies($login["ref"], $login["session_hash"]); }'
            . 'header("Content-Type: application/json"); echo json_encode($login); return; }' . "\n"
            . 'if ($endpoint === "/download") { chdir(__DIR__ . "/pages"); include ' . var_export($directory . '/pages/download.php', true) . '; return; }' . "\n"
            . 'include ' . var_export($root . '/include/authenticate.php', true) . ";\n"
            . 'if ($endpoint === "/identity") { header("Content-Type: application/json"); echo json_encode(["ref" => $userref, "token" => generateCSRFToken($usersession, "clear"), "identifier" => $CSRF_token_identifier]); return; }' . "\n"
            . '$USER_SELECTION_COLLECTION = ' . (int) $collection . ";\n"
            . 'if ($endpoint === "/clear") { include ' . var_export($directory . '/pages/ajax/collections.php', true) . '; return; }';
        file_put_contents($directory . '/router.php', $router);
        $server = proc_open([PHP_BINARY, '-S', $address, $directory . '/router.php'],
            [['pipe', 'r'], ['file', $directory . '/server.log', 'a'], ['file', $directory . '/server.log', 'a']], $pipes);
        $check(is_resource($server), 'HTTP server failed to start');
        $request = static function (string $endpoint, ?array $post = null, ?string $cookie = null) use ($address, $cookie_file): array {
            $handle = curl_init('http://' . $address . $endpoint);
            $headers = [];
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'ResourceSpace automated tests',
                CURLOPT_HTTPHEADER => ['Origin: ' . $GLOBALS['baseurl']],
                CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$headers) {
                    $headers[] = trim($line);
                    return strlen($line);
                }]);
            if ($post !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
            }
            if ($cookie !== null) {
                curl_setopt($handle, CURLOPT_COOKIE, $cookie);
            } else {
                curl_setopt_array($handle, [CURLOPT_COOKIEFILE => $cookie_file, CURLOPT_COOKIEJAR => $cookie_file]);
            }
            $body = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            return ['status' => $status, 'body' => $body, 'headers' => $headers];
        };
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (($probe = $request('/ready'))['body'] === 'ready') {
                $ready = true;
                break;
            }
            usleep(20000);
        }
        $check($ready, 'HTTP server not ready: ' . json_encode($probe) . substr(file_get_contents($directory . '/server.log'), -2500));
        $login = $request('/login', ['username' => $name, 'password' => 'BrowserPassword1']);
        $result = json_decode($login['body'], true);
        $check(($result['valid'] ?? false) && $result['ref'] == $user, 'HTTP login failed: ' . $login['body']);
        $check(stripos(implode("\n", $login['headers']), 'HttpOnly') !== false, 'Login cookie missing HttpOnly');
        $identity = $request('/identity');
        $identity_data = json_decode($identity['body'], true);
        $check(($identity_data['ref'] ?? 0) == $user, 'Cookie did not authenticate subsequent request: ' . $identity['body']);
        $token = $identity_data['token'];
        $identifier = $identity_data['identifier'];
        $snapshot = static fn () => ps_query('SELECT * FROM collection_resource WHERE collection = ?', ['i', $collection]);
        $before = $snapshot();
        $query = '/clear?action=clear_selection_collection_resources&collection=' . $collection;
        $get_response = $request($query);
        $check($get_response['status'] === 405 && $snapshot() === $before && file_get_contents($path) === $bytes, 'State-changing GET was not rejected without side effects: ' . json_encode($get_response));
        foreach ([[], [$identifier => 'tampered'], [$identifier => generateCSRFToken('wrong_session', 'clear')]] as $invalid) {
            $response = $request($query, $invalid + ['ajax' => 'true']);
            $check($response['status'] === 400 && $snapshot() === $before && file_get_contents($path) === $bytes, 'Invalid CSRF POST was not rejected without side effects');
        }
        $response = $request($query, [$identifier => $token]);
        $check($response['status'] === 200 && $snapshot() === [], 'Valid CSRF mutation did not persist: ' . $response['body']);
        $download = $request('/download?ref=' . $resource . '&ext=txt');
        $check($download['status'] === 200 && $download['body'] === $bytes, 'Download bytes incorrect: ' . $download['body']);
        $headers = implode("\n", $download['headers']);
        $check(stripos($headers, 'Content-Disposition: attachment;') !== false && str_contains($headers, 'filename="' . $resource . '.txt"')
            && stripos($headers, 'Content-Type: text/plain') !== false, 'Download filename/type headers incorrect: ' . $headers);
        ps_query('UPDATE resource SET access = 2 WHERE ref = ?', ['i', $resource]);
        $denied = $request('/download?ref=' . $resource . '&ext=txt');
        $check($denied['body'] !== $bytes && !str_contains((string) $denied['body'], $bytes), 'Denied download disclosed file bytes');
        $tampered = $request('/identity', null, 'user=invalid_session');
        $check(($data = json_decode($tampered['body'], true)) === null || ($data['ref'] ?? 0) != $user, 'Tampered session authenticated');
        ps_query('UPDATE user SET last_active = NOW() - INTERVAL 1 DAY WHERE ref = ?', ['i', $user]);
        $expired = $request('/identity');
        $check(ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], 'missing') === '', 'Expired session not revoked');
        $check($expired['body'] !== $identity['body'], 'Expired session returned identity');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($server);
        }
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        delete_usergroup($group);
        if (is_dir($directory)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($directory);
        }
    }
})();

<?php

command_line_only();
include_once dirname(__DIR__, 2) . '/include/api_functions.php';

return (static function () {
    $directory = sys_get_temp_dir() . '/rs_api_session_' . bin2hex(random_bytes(8));
    $server = null;
    $pipes = [];
    $saved_get = $_GET;
    $saved_post = $_POST;
    $saved_cookie = $_COOKIE;
    $saved_server = $_SERVER;
    $saved_cwd = getcwd();
    $check = static function ($condition, $message) {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    try {
        mkdir($directory . '/pages/user', 0700, true);
        mkdir($directory . '/include');
        // Exercise the complete, unmodified page with the runner's authenticated user and configuration.
        // Boot, authentication and site chrome are already supplied by the runner; isolate those includes.
        foreach (['boot', 'api_functions', 'authenticate', 'header', 'footer'] as $include) {
            file_put_contents($directory . '/include/' . $include . '.php', '<?php');
        }
        copy(dirname(__DIR__, 2) . '/pages/user/user_api_session.php', $directory . '/pages/user/user_api_session.php');
        file_put_contents($directory . '/router.php', <<<'PHP'
<?php
file_put_contents(__DIR__ . '/callback.json', json_encode(['url' => $_SERVER['REQUEST_URI'], 'params' => $_POST]));
header('Content-Type: application/json');
echo json_encode(['message' => 'Callback received']);
PHP
        );
        // Ask the OS for an available port, then start a separate server so callback POSTs cannot deadlock.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $check($socket !== false, 'Unable to reserve callback port: ' . $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open(
            [PHP_BINARY, '-S', $address, $directory . '/router.php'],
            [['pipe', 'r'], ['file', $directory . '/server.log', 'a'], ['file', $directory . '/server.log', 'a']],
            $pipes
        );
        $check(is_resource($server), 'Unable to start callback server');
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $probe = curl_init('http://' . $address . '/ready');
            curl_setopt_array($probe, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 100]);
            $response = curl_exec($probe);
            curl_close($probe);
            if ($response !== false) {
                $ready = true;
                break;
            }
            usleep(20000);
        }
        $check($ready, 'Callback server did not become ready');

        $production = 'http://' . $address . '/production?client=cihub&mode=live';
        $testing = 'http://' . $address . '/testing';
        $destination = ['name' => 'Callback test', 'querydata' => ['username', 'email', 'fullname'], 'stateparam' => 'state'];
        $render = static function ($urls, array $get, array $post = []) use ($directory, $destination) {
            // Each request gets fresh page-local variables, including $message.
            extract($GLOBALS, EXTR_SKIP);
            unset($message);
            $api_issue_valid_destinations = ['callback_test' => $destination + ['url' => $urls]];
            $_GET = ['system' => 'callback_test'] + $get;
            $_POST = $post;
            $_COOKIE = [];
            $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
            chdir($directory . '/pages/user');
            ob_start();
            try {
                include $directory . '/pages/user/user_api_session.php';
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        };
        $form_values = static function (string $html): array {
            $document = new DOMDocument();
            $document->loadHTML($html);
            $values = [];
            foreach ($document->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('type') === 'hidden') {
                    $values[$input->getAttribute('name')] = $input->getAttribute('value');
                }
            }
            return $values;
        };

        foreach ([
            ['single URL without parameter', $production, [], $production],
            ['single URL ignores override', $production, ['url' => $testing], $production],
            ['first allowed URL', [$production, $testing], ['url' => $production], $production],
            ['second allowed URL', [$production, $testing], ['url' => $testing], $testing],
        ] as [$label, $urls, $get, $expected]) {
            $html = $render($urls, $get + ['state' => 'state<&"value']);
            $check(str_contains($html, '<form'), $label . ': consent form missing');
            $post = $form_values($html);
            $check(($post['url'] ?? null) === $expected, $label . ': callback not preserved in form');
            $check(($post['state'] ?? null) === 'state<&"value', $label . ': state not preserved');
            $check(($post['system'] ?? null) === 'callback_test', $label . ': system not preserved');
            if (is_file($directory . '/callback.json')) {
                unlink($directory . '/callback.json');
            }
            // Submit only form values, without the original URL query parameter.
            $html = $render($urls, [], $post);
            $check(str_contains($html, escape($GLOBALS['lang']['user_api_session_grant_success'])), $label . ': callback submission failed');
            $check(is_file($directory . '/callback.json'), $label . ': callback was not sent');
            $received = json_decode(file_get_contents($directory . '/callback.json'), true);
            $check($received['url'] === substr($expected, strlen('http://' . $address)), $label . ': wrong callback destination');
            $expected_params = [
                'state' => 'state<&"value',
                'sessionkey' => get_session_api_key($GLOBALS['userref']),
                'username' => $GLOBALS['username'],
                'email' => $GLOBALS['useremail'],
                'fullname' => $GLOBALS['userfullname'],
            ];
            // HTTP form encoding omits null values and converts scalar values to strings.
            parse_str(http_build_query($expected_params), $expected_params);
            $check($received['params'] === $expected_params, $label . ': callback payload incorrect');
        }

        foreach ([[], ['url' => $testing . '/extra'], ['url' => [$testing]]] as $invalid) {
            foreach ([false, true] as $submit) {
                if (is_file($directory . '/callback.json')) {
                    unlink($directory . '/callback.json');
                }
                $html = $render([$production, $testing], $submit ? [] : $invalid, $submit ? $invalid + ['submit' => 'true'] : []);
                $check(str_contains($html, escape($GLOBALS['lang']['user_api_session_invalid_system'])), 'Invalid callback was not rejected');
                $check(!str_contains($html, '<form'), 'Invalid callback displayed consent form');
                $check(!file_exists($directory . '/callback.json'), 'Invalid callback received credentials');
            }
        }
        return true;
    } catch (Throwable $error) {
        echo $error->getMessage() . ' - ';
        return false;
    } finally {
        $_GET = $saved_get;
        $_POST = $saved_post;
        $_COOKIE = $saved_cookie;
        $_SERVER = $saved_server;
        chdir($saved_cwd);
        if (is_resource($server)) {
            proc_terminate($server);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($server);
        }
        if (is_dir($directory)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
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

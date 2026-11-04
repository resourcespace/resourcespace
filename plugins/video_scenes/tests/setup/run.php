<?php

/** HTTP regressions for setup and scene description saving. Uses no installation data. */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$directory = sys_get_temp_dir() . '/rs_video_scenes_setup_' . bin2hex(random_bytes(8));
$server = null;
$pipes = [];
mkdir($directory . '/include', 0700, true);
mkdir($directory . '/plugins/video_scenes/pages', 0700, true);
mkdir($directory . '/plugins/video_scenes/include', 0700, true);
try {
    $plugin = dirname(__DIR__, 2);
    copy($plugin . '/pages/setup.php', $directory . '/plugins/video_scenes/pages/setup.php');
    copy($plugin . '/pages/edit_description.php', $directory . '/plugins/video_scenes/pages/edit_description.php');
    file_put_contents(
        $directory . '/plugins/video_scenes/include/video_scene_functions.php',
        '<?php require ' . var_export($plugin . '/include/video_scene_functions.php', true) . ';'
    );
    foreach (['boot', 'authenticate'] as $file) {
        file_put_contents($directory . '/include/' . $file . '.php', '<?php');
    }
    file_put_contents($directory . '/include/header.php', '<?php echo "<html><body>"; flush();');
    file_put_contents($directory . '/include/footer.php', '<?php echo "</body></html>";');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $assert($socket !== false, 'Unable to reserve fixture port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $environment = getenv();
    $environment['VIDEO_SCENES_SETUP_TEST_DIR'] = $directory;
    $server = proc_open(
        [PHP_BINARY, '-d', 'output_buffering=0', '-S', $address, __DIR__ . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'],
        2 => ['file', $directory . '/server.log', 'a']],
        $pipes,
        null,
        $environment
    );
    $assert(is_resource($server), 'Unable to start setup HTTP fixture');
    $request = static function (?array $post = null, string $query = '', string $page = 'setup') use ($address): array {
        $curl = curl_init('http://' . $address . '/' . $page . $query);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($curl);
        return ['body' => $body, 'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE)];
    };
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $response = $request();
        if ($response['status'] > 0) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    $assert($ready && $response['status'] === 200, 'Setup page did not render');
    $document = new DOMDocument();
    @$document->loadHTML($response['body']);
    $fields = [];
    foreach ($document->getElementsByTagName('input') as $input) {
        if (str_starts_with($input->getAttribute('name'), 'video_scene_')) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
    }
    $assert(($fields['video_scene_timeout'] ?? null) === '7200', 'Timeout field missing from actual core renderer');
    $fields['video_scene_audio'] = '1';
    $fields['save'] = 'Save configuration';
    $response = $request($fields);
    $assert($response['status'] === 200 && is_file($directory . '/settings.json'), 'Valid setup save failed');
    $saved = file_get_contents($directory . '/settings.json');
    $fields['video_scene_timeout'] = '1';
    $fields['video_scene_threshold'] = '0.65';
    $response = $request($fields);
    $assert($response['status'] === 400, 'Invalid setup should return 400 before header output');
    $assert(substr_count($response['body'], '<html>') === 1, 'Validation rendered duplicate page headers');
    $assert(str_contains($response['body'], 'role="alert"'), 'Validation error is missing');
    $assert(str_contains($response['body'], 'value="0.65"'), 'Rejected values were not retained for correction');
    $assert(!str_contains($response['body'], 'toastNotification('), 'Invalid setup displayed saved notification');
    $assert(file_get_contents($directory . '/settings.json') === $saved, 'Invalid save changed stored settings');
    $fields['video_scene_timeout'] = '600';
    $assert($request($fields)['status'] === 200, 'Corrected setup could not be saved');
    $assert($request(null, '?save=1')['status'] === 405, 'GET save was accepted');
    $assert($request(null, '?denied=1')['status'] === 403, 'Denied setup access was accepted');

    $token = $request(null, '?token=1', 'description')['body'];
    $post = ['ref' => '10', 'scene' => '7', 'description' => '<script>test</script>' . "\nEdited scene",
        'CSRFToken' => $token];
    $assert($request(null, '', 'description')['status'] === 405, 'GET description update accepted');
    foreach (['', 'invalid'] as $invalid_token) {
        $invalid = $post;
        $invalid['CSRFToken'] = $invalid_token;
        $assert($request($invalid, '', 'description')['status'] === 400, 'Invalid CSRF accepted');
    }
    $assert(!is_file($directory . '/scene.json'), 'Invalid request changed description');
    $response = $request($post, '', 'description');
    $assert($response['status'] === 200, 'Editor could not save description');
    $assert(json_decode($response['body'], true)['description'] === $post['description'], 'Response changed text');
    $saved = file_get_contents($directory . '/scene.json');
    $log = json_decode(file_get_contents($directory . '/scene-log.json'), true);
    $assert($log[0] === 10 && $log[4] === 'Original' && $log[5] === $post['description'], 'Audit log missing');
    foreach (['denied', 'inaccessible', 'preview_denied', 'disabled'] as $denial) {
        $assert($request($post, '?' . $denial . '=1', 'description')['status'] === 403, 'Denied edit accepted');
    }
    $invalid = $post;
    $invalid['k'] = 'external-share';
    $assert($request($invalid, '', 'description')['status'] === 403, 'External share edit accepted');
    foreach (['ref' => '11', 'scene' => '8'] as $field => $value) {
        $invalid = $post;
        $invalid[$field] = $value;
        $assert($request($invalid, '', 'description')['status'] === 404, 'Wrong owning resource or scene accepted');
    }
    foreach ([str_repeat('x', 16001), [1], "\xFF"] as $description) {
        $invalid = $post;
        $invalid['description'] = $description;
        $assert($request($invalid, '', 'description')['status'] === 400, 'Invalid description accepted');
    }
    $assert(file_get_contents($directory . '/scene.json') === $saved, 'Rejected request changed description');
    $post['description'] = '';
    $assert($request($post, '', 'description')['status'] === 200, 'Clearing description failed');
    $assert(json_decode(file_get_contents($directory . '/scene.json'), true) === '', 'Description was not cleared');
    echo "Video scenes setup and description HTTP checks passed.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        fclose($pipes[0]);
        proc_close($server);
    }
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

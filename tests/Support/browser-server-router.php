<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || getenv('RS_TEST_MODE') !== '1') {
    return false;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (is_string($path) && str_starts_with($path, '/iiif/')) {
    chdir(dirname(__DIR__, 2) . '/api/iiif');
    require 'handler.php';
    return true;
}

// Other application and asset requests retain the built-in server's normal handling.
if ($path !== '/__rs_browser_ready') {
    return false;
}

$token = getenv('RS_BROWSER_SERVER_TOKEN');
if ($token === false || $token === '') {
    http_response_code(503);
    return;
}

error_log('Browser server readiness probe accessed');

header('Content-Type: text/plain');
header('Cache-Control: no-store');
echo $token;

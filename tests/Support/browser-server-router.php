<?php

declare(strict_types=1);

// All application and asset requests retain the built-in server's normal handling.
if (
    PHP_SAPI !== 'cli-server'
    || getenv('RS_TEST_MODE') !== '1'
    || ($_SERVER['REQUEST_URI'] ?? '') !== '/__rs_browser_ready'
) {
    return false;
}

$token = getenv('RS_BROWSER_SERVER_TOKEN');
if ($token === false || $token === '') {
    http_response_code(503);
    return;
}

error_log("Browser server readiness probe accessed (run token: '{$token}')");

header('Content-Type: text/plain');
header('Cache-Control: no-store');
echo $token;

<?php

declare(strict_types=1);

// A successful HTTP response alone can't identify the server owned by this run.
if (PHP_SAPI !== 'cli' || empty($argv[1]) || !extension_loaded('curl')) {
    exit(1);
}

$request = curl_init('http://127.0.0.1:8081/__rs_browser_ready');
curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT_MS => 250,
    CURLOPT_TIMEOUT_MS => 500,
    CURLOPT_PROXY => '',
]);

$response = curl_exec($request);
$status = curl_getinfo($request, CURLINFO_HTTP_CODE);

exit($status === 200 && $response === $argv[1] ? 0 : 1);

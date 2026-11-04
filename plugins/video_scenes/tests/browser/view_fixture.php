<?php

/** Render the actual view hook with isolated permission and scene fixtures. */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require __DIR__ . '/functions.php';
set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$ref = 10;
$resource = ['ref' => $ref, 'resource_type' => 3];
$context = $argv[1] ?? 'View';
$display = '';
$baseurl_short = 'http://scene.test/';
$k = isset($argv[5]) && $argv[5] !== '' ? '' : 'test-share-key';
include dirname(__DIR__, 4) . '/languages/en.php';
include dirname(__DIR__, 2) . '/languages/en.php';

include dirname(__DIR__, 2) . '/hooks/view.php';
HookVideo_scenesViewCustompanels();

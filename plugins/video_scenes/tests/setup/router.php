<?php

// Isolated HTTP fixture: real core form rendering/saving, no installation bootstrap or database.
if (PHP_SAPI !== 'cli-server' || getenv('VIDEO_SCENES_SETUP_TEST_DIR') === false) {
    http_response_code(404);
    exit();
}
$root = dirname(__DIR__, 4);
require $root . '/include/definitions.php';
require $root . '/include/config.default.php';
require $root . '/include/general_functions.php';
require $root . '/include/encryption_functions.php';
require $root . '/include/config_functions.php';
require $root . '/include/plugin_functions.php';
require $root . '/include/render_functions.php';
require __DIR__ . '/functions.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/languages/en.php';

set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$lang += [
    'systemsetup' => 'System setup', 'pluginmanager' => 'Plugins', 'saved' => 'Saved',
    'plugins-configvar' => '%cvn', 'false-true' => ['No', 'Yes'],
    'plugins-saveconfig' => 'Save configuration', 'plugins-saveandexit' => 'Save and exit',
    'error-permissiondenied' => 'Permission denied',
    'error-csrf-verification-failed' => 'CSRF verification failed',
    'error-method-not_allowed' => 'Method not allowed',
    'error-405-method-not_allowed' => 'Use POST',
];
$baseurl_short = '/';
$plugins = ['video_scenes'];
$pagename = 'setup';
$CSRF_enabled = false;
$mysql_charset = 'utf8mb4';
$test_directory = getenv('VIDEO_SCENES_SETUP_TEST_DIR');
if (str_starts_with($_SERVER['REQUEST_URI'], '/description')) {
    $CSRF_enabled = true;
    $usersession = 'isolated-scene-editor-session';
    $pagename = 'edit_description';
    if (getval('token', '') === '1') {
        echo generateCSRFToken($usersession, 'video_scene_description');
        exit();
    }
    if (getval('disabled', '') === '1') {
        $plugins = [];
    }
    chdir($test_directory . '/plugins/video_scenes/pages');
    include 'edit_description.php';
    exit();
}
if (is_file($test_directory . '/settings.json')) {
    foreach (json_decode(file_get_contents($test_directory . '/settings.json'), true) as $name => $value) {
        $GLOBALS[$name] = $value;
    }
}
chdir($test_directory . '/plugins/video_scenes/pages');
include 'setup.php';

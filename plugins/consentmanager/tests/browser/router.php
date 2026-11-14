<?php

/** Local-only browser test router. A separate test database and temporary storage are mandatory. */

$fixture_path = getenv('RS_CONSENT_FIXTURE');
if (PHP_SAPI !== 'cli-server' || !$fixture_path || !str_starts_with($fixture_path, '/tmp/rs-consent-browser-')) {
    http_response_code(403);
    exit;
}
$fixture = json_decode(file_get_contents($fixture_path), true, 16, JSON_THROW_ON_ERROR);
$root = dirname(__DIR__, 4);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_ends_with($path, '.php')) {
    $allowed = ['list', 'requests', 'templates', 'template_edit', 'template_delete', 'recipient_preview', 'consent'];
    if (
        !preg_match('#\A/plugins/consentmanager/pages/([a-z_]+)\.php\z#', $path, $match)
        || !in_array($match[1], $allowed, true)
    ) {
        http_response_code(403);
        exit;
    }
    define('RS_TEST_MODE', 1);
    $rs_test_mysql_db = 'rs_test_db';
    $disable_browser_check = true;
    $_SERVER['HTTPS'] = 'on';
    // Validate the browser's real origin before remapping this isolated server's configuration.
    // In particular, Origin: null must not be hidden from the test by the bootstrap remapping.
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== getenv('RS_CONSENT_ORIGIN')) {
        http_response_code(403);
        exit;
    }
    unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    include_once $root . '/include/boot.php';
    if ($mysql_db !== 'rs_test_db') {
        throw new RuntimeException('Test database isolation failed');
    }
    $baseurl = getenv('RS_CONSENT_ORIGIN');
    $baseurl_short = '/';
    $storagedir = $fixture['storage'];
    $tempdir = $storagedir;
    $browser_check = false;
    $consentmanager_external_enabled = true;
    $ffmpeg_preview_extension = 'mp4';
    include $root . $path;
    exit;
}
if (preg_match('#\A/(css|js|gfx|lib|plugins/consentmanager/(css|js))/#', $path)) {
    return false;
}
http_response_code(404);

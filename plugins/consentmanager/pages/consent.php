<?php

include_once __DIR__ . '/../../../include/boot.php';

// This capability page deliberately does not authenticate the recipient as any ResourceSpace user.
header('Cache-Control: no-store, private');
header('Referrer-Policy: strict-origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
    . "script-src 'self'; frame-src 'self'; object-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");

if (!function_exists('consentmanager_external_enabled') || !consentmanager_external_enabled()) {
    http_response_code(404);
    exit;
}

$token = getval('token', '');
if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') && (int) ($_SERVER['SERVER_PORT'] ?? 0) !== 443) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(403);
        exit;
    }
    redirect(generateURL(consentmanager_external_portal_url(), $_GET));
}
$error = '';
$declined = false;
// A separate cookie binds CSRF to this browser without a ResourceSpace login or session.
$usersession = $_COOKIE['cm_external_browser'] ?? '';
if (!is_string($usersession) || !preg_match('/\A[a-f0-9]{64}\z/D', $usersession)) {
    $usersession = bin2hex(random_bytes(32));
    setcookie('cm_external_browser', $usersession, [
        'expires' => 0,
        'path' => $baseurl_short . 'plugins/consentmanager/pages/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
$CSRF_enabled = true;
try {
    $request = consentmanager_external_resolve($token);
    $action = getval('action', '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        enforcePostRequest(false);
        if (!rs_validate_token(getval($CSRF_token_identifier, ''), $usersession)) {
            http_response_code(403);
            throw new RuntimeException('cm_external_csrf');
        }
        switch ($action) {
            case 'form':
                consentmanager_external_prepare_review($token, $_POST);
                break;
            case 'sign':
                consentmanager_external_sign($token, $_POST);
                break;
            case 'submit':
                if (getval('approve_signed', '') !== 'yes') {
                    throw new InvalidArgumentException('cm_external_invalid');
                }
                consentmanager_external_submit($token, (int) getval('revision', 0), getval('hash', ''));
                break;
            case 'correct':
                consentmanager_external_visit($token, true);
                break;
            case 'decline':
                consentmanager_external_decline($token);
                $declined = true;
                break;
            default:
                throw new InvalidArgumentException('cm_external_invalid');
        }
        if (!$declined) {
            // POST/redirect/GET prevents accidental repeated form revisions on refresh.
            redirect(generateURL(consentmanager_external_portal_url(), ['token' => $token]));
        }
    } elseif ($action === 'pdf') {
        $bytes = consentmanager_external_document($token, getval('signed', '') === '1', (int) getval('revision', 0));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="consent.pdf"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    } elseif ($action === 'media') {
        $media = consentmanager_external_media($request, (int) getval('resource', 0), getval('original', '') === '1');
        if (!$media) {
            http_response_code(404);
            exit;
        }
        consentmanager_external_stream($media);
    } else {
        consentmanager_external_visit($token);
        $request = consentmanager_external_resolve($token);
    }
} catch (Throwable $e) {
    $error = array_key_exists($e->getMessage(), $lang) ? $lang[$e->getMessage()] : $lang['cm_external_failed'];
    if (!isset($request)) {
        http_response_code(404);
    }
}

consentmanager_external_render_portal($request ?? null, $token, $error, $declined);

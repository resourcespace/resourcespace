<?php

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: strict-origin');
header('X-Content-Type-Options: nosniff');

try {
    $request = consentmanager_external_manage_request((int) getval('request', 0));
    $preview_response = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && enforcePostRequest(false)) {
        if (getval('action', '') === 'form') {
            $preview_response = consentmanager_external_prepare_preview($request, $_POST);
            $request['status'] = 'signed';
        } elseif (getval('action', '') === 'correct') {
            $preview_response = consentmanager_external_preview_corrections($request, getval('preview_responses', ''));
            $request['status'] = 'viewed';
        } else {
            throw new RuntimeException('cm_external_denied');
        }
    }

    if (getval('action', '') === 'pdf') {
        $bytes = consentmanager_external_preview_document(
            $request['ref'],
            getval('signed', '') === '1',
            (int) getval('revision', 0)
        );
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="consent-preview.pdf"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }
    if (getval('action', '') === 'media') {
        $media = consentmanager_external_media($request, (int) getval('resource', 0), getval('original', '') === '1');
        if (!$media) {
            http_response_code(404);
            exit;
        }
        consentmanager_external_stream($media);
    }
    $preview = true;
    $token = '';
    $error = '';
    $declined = false;
    ob_start();
    consentmanager_external_render_portal($request, '', '', false, true, $preview_response);
    ob_end_flush();
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($e instanceof InvalidArgumentException ? 400 : 403);
    }
    debug('Consent Manager: recipient preview failed at ' . $e->getFile() . ':' . $e->getLine());
    $error = $lang[$e->getMessage()] ?? $lang['cm_external_failed'];
    if (isset($request)) {
        $request['status'] = 'viewed';
        consentmanager_external_render_portal($request, '', $error, false, true);
        exit;
    }
    consentmanager_external_render_portal(null, '', $error, false, true);
}

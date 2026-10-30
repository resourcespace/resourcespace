<?php

// This endpoint authenticates with a single-use code and PKCE, rather than browser cookies.
$suppress_headers = true;
$disable_browser_check = true;
include '../../include/boot.php';
include_once '../../include/api_functions.php';
include_once '../../include/user_api_session_functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

if (!enforcePostRequest(false)) {
    header('Allow: POST');
    echo json_encode(['error' => 'invalid_request']);
    exit();
}

if (!$enable_remote_apis) {
    http_response_code(403);
    echo json_encode(['error' => 'access_denied']);
    exit();
}

if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
    http_response_code(415);
    echo json_encode(['error' => 'invalid_request']);
    exit();
}

// Credentials must be in the POST body; never accept query-string or cookie fallbacks.
$params = [];
foreach (['system', 'code', 'code_verifier', 'redirect_uri'] as $key) {
    if (!isset($_POST[$key]) || !is_string($_POST[$key]) || $_POST[$key] === '') {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request']);
        exit();
    }
    $params[$key] = $_POST[$key];
}

$response = redeem_api_session_code(
    $params['system'],
    $params['code'],
    $params['code_verifier'],
    $params['redirect_uri']
);
if ($response === false) {
    http_response_code(400);
    $response = ['error' => 'invalid_grant'];
}
echo json_encode($response);

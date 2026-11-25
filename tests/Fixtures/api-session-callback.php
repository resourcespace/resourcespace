<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || getenv('RS_TEST_MODE') !== '1') {
    http_response_code(404);
    return;
}

$id = $_GET['fixture'] ?? '';
if (!is_string($id) || strlen($id) !== 24 || !ctype_xdigit($id)) {
    http_response_code(400);
    return;
}

$record = ['path' => $_SERVER['REQUEST_URI'], 'post' => $_POST];
if (isset($record['post']['sessionkey'])) {
    $record['post']['sessionkey'] = hash('sha256', $record['post']['sessionkey']);
}
$path = sys_get_temp_dir() . '/pest-callback-' . $id . '/received.json';
if (file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR)) === false) {
    http_response_code(500);
    return;
}
header('Content-Type: application/json');
echo json_encode(['message' => 'Callback received']);

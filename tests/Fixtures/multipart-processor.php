<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || getenv('RS_TEST_MODE') !== '1') {
    http_response_code(404);
    return;
}

$disable_browser_check = true;
require dirname(__DIR__) . '/Support/bootstrap-resourcespace-test-env.php';

$id = $_POST['fixture'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($id) || !ctype_xdigit($id) || !isset($_FILES['file'])) {
    http_response_code(400);
    return;
}

$destination = new SplFileInfo(get_temp_dir(false) . '/pest_multipart_' . $id . '.bin');
$processor = match ($_POST['mode'] ?? '') {
    'csv' => ['mime_file_based_detection' => false, 'allow_extensions' => ['csv']],
    'dry_run' => ['file_move' => 'dry_run'],
    default => [],
};
$result = process_file_upload($_FILES['file'], $destination, $processor);

header('Content-Type: application/json');
echo json_encode(['success' => $result['success'], 'error' => ($result['error'] ?? null)?->name]);

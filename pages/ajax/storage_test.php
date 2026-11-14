<?php

include '../../include/boot.php';
include '../../include/authenticate.php';
include_once '../../include/storage_test_functions.php';

enforcePostRequest(true);
header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    // CSRF is validated by authenticate.php before any file operations.
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $sequence = $_POST['sequence'] ?? '';
    if (
        !is_string($action) || !is_string($id) || !is_scalar($sequence)
        || !preg_match('/\A[0-9]{1,9}\z/D', (string) $sequence)
    ) {
        throw new RuntimeException('storage_test_invalid');
    }
    echo json_encode(storage_test_request($action, $id, (int) $sequence));
} catch (Throwable $e) {
    $key = $e->getMessage();
    $known = ['storage_test_denied', 'storage_test_invalid', 'storage_test_busy',
        'storage_test_root_error', 'storage_test_space', 'storage_test_io_error', 'storage_test_unsafe'];
    if (!in_array($key, $known, true)) {
        debug('Storage performance test: request failed (' . get_class($e) . ').');
        $key = 'storage_test_io_error';
    }
    http_response_code($key === 'storage_test_denied' ? 403 : ($key === 'storage_test_busy' ? 409 : 400));
    echo json_encode(['error' => $lang[$key]]);
}

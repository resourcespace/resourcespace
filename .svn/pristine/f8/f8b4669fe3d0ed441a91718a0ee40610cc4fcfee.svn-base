<?php
include '../include/boot.php';
include '../include/authenticate.php';

if (!job_trigger_permission_check()) {
    error_alert($lang["error-permissiondenied"], false, 401);
    exit();
}

$job = getval("job", 0, false, "is_int_loose");

$log_path = get_job_queue_log_path_by_ref($job);

if (!is_file($log_path) || !is_readable($log_path)) {
    http_response_code(404);
    exit($lang["downloadfile_nofile"]);
}

// Set headers
header('Content-Description: File Transfer');
header('Content-Type: text/plain'); 
header('Content-Disposition: attachment; filename="' . basename($log_path ) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($log_path ));

ob_clean();
flush();

readfile($log_path );
exit;
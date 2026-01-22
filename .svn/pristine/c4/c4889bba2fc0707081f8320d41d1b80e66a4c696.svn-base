<?php

include '../../include/boot.php';
include '../../include/authenticate.php';

$job        = getval("job", 0, false, "is_int_loose");
$type       = getval("type", "stream");

$log_path    = get_job_queue_log_path_by_ref($job);
$job_details = job_queue_get_job($job);

if (empty($job_details)) {
    header('HTTP/1.1 404 Not found');
    $return['error'] = array(
        'status' => 404,
        'title'  => 'Not found',
        'detail' => 'Job not found');

    echo json_encode($return);
    exit();
}

if (!job_trigger_permission_check() && !($type === "progress" && $job_details['user'] == $userref)) {
    header('HTTP/1.1 401 Unauthorized');
    $return['error'] = array(
        'status' => 401,
        'title'  => 'Unauthorized',
        'detail' => $lang['error-permissiondenied']);

    echo json_encode($return);
    exit();
}


if ($type == "progress") {

    // Check the status of the job
    if ($job_details['status'] == STATUS_ACTIVE && !is_file($log_path) || !is_readable($log_path)) {
        // Log file may not exist yet
        header('Content-Type: application/json');
        echo json_encode(['last_line' => escape($lang["jobs_action_not_started"])]);
        exit;
    }

    $progress = get_job_progress_from_log_file($log_path, "/\[PROGRESS\]/i");
    $progress['percentage'] = null;
    $progress['time'] = null;

    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $progress['found'] ?? $progress['last_line'] ?? null, $matches)) {
        $progress['time'] = $matches[1];
    }

    if ($progress['found'] !== null && preg_match('/(\d+)%/', $progress['found'], $matches)) {
        $progress['percentage'] = (int) $matches[1];
    }
    
    header('Content-Type: application/json');
    echo json_encode($progress);
    exit;

} elseif ($type == "stream") {

    // Check access to log file, otherwise we can't stream it
    if (!is_file($log_path) || !is_readable($log_path)) {
        header('HTTP/1.1 400 Bad Request');
        $return['error'] = array(
            'status' => 400,
            'title'  => 'Bad Request',
            'detail' => 'Invalid log file.');

        echo json_encode($return);
        exit();
    }

    $job_status = isset($lang["job_status_" . $job_details['status']]) ? $lang["job_status_" . $job_details['status']] : $job_details['status'];
    $job_done = ($job_details['status'] !== 3);

    $offset       = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $backlog      = isset($_GET['lines'])  ? max(0, (int) $_GET['lines'])  : 0;
    $client_inode = isset($_GET['inode'])  ? (int) $_GET['inode'] : 0;
    $carry        = isset($_GET['carry'])  ? (string) $_GET['carry'] : '';

    $backlog = min($backlog, 500);

    $lines    = [];
    $newCarry = '';
    $rotated  = false;

    function tail_last_lines(string $path, int $lines = 50, int $chunk = 4096): array 
    {
        if ($lines <= 0) return [];
        $f = fopen($path, 'rb');
        if (!$f) return [];
        fseek($f, 0, SEEK_END);
        $pos     = ftell($f);
        $buffer  = '';
        $lineCnt = 0;

        while ($pos > 0 && $lineCnt <= $lines) {
            $seek = max($pos - $chunk, 0);
            $read = $pos - $seek;
            fseek($f, $seek);
            $buffer = fread($f, $read) . $buffer;
            $pos    = $seek;
            $lineCnt = substr_count($buffer, "\n");
            if ($seek === 0) break;
        }
        fclose($f);

        $all = explode("\n", rtrim($buffer, "\n"));
        $sliced_lines = array_slice($all, -$lines);

        $processed_lines = [];

        foreach ($sliced_lines as $line) {
            $processed_lines[] = ["text" => $line, "type" => process_line($line)];
        }

        return $processed_lines;

    }

    function process_line(string $line): string
    {
        $type = "";

        // Process line for tags - type tags will always be first
        if (preg_match('/\[([A-Z_]+)\]/', $line, $matches)) {
            $type = strtolower($matches[1]);
        }

        return $type;
    }


    // -------- core logic ---------------------------------------------------------
    $fp = fopen($log_path, 'rb');
    if (!$fp) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cannot open log file']);
        exit;
    }

    $st = fstat($fp);
    $size  = (int) ($st['size'] ?? 0);
    $inode = (int) ($st['ino']  ?? 0);

    // Detect rename + newfile rotation by inode change
    if ($client_inode !== 0 && $client_inode !== $inode) {
        $offset  = 0;
        $rotated = true;
    }

    // Detect truncation/copytruncate by offset past EOF
    if ($offset > $size) {
        $offset  = 0;
        $rotated = true;
    }

    $newOffset = $offset;

    // Initial load with backlog (like tail)
    if ($offset === 0 && $backlog > 0) {
        fclose($fp);

        $lines = tail_last_lines($log_path, $backlog);

        // Re-stat after tail to set offset to current EOF
        clearstatcache(true, $log_path);
        $newOffset = (int) filesize($log_path);
        $inode = (int) fileinode($log_path);
        $newCarry = '';
    } else {
        // Read from offset to EOF
        fseek($fp, $offset);
        $toRead = min(max(0, $size - $offset), 256 * 1024);
        $chunk  = $toRead > 0 ? fread($fp, $toRead) : '';
        $rawEnd = ftell($fp); // where we *would* be if we consumed all bytes

        fclose($fp);

        if ($chunk !== '') {
            $chunk = str_replace(["\r\n", "\r"], "\n", $chunk);

            // Only process complete lines. Keep partial tail by NOT advancing past it.
            $lastNl = strrpos($chunk, "\n");

            if ($lastNl === false) {
                // No newline at all in this chunk => line is longer than MAX_READ_BYTES.
                // Safety: don't emit it; also don't advance offset (or you can advance a little).
                $newOffset = $offset;   // try again next poll
                $lines = [];
            } else {
                $complete = substr($chunk, 0, $lastNl); // excludes the last \n
                $parts = explode("\n", $complete);

                foreach ($parts as $line) {
                    if ($line === '') continue;
                    $lines[] = ["text" => $line, "type" => process_line($line)];
                }

                // Advance offset only through the newline we processed
                $newOffset = $offset + $lastNl + 1;
            }
        } else {
            $newOffset = $offset;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'offset'  => $newOffset,
        'inode'   => $inode,
        'rotated' => $rotated,
        'lines'   => $lines,
        'status'  => $job_status,
        'done'    => $job_done,
    ]);
} else {
    header('HTTP/1.1 400 Bad Request');
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'Invalid type');

    echo json_encode($return);
    exit();
}
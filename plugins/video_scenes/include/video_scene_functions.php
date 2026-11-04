<?php

/**
 * Validate setup values before saving them or starting a worker.
 *
 * @param array $settings Plugin configuration keyed by variable name.
 * @return bool Whether all settings have safe, usable types and ranges.
 */
function video_scene_valid_config(array $settings): bool
{
    global $video_scene_python;

    foreach (['threshold', 'api_key', 'model', 'timeout', 'audio', 'prompt', 'audio_overlap'] as $name) {
        $value = $settings['video_scene_' . $name] ?? null;
        if (!is_scalar($value)) {
            return false;
        }
    }
    if (
        array_key_exists('video_scene_python', $settings)
        && $settings['video_scene_python'] !== $video_scene_python
    ) {
        return false;
    }
    $python = (string) $video_scene_python;
    return is_file($python)
        && is_executable($python)
        && realpath($python) !== false
        && ($python[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $python))
        && is_numeric($settings['video_scene_threshold'])
        && $settings['video_scene_threshold'] > 0
        && $settings['video_scene_threshold'] < 1
        && filter_var($settings['video_scene_timeout'], FILTER_VALIDATE_INT) !== false
        && $settings['video_scene_timeout'] >= 60
        && $settings['video_scene_timeout'] <= 86400
        && preg_match('/^[a-zA-Z0-9._-]{1,100}$/D', $settings['video_scene_model'])
        && strlen($settings['video_scene_api_key']) <= 512
        && !preg_match('/[\x00-\x20\x7f]/', $settings['video_scene_api_key'])
        && trim($settings['video_scene_prompt']) !== ''
        && strlen($settings['video_scene_prompt']) <= 8000
        && filter_var($settings['video_scene_audio_overlap'], FILTER_VALIDATE_INT) !== false
        && $settings['video_scene_audio_overlap'] >= 0
        && $settings['video_scene_audio_overlap'] <= 30
        && in_array((string) $settings['video_scene_audio'], ['', '0', '1'], true);
}

/**
 * Identify a readable original video using ResourceSpace's configured video extensions.
 *
 * @param int $ref Resource reference.
 * @return array Empty when absent/unsupported, otherwise resource, path and source signature.
 */
function video_scene_source(int $ref): array
{
    global $ffmpeg_supported_extensions, $ffmpeg_audio_extensions;

    $resource = $ref > 0 ? get_resource_data($ref, false) : false;
    if (
        !$resource
        || !in_array(strtolower((string) $resource['file_extension']), $ffmpeg_supported_extensions, true)
        || in_array(strtolower((string) $resource['file_extension']), $ffmpeg_audio_extensions, true)
    ) {
        return [];
    }
    // Recheck staticsync paths after a replacement, including within a long-running worker.
    unset($GLOBALS['get_resource_path_fpcache'][$ref]);
    $path = get_resource_path($ref, true, '', false, $resource['file_extension']);
    clearstatcache(true, $path);
    $stat = is_readable($path) && is_file($path) ? stat($path) : false;
    if ($stat === false) {
        return [];
    }
    $signature = hash('sha256', json_encode([
        $path, $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime'],
        $resource['file_checksum'] ?? '',
    ]));
    return ['resource' => $resource, 'path' => $path, 'signature' => $signature];
}

/**
 * Queue one scene job per original version, suppressing queued/running duplicates.
 *
 * @param int $ref Resource reference, supplied by a successful upload/preview hook.
 * @return void
 */
function video_scene_queue(int $ref): void
{
    global $lang, $userref;

    $source = video_scene_source($ref);
    if ($source === []) {
        return;
    }
    $code = 'video_scene_' . $ref . '_' . $source['signature'];
    foreach (job_queue_get_jobs('video_scene', -1, '', $code) as $job) {
        if (in_array((int) $job['status'], [STATUS_ACTIVE, STATUS_INPROGRESS], true)) {
            return;
        }
    }
    job_queue_add(
        'video_scene',
        ['resource' => $ref, 'signature' => $source['signature']],
        (int) ($userref ?? 0) > 0 ? $userref : (int) $source['resource']['created_by'],
        '',
        $lang['video_scene_job_success'],
        $lang['video_scene_job_failure'],
        $code
    );
}

/**
 * Queue scene analysis after original video preview creation.
 *
 * @param int $ref Resource reference.
 * @param int $alternative Alternative reference; -1 represents the original.
 * @return void
 */
function video_scene_on_preview_creation(int $ref, int $alternative): void
{
    if ($alternative === -1) {
        video_scene_queue($ref);
    }
}

/**
 * Clear old scenes and queue analysis after a successful upload.
 *
 * @param int $resource_ref Uploaded resource reference.
 * @return void
 */
function video_scene_on_upload(int $resource_ref): void
{
    $resource = get_resource_data($resource_ref, false);
    if ($resource) {
        video_scene_remove_scene_files($resource_ref, $resource['file_extension']);
    }
    ps_query('DELETE FROM resource_scene WHERE resource = ?', ['i', $resource_ref]);
    video_scene_queue($resource_ref);
}

/**
 * Remove scene data when a resource is permanently deleted.
 *
 * @param int $resource_ref Resource reference.
 * @return void
 */
function video_scene_delete_resource(int $resource_ref, array $resource = []): void
{
    $resource = $resource ?: (get_resource_data($resource_ref, false) ?: []);
    if (isset($resource['file_extension'])) {
        video_scene_remove_scene_files($resource_ref, $resource['file_extension']);
    }
    ps_query('DELETE FROM resource_scene WHERE resource = ?', ['i', $resource_ref]);
}

/**
 * Remove files belonging to stored scene rows.
 *
 * @param int    $resource_ref Resource reference.
 * @param string $extension    Original resource file extension.
 * @return void
 */
function video_scene_remove_scene_files(int $resource_ref, string $extension): void
{
    $rows = ps_query('SELECT ref FROM resource_scene WHERE resource = ?', ['i', $resource_ref]);
    $original_path = get_resource_path($resource_ref, true, '', false, $extension);
    $directory = dirname($original_path);
    foreach ($rows as $row) {
        $path = $directory . '/scene_' . (int) $row['ref'] . '.jpg';
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * Serve a stored scene thumbnail after applying resource and share permissions.
 *
 * @return void
 */
function video_scene_serve_thumbnail(): void
{
    global $plugins, $pagename, $access;

    $ref = (int) getval('ref', 0, true);
    $scene = (int) getval('scene', 0, true);
    if (!in_array('video_scenes', $plugins, true)) {
        http_response_code(404);
        exit();
    }
    $resource = $ref > 0 ? get_resource_data($ref) : false;
    $internal_share_access = internal_share_access();
    $access = $resource ? get_resource_access($resource) : RESOURCE_ACCESS_INVALID_REQUEST;
    $pagename = 'view';
    if (!$resource || $scene <= 0 || !video_scene_can_view($resource) || check_use_watermark('', $ref)) {
        http_response_code(403);
        exit();
    }
    $rows = ps_query(
        'SELECT ref FROM resource_scene WHERE ref = ? AND resource = ?',
        ['i', $scene, 'i', $ref]
    );
    if ($rows === []) {
        http_response_code(404);
        exit();
    }
    $source = video_scene_source($ref);
    if ($source === []) {
        http_response_code(404);
        exit();
    }
    $thumbnail_path = dirname($source['path']) . '/scene_' . $scene . '.jpg';
    $thumbnail_directory = realpath(dirname($thumbnail_path));
    $resolved_thumbnail = realpath($thumbnail_path);
    if (
        $thumbnail_directory === false
        || $resolved_thumbnail === false
        || dirname($resolved_thumbnail) !== $thumbnail_directory
        || !is_file($resolved_thumbnail)
        || !is_readable($resolved_thumbnail)
        || filesize($resolved_thumbnail) > 65536
    ) {
        http_response_code(404);
        exit();
    }
    header('Content-Type: image/jpeg');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . filesize($resolved_thumbnail));
    readfile($resolved_thumbnail);
}

/**
 * Validate and save a scene description submitted by an authorised resource editor.
 *
 * @return void Emits a JSON response and appropriate HTTP status.
 */
function video_scene_update_description_endpoint(): void
{
    global $lang, $plugins, $CSRF_token_identifier, $usersession;

    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (!enforcePostRequest(true)) {
        exit();
    }
    if (!isValidCSRFToken(getval($CSRF_token_identifier, ''), $usersession)) {
        http_response_code(400);
        exit(json_encode(['error' => $lang['error-csrf-verification-failed']]));
    }

    $ref = filter_var($_POST['ref'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $scene = filter_var($_POST['scene'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $resource = $ref ? get_resource_data($ref) : false;
    if (!in_array('video_scenes', $plugins, true) || !$resource || !video_scene_can_edit($resource)) {
        http_response_code(403);
        exit(json_encode(['error' => $lang['error-permissiondenied']]));
    }

    $description = $_POST['description'] ?? null;
    if (!$scene || !is_string($description) || strlen($description) > 16000 || preg_match('//u', $description) !== 1) {
        http_response_code(400);
        exit(json_encode(['error' => $lang['video_scene_invalid_description']]));
    }
    $rows = ps_query(
        'SELECT description, start_time FROM resource_scene WHERE ref = ? AND resource = ?',
        ['i', $scene, 'i', $ref]
    );
    if ($rows === []) {
        http_response_code(404);
        exit(json_encode(['error' => $lang['video_scene_description_missing']]));
    }
    if ($description !== (string) $rows[0]['description']) {
        ps_query(
            'UPDATE resource_scene SET description = ? WHERE ref = ? AND resource = ?',
            ['s', $description, 'i', $scene, 'i', $ref]
        );
        resource_log(
            $ref,
            '',
            0,
            $lang['video_scene_description'] . ' (' . video_scene_timestamp((float) $rows[0]['start_time']) . ')',
            (string) $rows[0]['description'],
            $description
        );
    }
    echo json_encode(['description' => $description]);
}

/**
 * Check scene visibility using resource and video preview permissions.
 *
 * @param array $resource Resource data; current share context is honoured by the access helpers.
 * @return bool Whether the current viewer may see the video scene table.
 */
function video_scene_can_view(array $resource): bool
{
    return in_array(get_resource_access($resource), [RESOURCE_ACCESS_FULL, RESOURCE_ACCESS_RESTRICTED], true)
        && !resource_has_access_denied_by_RT_size((int) $resource['resource_type'], 'pre');
}

/**
 * Check resource edit access for scene descriptions, excluding external-share views.
 *
 * @param array $resource Resource data for the scene's owning resource.
 * @return bool Whether the current viewer may edit its scene descriptions.
 */
function video_scene_can_edit(array $resource): bool
{
    global $k;

    return empty($k) && video_scene_can_view($resource)
        && get_edit_access((int) $resource['ref'], -999, $resource);
}

/**
 * Format a timestamp without wrapping after 24 hours.
 *
 * @param float $seconds Seconds from the start of the video.
 * @return string HH:MM:SS, with hours allowed to exceed two digits.
 */
function video_scene_timestamp(float $seconds): string
{
    $seconds = (int) floor(max(0, $seconds));
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

/**
 * Run the local Python pipeline, passing credentials only over stdin.
 *
 * @param array  $request Worker configuration, including original path and optional API key.
 * @param string $dir     Private temporary directory for generated images and diagnostic files.
 * @param mixed  $job_log Writable offline job log resource, when available.
 * @return array Validated JSON result. Throws RuntimeException on worker failure or timeout.
 */
function video_scene_worker(array $request, string $dir, $job_log = null): array
{
    global $video_scene_python, $video_scene_timeout;

    $process = proc_open(
        [$video_scene_python, '-B', __DIR__ . '/../scripts/process.py'],
        [0 => ['pipe', 'r'], 1 => ['file', $dir . '/result.json', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Python worker.');
    }
    $error_pipe = $pipes[2];
    stream_set_blocking($error_pipe, false);
    $error_log = fopen($dir . '/worker.log', 'wb');
    $pending_log_line = '';
    video_scene_log('Starting Python scene worker.', $job_log);
    try {
        $input = json_encode($request, JSON_THROW_ON_ERROR);
        $written = 0;
        while ($written < strlen($input)) {
            $bytes = fwrite($pipes[0], substr($input, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Unable to send worker configuration.');
            }
            $written += $bytes;
        }
        fclose($pipes[0]);
        $deadline = microtime(true) + (int) $video_scene_timeout;
        do {
            $worker_output = stream_get_contents($error_pipe);
            if ($worker_output !== false && $worker_output !== '') {
                fwrite($error_log, $worker_output);
                $pending_log_line .= $worker_output;
                while (($newline = strpos($pending_log_line, "\n")) !== false) {
                    $line = trim(substr($pending_log_line, 0, $newline));
                    $pending_log_line = substr($pending_log_line, $newline + 1);
                    if ($line !== '') {
                        video_scene_log($line, $job_log);
                    }
                }
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Video scene processing timed out.');
            }
            usleep(200000);
        } while (true);
    } finally {
        $worker_output = stream_get_contents($error_pipe);
        if ($worker_output !== false && $worker_output !== '') {
            fwrite($error_log, $worker_output);
            $pending_log_line .= $worker_output;
        }
        if (trim($pending_log_line) !== '') {
            video_scene_log(trim($pending_log_line), $job_log);
        }
        if (is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        fclose($error_pipe);
        fclose($error_log);
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }
        proc_close($process);
    }
    $result = json_decode(file_get_contents($dir . '/result.json'), true);
    if (is_array($result) && is_string($result['error'] ?? null)) {
        throw new RuntimeException('Video scene worker: ' . $result['error']);
    }
    if (!is_array($result) || !isset($result['scenes']) || !is_array($result['scenes'])) {
        // Do not copy third-party stderr or API responses into application logs.
        throw new RuntimeException(
            'Python worker failed. Check transnetv2-pytorch installation and the configured Python executable.'
        );
    }
    return $result;
}

/**
 * Write a progress message to the active offline job log when available.
 *
 * @param string $message Progress detail from the scene worker.
 * @param mixed  $job_log Writable offline job log resource, when available.
 * @return void
 */
function video_scene_log(string $message, $job_log = null): void
{
    global $log_file;

    if (!is_resource($job_log) && isset($log_file) && is_resource($log_file)) {
        $job_log = $log_file;
    }
    $log_line = date('Y-m-d H:i:s') . ' [video_scene] ' . $message . PHP_EOL;
    if (defined('STDOUT')) {
        fwrite(STDOUT, $log_line);
        fflush(STDOUT);
    }
    if (is_resource($job_log)) {
        fwrite($job_log, $log_line);
        fflush($job_log);
    }
}

/**
 * Process an original video and replace its scenes only after the pipeline succeeds.
 *
 * @param array $job_data Resource and original-version signature from the queue.
 * @param mixed $job_log  Writable offline job log resource, when available.
 * @return bool False when detection succeeded but optional descriptions failed. Throws on other failures.
 */
function video_scene_process(array $job_data, $job_log = null): bool
{
    global $video_scene_python, $video_scene_threshold,
        $video_scene_api_key, $video_scene_model, $video_scene_audio, $video_scene_timeout,
        $video_scene_prompt, $video_scene_search_field, $video_scene_audio_overlap;

    $ref = filter_var($job_data['resource'] ?? null, FILTER_VALIDATE_INT);
    if ($ref === false || $ref <= 0 || !is_string($job_data['signature'] ?? null)) {
        throw new RuntimeException('Invalid scene job data.');
    }
    $source = video_scene_source($ref);
    if ($source === []) {
        video_scene_log('Resource ' . $ref . ' is no longer a supported video; skipping.', $job_log);
        return true; // Removed resource or an original replaced with a non-video file.
    }
    if (!hash_equals($source['signature'], $job_data['signature'])) {
        video_scene_log('The original for resource ' . $ref . ' changed; queueing current version.', $job_log);
        video_scene_queue($ref);
        return true;
    }
    if (get_resource_access($ref) !== RESOURCE_ACCESS_FULL && !get_edit_access($ref)) {
        throw new RuntimeException('Original video access denied for the job user.');
    }
    $settings = compact(
        'video_scene_python',
        'video_scene_threshold',
        'video_scene_api_key',
        'video_scene_model',
        'video_scene_audio',
        'video_scene_prompt',
        'video_scene_search_field',
        'video_scene_audio_overlap',
        'video_scene_timeout'
    );
    if (!video_scene_valid_config($settings)) {
        throw new RuntimeException('Invalid video scene configuration.');
    }
    video_scene_log('Preparing scene detection for resource ' . $ref . '.', $job_log);
    // Utility helpers return shell-quoted commands; subprocess argument arrays need the raw checked path.
    $ffmpeg = $ffprobe = null;
    if (get_utility_path('ffmpeg', $ffmpeg) === false || get_utility_path('ffprobe', $ffprobe) === false) {
        throw new RuntimeException('FFmpeg and FFprobe must be configured.');
    }
    // OS temporary storage keeps intermediate originals, audio and collages outside the web filestore.
    $dir = sys_get_temp_dir() . '/rs_video_scene_' . bin2hex(random_bytes(16));
    if (!mkdir($dir, 0700)) {
        throw new RuntimeException('Unable to create private scene workspace.');
    }
    try {
        $worker_request = [
            'video' => $source['path'],
            'directory' => $dir,
            'ffmpeg' => $ffmpeg,
            'ffprobe' => $ffprobe,
            'timeout' => (int) $video_scene_timeout,
            'threshold' => (float) $video_scene_threshold,
            'api_key' => $video_scene_api_key,
            'model' => $video_scene_model,
            'audio' => (bool) $video_scene_audio,
            'prompt' => $video_scene_prompt,
            'audio_overlap' => (int) $video_scene_audio_overlap,
        ];
        $worker_request['mode'] = 'thumbnails';
        $result = video_scene_worker($worker_request, $dir, $job_log);
        video_scene_log('Detector returned ' . count($result['scenes']) . ' scenes; validating results.', $job_log);
        $scenes = video_scene_validate_scenes($result['scenes'], $dir);
        $current = video_scene_source($ref);
        if ($current === [] || !hash_equals($source['signature'], $current['signature'])) {
            video_scene_queue($ref);
            return true; // Do not publish results from a deleted/replaced original.
        }
        // Schema upgrades can issue DDL, which commits transactions; finish them before replacing old rows.
        ps_query('SELECT shot_id, probability FROM resource_scene LIMIT 0');
        $old_scene_rows = ps_query('SELECT ref FROM resource_scene WHERE resource = ?', ['i', $ref]);
        $resource_directory = dirname($source['path']);
        $created_files = [];
        $scene_refs = [];
        video_scene_log(
            'Publishing ' . count($scenes) . ' scene rows and thumbnails beside the original video.',
            $job_log
        );
        db_begin_transaction('video_scene');
        try {
            ps_query('DELETE FROM resource_scene WHERE resource = ?', ['i', $ref]);
            foreach ($scenes as $scene) {
                ps_query(
                    'INSERT INTO resource_scene '
                    . '(resource, shot_id, probability, start_frame, end_frame, '
                    . 'start_time, end_time, description) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    ['i', $ref, 'i', $scene['shot_id'], 'd', $scene['probability'],
                    'i', $scene['start_frame'], 'i', $scene['end_frame'],
                    'd', $scene['start_time'], 'd', $scene['end_time'],
                    's', $scene['description']]
                );
                $scene_ref = (int) sql_insert_id();
                $scene_refs[] = $scene_ref;
                $thumbnail_path = $resource_directory . '/scene_' . $scene_ref . '.jpg';
                $temporary_path = tempnam($resource_directory, '.video_scene_');
                if (
                    $temporary_path === false
                    || !copy($scene['thumbnail_path'], $temporary_path)
                    || !chmod($temporary_path, 0644)
                    || !rename($temporary_path, $thumbnail_path)
                ) {
                    if (is_string($temporary_path) && is_file($temporary_path)) {
                        unlink($temporary_path);
                    }
                    throw new RuntimeException('Unable to save scene thumbnail beside the original video.');
                }
                $created_files[] = $thumbnail_path;
            }
            db_end_transaction('video_scene');
        } catch (Throwable $error) {
            db_rollback_transaction('video_scene');
            foreach ($created_files as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $error;
        }
        foreach ($old_scene_rows as $old_scene) {
            $old_thumbnail = $resource_directory . '/scene_' . (int) $old_scene['ref'] . '.jpg';
            if (is_file($old_thumbnail)) {
                unlink($old_thumbnail);
            }
        }
        video_scene_log('Saved ' . count($scenes) . ' scenes and thumbnails for resource ' . $ref . '.', $job_log);

        $description_errors = 0;
        if ($video_scene_api_key !== '') {
            video_scene_log('Starting OpenAI description pass after thumbnail publication.', $job_log);
            $worker_request['mode'] = 'descriptions';
            $worker_request['scenes'] = $scenes;
            $worker_request['has_audio'] = (bool) ($result['has_audio'] ?? false);
            $worker_request['duration'] = (float) ($result['duration'] ?? 0);
            $description_result = video_scene_worker($worker_request, $dir, $job_log);
            $scenes = $description_result['scenes'];
            $description_errors = (int) ($description_result['description_errors'] ?? 0);
            foreach ($scenes as $index => $scene) {
                ps_query(
                    'UPDATE resource_scene SET description = ? WHERE ref = ? AND resource = ?',
                    ['s', $scene['description'], 'i', $scene_refs[$index], 'i', $ref]
                );
            }
            video_scene_log('Saved descriptions returned by OpenAI.', $job_log);
        }
        if ((int) $video_scene_search_field > 0) {
            $descriptions = array_filter(
                array_column($scenes, 'description'),
                static fn($description) => $description !== ''
            );
            $errors = [];
            if (!update_field($ref, (int) $video_scene_search_field, implode("\n", $descriptions), $errors)) {
                debug('video_scenes: Could not update searchable scene descriptions: ' . implode('; ', $errors));
                video_scene_log('Could not update the searchable scene descriptions field.', $job_log);
                return false;
            }
            video_scene_log('Updated the searchable scene descriptions field.', $job_log);
        }
        if ($description_errors > 0) {
            video_scene_log(
                $description_errors . ' scene description request(s) failed.',
                $job_log
            );
            return false;
        }
        video_scene_log('Scene processing completed successfully for resource ' . $ref . '.', $job_log);
        return true;
    } finally {
        foreach (glob($dir . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
}

/**
 * Validate the worker manifest and thumbnails before changing the database.
 *
 * @param array  $scenes Manifest scenes, with inclusive frame ends and exclusive time ends.
 * @param string $dir    Private worker directory. Filenames are derived locally, never from the manifest.
 * @return array Scenes including private thumbnail paths. Throws on malformed output.
 */
function video_scene_validate_scenes(array $scenes, string $dir): array
{
    if ($scenes === [] || !array_is_list($scenes)) {
        throw new RuntimeException('No scenes detected.');
    }
    $last_frame = -1;
    $last_time = -1;
    foreach ($scenes as $index => &$scene) {
        if (
            !is_array($scene)
            || !is_int($scene['shot_id'] ?? null)
            || $scene['shot_id'] !== $index + 1
            || !is_numeric($scene['probability'] ?? null)
            || !is_finite((float) $scene['probability'])
            || $scene['probability'] < 0 || $scene['probability'] > 1
            || !is_int($scene['start_frame'] ?? null)
            || !is_int($scene['end_frame'] ?? null)
            || $scene['start_frame'] <= $last_frame
            || $scene['end_frame'] < $scene['start_frame']
            || !is_numeric($scene['start_time'] ?? null)
            || !is_numeric($scene['end_time'] ?? null)
            || !is_finite((float) $scene['start_time'])
            || !is_finite((float) $scene['end_time'])
            || $scene['start_time'] < max(0, $last_time)
            || $scene['end_time'] <= $scene['start_time']
            || !is_string($scene['description'] ?? null)
            || strlen($scene['description']) > 16000
        ) {
            throw new RuntimeException('Invalid scene manifest.');
        }
        $thumbnail = $dir . '/thumb_' . (int) $index . '.jpg';
        $image = is_file($thumbnail) ? getimagesize($thumbnail) : false;
        if (
            !$image || $image[2] !== IMAGETYPE_JPEG || $image[0] > 320 || $image[1] > 180
            || filesize($thumbnail) > 65536
        ) {
            throw new RuntimeException('Missing or invalid scene thumbnail.');
        }
        $scene['thumbnail_path'] = $thumbnail;
        $last_frame = $scene['end_frame'];
        $last_time = $scene['end_time'];
    }
    unset($scene);
    return $scenes;
}

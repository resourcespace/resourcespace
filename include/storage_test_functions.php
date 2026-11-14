<?php

/**
 * Can the current authenticated administrator run installation storage diagnostics?
 *
 * @return bool False for external shares and anonymous sessions, including anonymous administrators.
 */
function storage_test_allowed(): bool
{
    global $userref, $username, $anonymous_login, $k;
    return isset($userref) && $userref > 0 && checkperm('a') && empty($k)
        && (!isset($anonymous_login) || $username !== $anonymous_login);
}

/**
 * Resolve the asset filestore used by get_resource_path(), never get_temp_dir().
 * Symlinked filestore roots are supported, but stream wrappers and missing roots are not.
 *
 * @return string Canonical writable root.
 * @throws RuntimeException When storage cannot safely be resolved.
 */
function storage_test_root(): string
{
    global $storagedir;
    if (!isset($storagedir) || !is_string($storagedir) || $storagedir === '' || str_contains($storagedir, '://')) {
        throw new RuntimeException('storage_test_root_error');
    }
    $root = realpath($storagedir);
    if ($root === false || dirname($root) === $root || !is_dir($root) || !is_writable($root)) {
        throw new RuntimeException('storage_test_root_error');
    }
    return rtrim($root, '/\\');
}

/**
 * Fixed workload manifest. No path component comes from a request or directory enumeration.
 *
 * @return array Directory names and file names/sizes relative to the private test directory.
 */
function storage_test_manifest(): array
{
    $dirs = [];
    $files = [];
    for ($i = 0; $i < 4; ++$i) {
        $files['asset-' . $i . '.bin'] = 16 * 1024 * 1024;
        $dirs[] = 'd' . $i;
        for ($j = 0; $j < 4; ++$j) {
            $dirs[] = 'd' . $i . '/' . $j;
        }
    }
    for ($i = 0; $i < 96; ++$i) {
        $name = 'd' . intdiv($i, 24) . '/' . intdiv($i % 24, 6) . '/preview-' . $i . '.bin';
        $files[$name] = [4096, 32768, 262144][$i % 3];
    }
    return ['dirs' => $dirs, 'files' => $files];
}

/**
 * Validate a manifest path and every existing ancestor without following symlinks.
 * The private directory is only ever created exclusively with mode 0700.
 *
 * @param array $state Server-owned recovery state.
 * @param string $relative Fixed manifest path, or empty for the private directory itself.
 * @return string Absolute path within the current asset filestore.
 * @throws RuntimeException On changed storage, unsafe paths, symlinks or non-regular files.
 */
function storage_test_path(array $state, string $relative = ''): string
{
    $manifest = storage_test_manifest();
    if (
        !preg_match('/\A[a-f0-9]{32}\z/D', $state['id'])
        || $state['root'] !== storage_test_root()
        || ($relative !== '' && !isset($manifest['files'][$relative]) && !in_array($relative, $manifest['dirs'], true))
    ) {
        throw new RuntimeException('storage_test_unsafe');
    }
    $path = $state['root'] . '/.rs-storage-test-' . $state['id'];
    $parts = $relative === '' ? [] : explode('/', $relative);
    foreach (array_merge([''], $parts) as $part) {
        if ($part !== '') {
            $path .= '/' . $part;
        }
        clearstatcache(true, $path);
        if (is_link($path)) {
            throw new RuntimeException('storage_test_unsafe');
        }
        if (file_exists($path)) {
            $stat = lstat($path);
            if ($stat === false || (!is_dir($path) && (!is_file($path) || $stat['nlink'] !== 1))) {
                throw new RuntimeException('storage_test_unsafe');
            }
        }
    }
    return $path;
}

/**
 * Benchmark stages and their wall time budgets (seconds), excluding AJAX idle time.
 *
 * @return array Stage names mapped to maximum active time. Cleanup has its own bounded requests.
 */
function storage_test_stages(): array
{
    return ['directories' => 2, 'write' => 9, 'read' => 7, 'small_create' => 5,
        'small_read' => 4, 'stat' => 3, 'random' => 4, 'enumerate' => 2];
}

/**
 * Record actual operation time using a monotonic timer; retain partial counts on failure.
 *
 * @param array $state Mutable server state.
 * @param string $metric Metric name.
 * @param callable $operation Operation returning the number of bytes or operations actually completed.
 * @return void
 */
function storage_test_measure(array &$state, string $metric, callable $operation): void
{
    $start = hrtime(true);
    try {
        $count = $operation();
        $state['metrics'][$metric]['count'] = ($state['metrics'][$metric]['count'] ?? 0) + $count;
        if ($count > 0) {
            $state['operations'] = ($state['operations'] ?? 0) + 1;
        }
    } finally {
        $seconds = (hrtime(true) - $start) / 1e9;
        $state['metrics'][$metric]['seconds'] = ($state['metrics'][$metric]['seconds'] ?? 0) + $seconds;
        $state['slow'] = $state['slow'] || $seconds >= 1;
        if ($metric === 'random' && isset($count)) {
            $state['latencies'][] = $seconds * 1000;
        }
    }
}

/**
 * Transfer one bounded chunk, checking short reads/writes and closing the handle on every path.
 * Data generation is outside the measured operation. Writes include fflush() and close, not fsync().
 *
 * @param array $state Mutable benchmark state.
 * @param string $relative Manifest filename.
 * @param int $offset Byte offset.
 * @param int $length Expected transfer size (at most 1 MiB).
 * @param bool $write Write new random data instead of reading.
 * @param string $metric Measurement key.
 * @return void
 */
function storage_test_transfer(
    array &$state,
    string $relative,
    int $offset,
    int $length,
    bool $write,
    string $metric
): void {
    $path = storage_test_path($state, $relative);
    $data = $write ? random_bytes($length) : '';
    storage_test_measure($state, $metric, static function () use (
        &$state,
        $path,
        $offset,
        $length,
        $write,
        $data
    ): int {
        if ($write && $offset > 0 && filesize($path) !== $offset) {
            throw new RuntimeException('storage_test_io_error');
        }
        $handle = fopen($path, $write ? ($offset === 0 ? 'xb' : 'r+b') : 'rb');
        if ($handle === false) {
            throw new RuntimeException('storage_test_io_error');
        }
        try {
            if (fseek($handle, $offset) !== 0) {
                throw new RuntimeException('storage_test_io_error');
            }
            $done = 0;
            while ($done < $length) {
                $result = $write ? fwrite($handle, substr($data, $done)) : fread($handle, $length - $done);
                $bytes = $write ? $result : ($result === false ? false : strlen($result));
                if ($bytes === false || $bytes === 0) {
                    throw new RuntimeException('storage_test_io_error');
                }
                $done += $bytes;
                $state[$write ? 'written' : 'read'] += $bytes;
            }
            if ($write && !fflush($handle)) {
                throw new RuntimeException('storage_test_io_error');
            }
            return $done;
        } finally {
            fclose($handle);
        }
    });
    $state['last_operation'] = ['kind' => $write ? 'file_written' : 'file_read',
        'path' => $relative, 'bytes' => $length];
}

/**
 * Perform one workload unit. Stage cursors and file sizes are server-owned and bounded.
 *
 * @param array $state Mutable state, persisted after each request.
 * @return bool True when the current stage is complete.
 */
function storage_test_unit(array &$state): bool
{
    $manifest = storage_test_manifest();
    $files = array_keys($manifest['files']);
    $stage = $state['stage'];
    $i = $state['cursor'];
    if ($stage === 'directories' || $stage === 'enumerate') {
        if ($i >= count($manifest['dirs'])) {
            return true;
        }
        $path = storage_test_path($state, $manifest['dirs'][$i]);
        storage_test_measure($state, $stage, static function () use ($stage, $path): int {
            if ($stage === 'directories' && !is_dir(dirname($path))) {
                return 0;
            }
            if ($stage === 'enumerate' && !is_dir($path)) {
                return 0;
            }
            if ($stage === 'directories' ? !mkdir($path, 0700) : scandir($path) === false) {
                throw new RuntimeException('storage_test_io_error');
            }
            return 1;
        });
        if (is_dir($path)) {
            $state['last_operation'] = ['kind' => $stage === 'directories'
                ? 'directory_created' : 'directory_enumerated', 'path' => $manifest['dirs'][$i]];
        }
    } elseif ($stage === 'write' || $stage === 'read') {
        if ($i >= 64) {
            return true;
        }
        // Four 16 MiB assets, streamed in 1 MiB chunks.
        $file = 'asset-' . intdiv($i, 16) . '.bin';
        $offset = ($i % 16) * 1024 * 1024;
        if ($stage === 'read' && ($state['sizes'][$file] ?? 0) < $offset + 1024 * 1024) {
            ++$state['cursor'];
            return false;
        }
        storage_test_transfer($state, $file, $offset, 1024 * 1024, $stage === 'write', $stage);
        if ($stage === 'write') {
            $state['sizes'][$file] = $offset + 1024 * 1024;
        }
    } elseif (in_array($stage, ['small_create', 'small_read', 'stat'], true)) {
        if ($i >= 96) {
            return true;
        }
        $file = $files[$i + 4];
        $path = storage_test_path($state, $file);
        if ($stage === 'small_create') {
            // If directory creation hit its budget, do not generate files in missing directories.
            if (is_dir(dirname($path))) {
                storage_test_transfer($state, $file, 0, $manifest['files'][$file], true, $stage);
                $state['sizes'][$file] = $manifest['files'][$file];
                ++$state['small_created'];
            }
        } elseif (isset($state['sizes'][$file])) {
            if ($stage === 'small_read') {
                storage_test_transfer($state, $file, 0, $state['sizes'][$file], false, $stage);
                ++$state['small_read'];
            } else {
                storage_test_measure($state, 'stat', static function () use ($path): int {
                    clearstatcache(true, $path);
                    if (stat($path) === false) {
                        throw new RuntimeException('storage_test_io_error');
                    }
                    return 1;
                });
                $state['last_operation'] = ['kind' => 'metadata_read', 'path' => $file];
            }
        }
    } elseif ($stage === 'random') {
        $assets = array_filter($state['sizes'], static fn($size, $name) => str_starts_with($name, 'asset-')
            && $size >= 65536, ARRAY_FILTER_USE_BOTH);
        if ($i >= 64 || $assets === []) {
            return true;
        }
        $file = array_rand($assets);
        $offset = random_int(0, intdiv($assets[$file] - 65536, 4096)) * 4096;
        storage_test_transfer($state, $file, $offset, 65536, false, 'random');
    }
    ++$state['cursor'];
    return false;
}

/**
 * Delete only fixed manifest entries, children first. Never recursively delete enumerated content.
 * Unknown files or symlinks block cleanup and preserve recovery state for an administrator.
 *
 * @param array $state Mutable state, including a resumable cleanup cursor.
 * @param float $deadline Monotonic request deadline, in seconds.
 * @return bool True once the private directory has been removed.
 */
function storage_test_cleanup(array &$state, float $deadline): bool
{
    $manifest = storage_test_manifest();
    $paths = array_merge(array_keys($manifest['files']), array_reverse($manifest['dirs']), ['']);
    $count = 0;
    while ($state['cleanup_cursor'] < count($paths) && $count++ < 16 && hrtime(true) / 1e9 < $deadline) {
        $relative = $paths[$state['cleanup_cursor']];
        $path = storage_test_path($state, $relative);
        if (file_exists($path)) {
            $directory = is_dir($path);
            $metric = $directory ? 'directory_remove' : 'file_delete';
            storage_test_measure($state, $metric, static function () use ($path, $directory): int {
                if ($directory ? !rmdir($path) : !unlink($path)) {
                    throw new RuntimeException('storage_test_cleanup_error');
                }
                return 1;
            });
            $state['last_operation'] = ['kind' => $directory ? 'directory_removed' : 'file_deleted',
                'path' => $relative === '' ? '.rs-storage-test-' . $state['id'] : $relative];
        }
        ++$state['cleanup_cursor'];
    }
    return $state['cleanup_cursor'] === count($paths);
}

/**
 * Serialize diagnostic requests on the primary database, including across web servers.
 * A database advisory lock is released on connection loss; the persistent registry prevents
 * a second test between requests. The ordinary process lock uses temporary storage and is not atomic.
 *
 * @param bool $release Release instead of acquiring.
 * @return bool Whether the lock operation succeeded.
 */
function storage_test_lock(bool $release = false): bool
{
    $name = 'rs-storage-test-' . substr(hash('sha256', $GLOBALS['mysql_db']), 0, 32);
    $sql = $release ? 'SELECT RELEASE_LOCK(?) AS value' : 'SELECT GET_LOCK(?, 0) AS value';
    db_set_connection_mode('read_write');
    $rows = ps_query($sql, ['s', $name], '', -1, true, 0, false);
    return (int) ($rows[0]['value'] ?? 0) === 1;
}

/**
 * Read recovery state without the per-request or disk query caches.
 *
 * @return array|null Last registered test, or null if no test has been registered.
 */
function storage_test_load(): ?array
{
    db_set_connection_mode('read_write');
    $rows = ps_query(
        'SELECT value FROM sysvars WHERE name = ?',
        ['s', 'storage_performance_test'],
        '',
        -1,
        true,
        0,
        false
    );
    $json = $rows[0]['value'] ?? '';
    return $json === '' ? null : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
}

/**
 * Persist recovery state before touching files and after every bounded request.
 *
 * @param array $state Server-owned diagnostic state.
 * @return void
 */
function storage_test_save(array $state): void
{
    // Older installations do not necessarily have a unique index on sysvars.name.
    // Use the established transactional replacement helper, not an upsert.
    set_sysvar('storage_performance_test', json_encode($state, JSON_THROW_ON_ERROR), false);
}

/**
 * Execute a bounded authenticated request. Invalid IDs/actions never mutate the active test.
 * Duplicate step numbers return current progress without repeating file operations.
 *
 * @param string $action start, step or cancel.
 * @param string $id Opaque server-generated run identifier.
 * @param int $sequence Last observed response sequence, for retry/overlap protection.
 * @return array Public progress; no local storage paths are exposed.
 * @throws RuntimeException For denied, invalid, busy or unsafe requests.
 */
function storage_test_request(string $action, string $id, int $sequence): array
{
    if (!storage_test_allowed()) {
        throw new RuntimeException('storage_test_denied');
    }
    if (!in_array($action, ['start', 'step', 'cancel'], true) || $sequence < 0) {
        throw new RuntimeException('storage_test_invalid');
    }
    if (!storage_test_lock()) {
        throw new RuntimeException('storage_test_busy');
    }
    $previous = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS['use_error_exception'] = true;
    try {
        $state = storage_test_load();
        if ($action === 'start') {
            if ($state !== null && $state['stage'] !== 'done') {
                if (time() - $state['updated'] < 120 && microtime(true) - $state['started'] < 120) {
                    throw new RuntimeException('storage_test_busy');
                }
                $state['stage'] = 'cleanup';
                $state['outcome'] = 'cancelled';
                storage_test_advance($state);
                return ['recovering' => true] + storage_test_public($state);
            }
            $root = storage_test_root();
            // 74 MiB workload plus 64 MiB reserve. Unknown capacity is not treated as zero.
            try {
                $free = disk_free_space($root);
            } catch (Throwable $e) {
                // Capacity reporting is unsupported on some mounts; the byte cap still applies.
                debug('Storage performance test: available capacity could not be measured.');
                $free = false;
            }
            if ($free !== false && $free < 138 * 1024 * 1024) {
                throw new RuntimeException('storage_test_space');
            }
            $state = ['id' => bin2hex(random_bytes(16)), 'root' => $root, 'owner' => (int) $GLOBALS['userref'],
                'updated' => time(), 'started' => microtime(true), 'stage' => 'directories', 'cursor' => 0,
                'sequence' => 0, 'stage_time' => 0, 'written' => 0, 'read' => 0, 'operations' => 0, 'sizes' => [],
                'metrics' => [], 'latencies' => [], 'small_created' => 0, 'small_read' => 0,
                'cleanup_cursor' => 0, 'slow' => false, 'limited' => false, 'outcome' => 'complete',
                'last_operation' => null];
            storage_test_save($state);
            $GLOBALS['use_error_exception'] = true;
            try {
                if (!mkdir(storage_test_path($state), 0700)) {
                    throw new RuntimeException('storage_test_io_error');
                }
            } catch (Throwable $e) {
                // A failed exclusive creation does not confer ownership of any existing directory.
                $state['stage'] = 'done';
                $state['outcome'] = 'error';
                storage_test_save($state);
                throw new RuntimeException('storage_test_io_error');
            }
        } else {
            if ($state === null || !hash_equals($state['id'], $id) || $state['owner'] !== (int) $GLOBALS['userref']) {
                throw new RuntimeException('storage_test_invalid');
            }
            if ($state['stage'] !== 'done' && ($action === 'cancel' || $sequence === $state['sequence'])) {
                storage_test_advance($state, $action === 'cancel');
            }
        }
        return storage_test_public($state);
    } finally {
        $GLOBALS['use_error_exception'] = $previous;
        storage_test_lock(true);
    }
}

/**
 * Advance at most eight workload units or 200 ms; directory operations return after one unit.
 * Cleanup uses at most sixteen entries.
 * Work stops after 45 seconds wall time, leaving a nominal 15 seconds for cleanup.
 * Filesystem syscalls cannot be interrupted portably by PHP.
 *
 * @param array $state Mutable test state, always saved even on an operation failure.
 * @param bool $cancel Request cancellation and cleanup.
 * @return void
 */
function storage_test_advance(array &$state, bool $cancel = false): void
{
    $previous = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS['use_error_exception'] = true;
    $start = hrtime(true) / 1e9;
    $deadline = $start + 0.2;
    if (($cancel || microtime(true) - $state['started'] >= 45) && $state['stage'] !== 'cleanup') {
        $state['stage'] = 'cleanup';
        $state['outcome'] = $cancel ? 'cancelled' : 'limited';
    }
    try {
        if ($state['stage'] === 'cleanup') {
            if (($state['error'] ?? '') === 'storage_test_cleanup_error') {
                unset($state['error']);
            }
            if (storage_test_cleanup($state, $deadline)) {
                $state['stage'] = 'done';
                $state['elapsed'] = microtime(true) - $state['started'];
            }
        } else {
            $stages = storage_test_stages();
            $stage = $state['stage'];
            $complete = false;
            $units = in_array($stage, ['directories', 'enumerate'], true) ? 1 : 8;
            for ($i = 0; $i < $units && hrtime(true) / 1e9 < $deadline; ++$i) {
                if (microtime(true) - $state['started'] >= 45) {
                    break;
                }
                $complete = storage_test_unit($state);
                if ($complete || $state['stage_time'] + hrtime(true) / 1e9 - $start >= $stages[$stage]) {
                    break;
                }
            }
            $state['stage_time'] += hrtime(true) / 1e9 - $start;
            if ($complete || $state['stage_time'] >= $stages[$stage]) {
                $state['limited'] = $state['limited'] || !$complete;
                $names = array_keys($stages);
                $state['stage'] = $names[array_search($stage, $names, true) + 1] ?? 'cleanup';
                $state['cursor'] = 0;
                $state['stage_time'] = 0;
            }
        }
    } catch (Throwable $e) {
        debug('Storage performance test: ' . $state['stage'] . ' failed (' . get_class($e)
            . ', line ' . $e->getLine() . ').');
        $state['stage'] = 'cleanup';
        $state['outcome'] = 'error';
        $state['error'] = 'storage_test_io_error';
        // Retain the registry even if cleanup is blocked, so later runs cannot start over it.
        $GLOBALS['use_error_exception'] = true;
        try {
            if (storage_test_cleanup($state, $deadline)) {
                $state['stage'] = 'done';
                $state['elapsed'] = microtime(true) - $state['started'];
            }
        } catch (Throwable $cleanup_error) {
            $state['error'] = 'storage_test_cleanup_error';
            debug('Storage performance test: cleanup blocked (' . get_class($cleanup_error)
                . ', line ' . $cleanup_error->getLine() . ').');
        }
    } finally {
        ++$state['sequence'];
        $state['updated'] = time();
        try {
            storage_test_save($state);
        } finally {
            $GLOBALS['use_error_exception'] = $previous;
        }
    }
}

/**
 * Retry abandoned cleanup, from cron or before a new start. Never guess directories from a glob.
 * The registry survives browser closure, PHP crashes and server restarts.
 *
 * @return void
 */
function storage_test_recover(): void
{
    if (!storage_test_lock()) {
        return;
    }
    $previous = $GLOBALS['use_error_exception'] ?? false;
    $GLOBALS['use_error_exception'] = true;
    try {
        $state = storage_test_load();
        if ($state !== null && $state['stage'] !== 'done' && time() - $state['updated'] >= 120) {
            $state['stage'] = 'cleanup';
            $state['outcome'] = 'cancelled';
            $deadline = hrtime(true) / 1e9 + 1;
            do {
                storage_test_advance($state);
            } while (
                $state['stage'] !== 'done' && ($state['error'] ?? '') !== 'storage_test_cleanup_error'
                && hrtime(true) / 1e9 < $deadline
            );
        }
    } catch (Throwable $e) {
        debug('Storage performance test: recovery blocked (' . get_class($e) . ').');
    } finally {
        $GLOBALS['use_error_exception'] = $previous;
        storage_test_lock(true);
    }
}

/**
 * Return objective measurements and progress only. Throughput uses decimal MB (1,000,000 bytes).
 * Small-file rates count successfully completed whole files, not byte estimates.
 *
 * @param array $state Server state.
 * @return array JSON-safe response, including formatted metric values and units.
 */
function storage_test_public(array $state): array
{
    $metrics = [];
    foreach ($state['metrics'] as $name => $measurement) {
        $count = match ($name) {
            'small_create' => $state['small_created'],
            'small_read' => $state['small_read'],
            default => $measurement['count'] ?? 0,
        };
        if ($count <= 0 || $measurement['seconds'] <= 0 || $name === 'random') {
            continue;
        }
        $throughput = in_array($name, ['write', 'read'], true);
        $rate = $count / $measurement['seconds'] / ($throughput ? 1e6 : 1);
        $whole_files = in_array($name, ['small_create', 'small_read', 'file_delete'], true);
        $metrics[$name] = [
            'value' => number_format($rate, 2, '.', ''),
            'unit' => $throughput ? 'MB/s' : ($whole_files ? 'files/s' : 'ops/s'),
            'count' => $count,
            'seconds' => round($measurement['seconds'], 6),
        ];
    }
    $latencies = $state['latencies'];
    sort($latencies, SORT_NUMERIC);
    if ($latencies !== []) {
        $mean = array_sum($latencies) / count($latencies);
        $p95 = $latencies[(int) ceil(count($latencies) * 0.95) - 1];
        $metrics['random_mean'] = ['value' => number_format($mean, 2, '.', ''), 'unit' => 'ms'];
        $metrics['random_p95'] = ['value' => number_format($p95, 2, '.', ''), 'unit' => 'ms'];
    }
    $stages = array_merge(array_keys(storage_test_stages()), ['cleanup', 'done']);
    $index = array_search($state['stage'], $stages, true);
    $limits = ['directories' => 20, 'write' => 64, 'read' => 64, 'small_create' => 96,
        'small_read' => 96, 'stat' => 96, 'random' => 64, 'enumerate' => 20, 'cleanup' => 121, 'done' => 1];
    $fraction = $state['stage'] === 'cleanup'
        ? $state['cleanup_cursor'] / 121
        : $state['cursor'] / $limits[$state['stage']];
    $elapsed = $state['elapsed'] ?? microtime(true) - $state['started'];
    $metrics['elapsed'] = ['value' => number_format($elapsed, 2, '.', ''), 'unit' => 's'];
    return ['id' => $state['id'], 'sequence' => $state['sequence'], 'stage' => $state['stage'],
        'progress' => $state['stage'] === 'done' ? 100 : min(99, (int) (($index + $fraction) / 9 * 100)),
        'last_operation' => $state['last_operation'] ?? null,
        'operations' => $state['operations'] ?? 0,
        'operations_total' => array_sum($limits) - $limits['done'],
        'completed' => $state['stage'] === 'cleanup' ? $state['cleanup_cursor'] : $state['cursor'],
        'total' => $limits[$state['stage']],
        'item' => in_array($state['stage'], ['directories', 'enumerate'], true)
            ? (storage_test_manifest()['dirs'][$state['cursor']] ?? '') : '',
        'written' => $state['written'], 'read' => $state['read'], 'metrics' => $metrics,
        'slow' => $state['slow'], 'limited' => $state['limited'], 'outcome' => $state['outcome'],
        'error' => $state['error'] ?? null];
}

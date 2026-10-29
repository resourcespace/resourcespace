<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['userpermissions'];
    $saved_error_exception = $GLOBALS['use_error_exception'] ?? null;
    $key = 'uploadtest_' . test_generate_random_ID(8);
    $remote_directory = get_temp_dir(false) . '/remote_files/' . $GLOBALS['userref'] . '_' . $key . generateUserFilenameUID($GLOBALS['userref']);
    $source = get_temp_dir(false) . '/entrypoint_' . test_generate_random_ID(8) . '.txt';
    try {
        $resource = create_resource(1, 0);
        $bytes = 'Upload entrypoint fixture ' . test_generate_random_ID(8);
        file_put_contents($source, $bytes);
        $GLOBALS['userpermissions'] = [];
        $before = get_resource_data($resource, false);
        $check(upload_file_by_url($resource, true, false, false, $source, $key) === false, 'Upload allowed without upload permission');
        $check(get_resource_data($resource, false) === $before && file_get_contents($source) === $bytes, 'Rejected upload changed resource/source');
        $GLOBALS['userpermissions'] = $saved;
        $check(upload_file_by_url($resource, true, false, false, $source, $key), 'URL upload entrypoint rejected local ingest source');
        $row = get_resource_data($resource, false);
        $path = get_resource_path($resource, true, '', false, 'txt');
        $check($row['file_extension'] === 'txt' && is_file($path) && file_get_contents($path) === $bytes, 'Upload not associated with resource or bytes changed');
        $check(upload_file_by_url($resource, true, false, false, $source . '.missing', $key) === false, 'Missing source ingest succeeded');
        $check(file_get_contents($path) === $bytes, 'Failed replacement damaged original');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
        if ($saved_error_exception === null) {
            unset($GLOBALS['use_error_exception']);
        } else {
            $GLOBALS['use_error_exception'] = $saved_error_exception;
        }
        if (is_dir($remote_directory)) {
            foreach (new DirectoryIterator($remote_directory) as $file) {
                if ($file->isFile()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($remote_directory);
        }
        if (is_file($source)) {
            unlink($source);
        }
    }
})();

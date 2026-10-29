<?php

command_line_only();

return (static function () {
    $id = test_generate_random_ID(8);
    $source = sys_get_temp_dir() . '/rs_upload_' . $id . '.txt';
    $destination = get_temp_dir(false) . '/rs_upload_destination_' . $id . '.txt';
    $content = "Upload file effects {$id}\n";
    try {
        foreach (['copy', 'dry_run', 'rename'] as $mode) {
            if (file_exists($destination)) {
                unlink($destination);
            }
            if (file_put_contents($source, $content) === false) {
                echo 'Upload file effects setup - ';
                return false;
            }
            $result = process_file_upload(new SplFileInfo($source), new SplFileInfo($destination), ['file_move' => $mode]);
            clearstatcache();
            if (($result['success'] ?? false) !== true
                || file_exists($source) !== ($mode !== 'rename')
                || file_exists($destination) !== ($mode !== 'dry_run')
                || ($mode !== 'dry_run' && file_get_contents($destination) !== $content)
                || ($mode !== 'rename' && file_get_contents($source) !== $content)) {
                echo "Upload processor {$mode} produced incorrect file effects - ";
                return false;
            }
        }

        // A rejected replacement must preserve the existing destination and source.
        file_put_contents($source, '');
        $result = process_file_upload(new SplFileInfo($source), new SplFileInfo($destination), ['file_move' => 'rename']);
        if ($result !== ['success' => false, 'error' => ProcessFileUploadErrorCondition::EmptySourceFile]
            || !file_exists($source) || file_get_contents($destination) !== $content) {
            echo 'Rejected upload damaged the existing destination - ';
            return false;
        }
        return true;
    } finally {
        foreach ([$source, $destination] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
})();

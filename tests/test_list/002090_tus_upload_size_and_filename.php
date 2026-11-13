<?php

command_line_only();

return (static function () {
    $id = test_generate_random_ID(8);
    $path = get_temp_dir(false) . '/rs_completed_upload_' . $id . '.bin';
    $content = str_repeat('ResourceSpace upload data ', 128);
    $expected_size = (string) strlen($content);

    try {
        // Test setup - Create a completed upload with a known browser-reported size.
        if (file_put_contents($path, $content) === false) {
            echo 'Completed upload validation setup failed - ';
            return false;
        }

        // Test A - An unchanged completed upload passes the exact size comparison.
        if (!completed_upload_size_matches($path, $expected_size)) {
            echo 'Test A - Completed upload with the expected size was rejected - ';
            return false;
        }

        // Test B - Appended duplicate data causes completed-size validation to fail.
        $duplicated_data = substr($content, 0, 256);
        if (file_put_contents($path, $duplicated_data, FILE_APPEND | LOCK_EX) === false) {
            echo 'Test B - Interrupted upload simulation failed - ';
            return false;
        }

        if (completed_upload_size_matches($path, $expected_size)) {
            echo 'Test B - Upload containing duplicated data was accepted - ';
            return false;
        }

        // Test C - Identical logical filenames receive unique temporary filenames.
        $filename = 'replacement-file.tif';
        $first_temp_id = str_repeat('a', 32);
        $second_temp_id = str_repeat('b', 32);
        $first_temp_name = get_tus_upload_filename($filename, $first_temp_id);
        $second_temp_name = get_tus_upload_filename($filename, $second_temp_id);

        if (
            $first_temp_name === false
            || $second_temp_name === false
            || $first_temp_name === $second_temp_name
            || !str_starts_with($first_temp_name, "{$first_temp_id}-")
            || !str_ends_with($first_temp_name, '.tif')
        ) {
            echo 'Test C - Unique temporary upload filenames were not generated correctly - ';
            return false;
        }

        // Test D - Invalid temporary upload identifiers are rejected.
        if (
            get_tus_upload_filename($filename, '../invalid') !== false
            || get_tus_upload_filename($filename, $first_temp_id . "\n") !== false
        ) {
            echo 'Test D - Invalid temporary upload identifier was accepted - ';
            return false;
        }

        // Test E - Uploads without an identifier retain the legacy key format.
        $legacy_name = get_tus_upload_filename($filename, '', 2);
        if ($legacy_name === false || !str_starts_with($legacy_name, '2-')) {
            echo 'Test E - Legacy temporary upload filename was not preserved - ';
            return false;
        }

        return true;
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
})();

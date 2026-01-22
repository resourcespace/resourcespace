<?php

command_line_only();

// --- Set up
$run_id = test_generate_random_ID(5);
test_log("Run ID - {$run_id}");
$new_tmp_txt_file = static function () use ($run_id) {
    $tmp = tmpfile();
    fwrite($tmp, "Run ID: {$run_id}");
    return $tmp;
};
// --- End of Set up

$use_cases = [
    [
        'name' => 'Non-existent JPG file',
        'input' => ['/tmp/test.jpg'],
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'Non-existent JPG file (with extension predetermined)',
        'input' => ['/tmp/test.jpg', 'jpg'],
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'When unsure, default to octet-stream',
        'input' => ['/tmp/test_default', null, false],
        'expected' => ['application/octet-stream'],
    ],
    [
        'name' => 'Text file (force file based detection)',
        'setup' => $new_tmp_txt_file,
        'input' => ['txt', true],
        'expected' => ['text/plain'],
    ],
    [
        'name' => 'JPG file (force file based detection, no predetermined extension)',
        'setup' => static function () use ($run_id) {
            $img = create_random_image(['text' => "Run ID {$run_id}"]);
            return isset($img['path']) ? $img['path'] : $img['error'];
        },
        'input' => [null, true],
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'Detect only based on the extension',
        'setup' => $new_tmp_txt_file,
        'input' => ['jpg', false],
        'expected' => ['image/jpeg'],
    ],
    [
        'name' => 'Support file matching multiple types',
        'input' => ['/tmp/test.mp4', 'mp4'],
        'expected' => ['video/mp4', 'audio/mp4', 'video/x-m4v'],
    ],
];
foreach ($use_cases as $uc) {
    // Set up the use case environment
    if (isset($uc['setup'])) {
        $setup = $uc['setup']();
        if (is_resource($setup)) {
            $path = stream_get_meta_data($setup)['uri'];
        } elseif (file_exists($setup)) {
            $path = $setup;
        } else {
            echo "Use case: {$uc['name']} set up (reason: {$setup}) - ";
            return false;
        }
        $input = [$path, ...$uc['input']];
    } else {
        $input = $uc['input'];
    }

    $result = get_mime_type(...$input);
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        test_log('result = ' . print_r($result, true) . PHP_EOL);
        return false;
    }

    // Tear down the use case environment (i.e. remove temporary file)
    if (isset($setup)) {
        if (is_resource($setup)) {
            fclose($setup);
        } elseif (file_exists($setup)) {
            unlink($setup);
        }

        unset($setup);
    }
}

// Tear down
unset($run_id, $use_cases, $result, $new_tmp_txt_file);

return true;

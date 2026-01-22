<?php

command_line_only();
$disable_browser_check = true;

// --- Set up
$run_id = test_generate_random_ID(5);
$dest = new SplFileInfo(get_temp_dir(false) . "/test_508_{$run_id}.bin");
$expect_fail_cond = static function (ProcessFileUploadErrorCondition $V): callable {
    return static fn ($R): bool => (is_array($R) && !$R['success'] && isset($R['error']) && $R['error'] === $V);
};
// --- End of Set up

$use_cases = [
    [
        'name' => 'Faking an HTTP POST uploaded file will trigger error for developers',
        'input' => [
            'source' => [
                'name' => 'test_fake.jpg',
                'full_path' => 'test_fake.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/phpt9Vnyy',
                'error' => 0,
                'size' => 4715,
            ],
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => null,
        'should_throw' => true,
    ],
    [
        'name' => 'Invalid upload path should fail',
        'input' => [
            'source' => new SplFileInfo(__FILE__),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::InvalidUploadPath),
    ],
    [
        'name' => 'Missing source file (non-existent)',
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508_missing.txt'),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::MissingSourceFile),
    ],
    [
        'name' => 'Empty files not allowed',
        'setup' => function () {
            $fh = fopen(sys_get_temp_dir() . '/test_508_empty.txt', 'w');
            return is_resource($fh) && fclose($fh);
        },
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508_empty.txt'),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::EmptySourceFile),
    ],
    [
        'name' => 'Special files are forbidden',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . '/.htaccess', 'x'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/.htaccess'),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::SpecialFile),
    ],
    [
        'name' => 'Banned extension',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . "/test_508_{$run_id}.php", '<?php echo 508;'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_{$run_id}.php"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::InvalidExtension),
    ],
    [
        'name' => 'Banned extension (dry run mode)',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . "/test_508_{$run_id}.php", '<?php echo 508;'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_{$run_id}.php"),
            'destination' => $dest,
            'processor' => ['file_move' => 'dry_run'],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::InvalidExtension),
    ],
    [
        'name' => 'Allow specific extension',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . "/test_508_{$run_id}.csv", 'x,y'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_{$run_id}.csv"),
            'destination' => $dest,
            'processor' => ['allow_extensions' => ['csv']],
        ],
        'expected' => ['success' => true],
    ],
    [
        'name' => 'MIME type mismatch and unable to determine a file type will fail',
        'setup' => fn() => file_put_contents(
            sys_get_temp_dir() . "/test_508_mime_mismatch_binary_{$run_id}.jpg",
            // Precompiled code (64 bytes) for testing - Linux ELF binary that exits with code 42
            hex2bin(
                "7f454c4602010100000000000000000002003e00010000007800000000000000" .
                "4000000000000000400000000000000000000000000000000000000000000000" .
                "b83c0000000f05"
            )
        ),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_mime_mismatch_binary_{$run_id}.jpg"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::InvalidMimeType),
    ],
    [
        'name' => 'MIME type mismatch associated w/ a banned extension will fail',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . "/test_508_mime_mismatch_unsafe_{$run_id}.jpg", "#!/usr/bin/env bash\r\necho 'test {$run_id}'"),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_mime_mismatch_unsafe_{$run_id}.jpg"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => $expect_fail_cond(ProcessFileUploadErrorCondition::MimeTypeMismatch),
    ],
    [
        'name' => 'MIME type mismatch not associated w/ a banned extension will pass',
        'setup' => function () use ($run_id) {
            $img = create_random_image(['text' => "Run ID {$run_id}"]);
            if (!isset($img['path'])) {
                return false;
            }
            return rename($img['path'], sys_get_temp_dir() . "/test_508_mime_mismatch_safe_{$run_id}.txt");
        },
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_mime_mismatch_safe_{$run_id}.txt"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => ['success' => true],
    ],
    [
        'name' => 'File can match against multiple MIME types',
        'setup' => static function () use ($run_id) {
            // We'll create an MP4 file that only has the audio channel (i.e. no video) => the type is audio/mp4.
            $ffmpeg = get_utility_path('ffmpeg');
            if ($ffmpeg === false) {
                return false;
            }

            $cmd_output = run_command(
                "{$ffmpeg} -f lavfi -i 'sine=frequency=1000:duration=5' -c:a aac -b:a 128k -vn %outfile",
                true,
                ['%outfile' => sys_get_temp_dir() . "/test_508_mime_check_{$run_id}.mp4"],
            );

            if (mb_strpos($cmd_output, ' Error ') !== false) {
                test_log("FFMPeg command failed: {$cmd_output}");
                return false;
            }

            return true;
        },
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_mime_check_{$run_id}.mp4"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => ['success' => true],
    ],
    [
        // We use the run_id as an invalid unique extension so it can handle correctly when run w/ -nosetup
        'name' => 'Unknown file types log (activity) and skip content based checks',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . "/test_508_{$run_id}.{$run_id}", 'Lorem ipsum'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . "/test_508_{$run_id}.{$run_id}"),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => static function ($result) use ($run_id): bool {
            // Temporary: at some point after v10.6+ we should stop skipping content checks so we'll expect it to fail!
            $op_ok = is_array($result) && $result['success'];
            $log_found = (bool) ps_value(
                 'SELECT EXISTS (
                      SELECT value_new
                        FROM activity_log
                       WHERE BINARY(`activity_log`.`log_code`) = "S"
                         AND note = ?
                    ORDER BY ref DESC
                ) AS `value`',
                ['s', "Unknown MIME type for file extension '{$run_id}'"],
                false
            );

            return $op_ok && $log_found;
        },
    ],
    [
        'name' => 'Check destination is not a directory',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . '/test_508.txt', 'x'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508.txt'),
            'destination' => new SplFileInfo(get_temp_dir(false)),
            'processor' => [],
        ],
        'expected' => null,
        'should_throw' => true,
    ],
    [
        'name' => 'Check destination path is allowed',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . '/test_508.txt', 'x'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508.txt'),
            'destination' => new SplFileInfo(sys_get_temp_dir() . '/test_508_copy.txt'),
            'processor' => [],
        ],
        'expected' => null,
        'should_throw' => true,
    ],
    [
        'name' => 'Check file move processor is allowed',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . '/test_508.txt', 'x'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508.txt'),
            'destination' => $dest,
            'processor' => ['file_move' => 'unknown_value'],
        ],
        'expected' => null,
        'should_throw' => true,
    ],
    [
        'name' => 'Moving file from source to its destination',
        'setup' => fn() => file_put_contents(sys_get_temp_dir() . '/test_508.txt', 'x'),
        'input' => [
            'source' => new SplFileInfo(sys_get_temp_dir() . '/test_508.txt'),
            'destination' => $dest,
            'processor' => [],
        ],
        'expected' => ['success' => true],
    ],
];
foreach ($use_cases as $uc) {
    if (isset($uc['setup']) && !$uc['setup']()) {
        echo "[ENV] Set up '{$uc['name']}' use case - ";
        return false;
    }

    $GLOBALS['use_error_exception'] = true;
    try {
        $result = process_file_upload(...$uc['input']);
    } catch (Throwable $t) {
        $result = isset($uc['should_throw']) ? null : [];
    }
    unset($GLOBALS['use_error_exception']);

    $assertion_fails = is_callable($uc['expected']) ? !$uc['expected']($result) : $uc['expected'] !== $result;
    if ($assertion_fails) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Use case: HTTP file uploaded
// (integration test so has to be handled separately to the above scenarios)
$test_signature = generateSecureKey(32);
$test_endpoint_filename = 'test_508_endpoint.php';
$curl_post_file_response = function (string $file, array $processor = []) use ($test_endpoint_filename, $test_signature) {
    $ch = curl_init(get_temp_dir(true) . "/{$test_endpoint_filename}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        [
            'sign' => $test_signature,
            'file' => new CURLFile($file),
            'processor' => json_encode($processor)
        ]
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    $result_decoded = json_decode($result, true);
    curl_close($ch);
    return $result_decoded;
};
$test_endpoint_content = <<<EOT
<?php
\$disable_browser_check = true;
include dirname(__DIR__, 3) . '/include/boot.php';


if (getval('sign', '') !== '{$test_signature}') {
    exit;
}

\$processor = json_decode(getval('processor', '', false, fn(string \$V) => json_decode(\$V, true) !== null), true);

\$result = process_file_upload(\$_FILES['file'], new SplFileInfo('{$dest}'), \$processor);

if (!\$result['success']) {
    \$result['error'] = serialize(\$result['error']);
}

echo json_encode(\$result);

EOT;
$test_source_file = get_temp_dir(false) . '/test_508.txt';
if (
    !(
        file_put_contents(get_temp_dir(false) . "/{$test_endpoint_filename}", $test_endpoint_content)
        && file_put_contents($test_source_file, "Source for {$run_id}")
        && chmod($dest, 0777)
    )
) {
    echo "[ENV] Set up 'HTTP POST file upload' use case - ";
    return false;
}

// Integration case: HTTP POST file upload
$result = $curl_post_file_response($test_source_file);
if (!(is_array($result) && isset($result['success']) && $result['success'])) {
    echo "Use case: HTTP POST file upload - ";
    return false;
}

// Integration case: CSV file HTTP POST
$csv_file = sys_get_temp_dir() . "/test_508_{$run_id}.csv";
file_put_contents($csv_file, "x,y\n1,2");
$result = $curl_post_file_response($csv_file, ['mime_file_based_detection' => false]);
if (!(is_array($result) && isset($result['success']) && $result['success'])) {
    echo "Use case: CSV file HTTP POST - ";
    $result['error'] = unserialize($result['error']);
    test_log('$result = ' . print_r($result, true));
    return false;
}

// Integration case: Dry run mode only emulates a "file move" processor (i.e. no actual file move/copy)
$empty_file = sys_get_temp_dir() . "/test_508_{$run_id}_empty_dryrun.txt";
$empty_fh = fopen($empty_file, 'w');
if (is_resource($empty_fh) && fclose($empty_fh) && try_unlink($dest) === true) {
    $result = $curl_post_file_response($empty_file, ['file_move' => 'dry_run']);

    if (is_array($result) && isset($result['error'])) {
        $result['error'] = unserialize($result['error']);
    }

    if (!($expect_fail_cond(ProcessFileUploadErrorCondition::EmptySourceFile)($result) && !file_exists($dest))) {
        test_log('$result = ' . print_r($result, true));
        test_log("Destination file ({$dest}) exists - " . json_encode(file_exists($dest)) . PHP_EOL);
        echo 'Use case: Dry run mode only emulates a "file move" processor - ';
        return false;
    }
} else {
    echo '[ENV] Set up \'Dry run mode only emulates a "file move" processor\' use case - ';
    return false;
}

// Tear down
unset($run_id, $use_cases, $result, $dest, $expect_fail_cond);
array_map('unlink', array_merge(glob(sys_get_temp_dir() . '/test_508*'), glob(get_temp_dir() . '/test_508*')));

return true;

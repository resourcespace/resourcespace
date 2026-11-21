<?php

use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Append or clear test configuration overrides.
 *
 * The configuration is appended to the test environment configuration file in between its special markers. This can be
 * used from hooks such as beforeAll() and from within individual tests. Can be called before ResourceSpace boots.
 *
 * Example:
 *
 * ```php
 * beforeAll(fn() => setTestConfiguration(<<<'PHP'
 *     $upload_then_edit = false;
 * PHP));
 *
 * afterAll(fn() => setTestConfiguration(''));
 * ```
 *
 * @param string $config PHP configuration to append, or an empty string to reset the block.
 *
 * @throws RuntimeException If the file cannot be read/written or its markers are invalid.
 */
function setTestConfiguration(string $config): void
{
    $config_path = __DIR__ . '/config.test.php';
    if (!is_writable($config_path)) {
        throw new RuntimeException('Test configuration file is not writable.');
    }

    $contents = file_get_contents($config_path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read test configuration file.');
    }

    $start_marker = '// BEGIN PEST TEST CONFIGURATION';
    $end_marker = '// END PEST TEST CONFIGURATION';
    if (substr_count($contents, $start_marker) !== 1 || substr_count($contents, $end_marker) !== 1) {
        throw new RuntimeException('Expected one pair of test configuration markers.');
    }

    $pattern = sprintf('/%s(.*?)%s/s', preg_quote($start_marker, '/'), preg_quote($end_marker, '/'));
    if (preg_match($pattern, $contents, $matches) !== 1) {
        throw new RuntimeException('Unable to locate the test configuration section.');
    }

    $config = trim($config);
    $existing_config = trim($matches[1]);
    if ($config !== '' && $existing_config !== '') {
        $config = "{$existing_config}\n{$config}";
    }

    $replacement = $start_marker . ($config === '' ? '' : "\n{$config}") . "\n" . $end_marker;
    // A callback preserves literal dollar signs and backslashes in PHP configuration.
    $updated = preg_replace_callback($pattern, static fn () => $replacement, $contents);
    if ($updated === null || file_put_contents($config_path, $updated, LOCK_EX) !== strlen($updated)) {
        throw new RuntimeException('Unable to update test configuration file.');
    }
}

/**
 * Generates a random JPEG image for a given resource with random background color and text.
 *
 * This function creates an image of specified dimensions with a random background color
 * and text indicating the resource reference. The image is then saved to the resource's
 * designated path and also triggers the creation of previews for the resource.
 *
 * @param int $resource The reference ID of the resource for which the image is being created.
 * @param int $width The width of the generated image in pixels.
 * @param int $height The height of the generated image in pixels.
 * @return bool Returns true on success, indicating that the image was created and saved successfully.
 */
function resource_random_jpg($resource, $width, $height)
{
    // Set random colours
    $bg_r =  mt_rand(0, 255);
    $bg_g = mt_rand(0, 255);
    $bg_b = mt_rand(0, 255);

    // Create image
    $test_image = imagecreate($width, $height);
    $text_r = $bg_r < 128 ? $bg_r + 100 : $bg_r - 100;
    $text_g = $bg_g < 128 ? $bg_g + 100 : $bg_g - 100;
    $text_b = $bg_b < 128 ? $bg_b + 100 : $bg_b - 100;
    $text_col = imagecolorallocate($test_image, $text_r, $text_g, $text_b);
    imagestring($test_image, 5, 20, 15, 'Image ' . $resource, $text_col);

    $path = get_resource_path($resource, true, '', true, 'jpg');
    imagejpeg($test_image, $path, 50);
    create_previews($resource, false, 'jpg');

    return true;
}

/**
 * Generate a random image which can be used during testing (e.g to upload, or create previews for)
 *
 * @param array $info Set image parameters:
 * - text -> Image content text
 * - filename (default: random)
 * - width (default: 150)
 * - height (default: 50)
 * - bg[red|green|blue] -> Background colour (RGB), e.g $info['bg']['red'] = 234;
 *
 * @return array Returns an "error" key if something went wrong, otherwise provides some useful info (e.g path)
 */
function create_random_image(array $info): array
{
    $width = $info['width'] ?? 150;
    $height = $info['height'] ?? 50;
    $filename = $info['filename'] ?? generateSecureKey(32);

    // Background colour
    $bg_r =  $info['bg']['red'] ?? mt_rand(0, 255);
    $bg_g = $info['bg']['green'] ?? mt_rand(0, 255);
    $bg_b = $info['bg']['blue'] ?? mt_rand(0, 255);

    // Text colour
    $text_r = $bg_r < 128 ? $bg_r + 100 : $bg_r - 100;
    $text_g = $bg_g < 128 ? $bg_g + 100 : $bg_g - 100;
    $text_b = $bg_b < 128 ? $bg_b + 100 : $bg_b - 100;

    // Generate image
    $img = imagecreate($width, $height);
    imagecolorallocate($img, $bg_r, $bg_g, $bg_b);
    imagestring($img, 5, 20, 15, $info['text'], imagecolorallocate($img, $text_r, $text_g, $text_b));
    $path = get_temp_dir() . DIRECTORY_SEPARATOR . "{$filename}.jpg";
    if (imagejpeg($img, $path, 70)) {
        return [
            'path' => $path,
        ];
    }

    return [
        'error' => 'Failed to create image',
    ];
}

/**
 * Generate a random video which can be used during testing (e.g to upload, or create previews for)
 *
 * @param array $info Set video parameters:
 * - duration (default: 5 seconds)
 * - width (default: 300)
 * - height (default: 300)
 * - filename (default: random)
 * - extension (default: mp4)
 * - text -> Video content text (optional)
 *
 * @return array Returns an "error" key if something went wrong, otherwise provides some useful info (e.g path)
 */
function create_random_video(array $info): array
{
    $duration = $info['duration'] ?? 5;
    $width = $info['width'] ?? 300;
    $height = $info['height'] ?? 300;
    $filename = $info['filename'] ?? generateSecureKey(32);
    $extension = $info['extension'] ?? 'mp4';
    if (is_banned_extension($extension)) {
        $extension = 'mp4';
    }

    $ffmpeg = get_utility_path('ffmpeg');
    if ($ffmpeg !== false && in_array($extension, $GLOBALS['ffmpeg_supported_extensions'])) {
        // Add text to video only if supported
        if (isset($info['text']) && mb_strpos(run_command($ffmpeg, true), '--enable-libfontconfig') !== false) {
            $cmd_vf = '-vf drawtext=text=%info_text'
                    . ":font='Times New Roman':fontsize=10:fontcolor=black:box=1:boxcolor=white:boxborderw=5";
            $cmd_vf_params = [
                '%info_text' => new CommandPlaceholderArg(
                    $info['text'],
                    fn($val): bool => preg_match('/^[a-zA-Z0-9\#\s]*$/', $val) === 1
                ),
            ];
        } else {
            $cmd_vf = '';
            $cmd_vf_params = [];
        }

        // Create video file
        $path = get_temp_dir() . DIRECTORY_SEPARATOR . safe_file_name($filename) . ".{$extension}";
        $cmd_output = run_command(
            "$ffmpeg -f lavfi -i testsrc=duration=%duration:size=%wx%h:rate=30 $cmd_vf %outfile",
            true,
            array_merge(
                [
                    '%duration' => (int) $duration,
                    '%w' => (int) $width,
                    '%h' => (int) $height,
                    '%outfile' => new CommandPlaceholderArg($path, fn(): bool => true),
                ],
                $cmd_vf_params
            )
        );

        if (mb_strpos($cmd_output, ' Error ') !== false) {
            return [
                'error' => $cmd_output,
            ];
        }

        return [
            'path' => $path,
        ];
    }

    return [
        'error' => 'FFMpeg missing',
    ];
}

/**
 * Debug logs for ResourceSpace automated tests
 * @uses RS_TEST_DEBUG constant {@see tests/test.php}
 */
function test_log(string $msg): void
{
    if (!RS_TEST_DEBUG) {
        return;
    }

    echo PHP_EOL . $msg;
}

/**
 * Get the test files' ID (from its file name)
 */
function test_get_file_id(string $file): int
{
    $matches = [];
    return preg_match('/[1-9][0-9]{2,}/', basename($file), $matches) === 1 ? (int) $matches[0] : 0;
}

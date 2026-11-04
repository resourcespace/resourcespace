<?php

/** Standalone plugin regressions. No installation bootstrap, database or API calls. */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require __DIR__ . '/functions.php';

include dirname(__DIR__, 2) . '/include/video_scene_functions.php';
include dirname(__DIR__, 2) . '/hooks/all.php';
include dirname(__DIR__, 2) . '/config/config.php';

$settings = compact(
    'video_scene_python',
    'video_scene_threshold',
    'video_scene_api_key',
    'video_scene_model',
    'video_scene_audio',
    'video_scene_timeout'
);
scene_assert(video_scene_valid_config($settings), 'Default configuration should validate');
foreach (
    [['video_scene_threshold', '0'], ['video_scene_threshold', '1'], ['video_scene_timeout', '-1'],
    ['video_scene_python', '/missing/python'], ['video_scene_api_key', "key\nInjected: header"],
    ['video_scene_model', []], ['video_scene_audio', 'unexpected']] as [$setting, $value]
) {
    $invalid = $settings;
    $invalid[$setting] = $value;
    scene_assert(!video_scene_valid_config($invalid), 'Invalid setting accepted: ' . $setting);
}

scene_assert(video_scene_timestamp(0.04) === '00:00:00', 'Fractional timestamp formatting');
scene_assert(video_scene_timestamp(3661.99) === '01:01:01', 'Hour timestamp formatting');
scene_assert(video_scene_timestamp(90000) === '25:00:00', 'Long video timestamp formatting');

$resource = ['resource_type' => 3];
$test_preview_denied = false;
$test_access = RESOURCE_ACCESS_FULL;
scene_assert(video_scene_can_view($resource), 'Full access denied');
$test_access = RESOURCE_ACCESS_RESTRICTED;
scene_assert(video_scene_can_view($resource), 'Restricted preview access denied');
$test_access = RESOURCE_ACCESS_CONFIDENTIAL;
scene_assert(!video_scene_can_view($resource), 'Confidential resource exposed');
$test_access = RESOURCE_ACCESS_FULL;
$test_preview_denied = true;
scene_assert(!video_scene_can_view($resource), 'Denied preview exposed');

$test_queries = [];
HookVideo_scenesAllAfterpreviewcreation(10, 2, true);
scene_assert($test_queries === [], 'Alternative preview hook must not queue or mutate');
HookVideo_scenesAllDelete_resource_extra(['ref' => 10]);
scene_assert($test_queries[0] === ['DELETE FROM resource_scene WHERE resource = ?', ['i', 10]], 'Delete cleanup');

$dir = sys_get_temp_dir() . '/rs_scene_unit_' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $test_original = $dir . '/original.mp4';
    file_put_contents($test_original, 'fixture');
    $test_resources = [10 => ['ref' => 10, 'file_extension' => 'mp4']];
    $ffmpeg_supported_extensions = ['mp4', 'mp3'];
    $ffmpeg_audio_extensions = ['mp3'];
    scene_assert(video_scene_source(-1) === [], 'Upload templates must be ignored');
    scene_assert(video_scene_source(99) === [], 'Missing resources must be ignored');
    $before = video_scene_source(10);
    scene_assert($before['path'] === $test_original, 'Original path helper not used');
    file_put_contents($test_original, 'replacement-fixture');
    scene_assert($before['signature'] !== video_scene_source(10)['signature'], 'Replacement not detected');
    $test_resources[10]['file_extension'] = 'mp3';
    scene_assert(video_scene_source(10) === [], 'Audio-only upload must be ignored');

    $image = imagecreatetruecolor(320, 180);
    imagejpeg($image, $dir . '/thumb_0.jpg');
    unset($image);
    $scene = ['shot_id' => 1, 'probability' => 0.8, 'start_frame' => 0,
        'end_frame' => 0, 'start_time' => 0.0, 'end_time' => 0.04,
        'description' => '<script>must be escaped in the view</script>'];
    $validated = video_scene_validate_scenes([$scene], $dir);
    scene_assert(is_file($validated[0]['thumbnail_path']), 'Thumbnail path was not retained');
    foreach (
        [[], [array_merge($scene, ['end_time' => 0])], [array_merge($scene, ['start_frame' => -1])],
        [array_merge($scene, ['end_time' => INF])], [array_merge($scene, ['probability' => 1.5])],
        [array_merge($scene, ['shot_id' => 0])], [$scene, $scene]] as $invalid
    ) {
        $rejected = false;
        try {
            video_scene_validate_scenes($invalid, $dir);
        } catch (RuntimeException $error) {
            $rejected = true;
        }
        scene_assert($rejected, 'Malformed scene data accepted');
    }
    // An empty stdin request must fail cleanly without exposing credentials or diagnostic stderr.
    $failed = false;
    try {
        video_scene_worker(['api_key' => 'test-private-value'], $dir);
    } catch (RuntimeException $error) {
        $failed = true;
        scene_assert(!str_contains($error->getMessage(), 'test-private-value'), 'Worker credential leaked');
    }
    scene_assert($failed, 'Worker failure was not propagated');
} finally {
    foreach (glob($dir . '/*') as $file) {
        unlink($file);
    }
    rmdir($dir);
}
echo "Video scene PHP unit checks passed.\n";

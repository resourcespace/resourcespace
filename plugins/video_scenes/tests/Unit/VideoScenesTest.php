<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/include/video_scene_functions.php';

test('validates scene configuration before starting a worker', function (string $setting, mixed $value): void {
    $settings = [
        'video_scene_python' => PHP_BINARY,
        'video_scene_threshold' => '0.5',
        'video_scene_api_key' => '',
        'video_scene_model' => 'gpt-realtime-2.1',
        'video_scene_timeout' => '7200',
        'video_scene_audio' => '1',
        'video_scene_prompt' => 'Describe the scene.',
        'video_scene_audio_overlap' => '1',
    ];
    $previous_python = $GLOBALS['video_scene_python'] ?? null;
    $GLOBALS['video_scene_python'] = PHP_BINARY;
    try {
        expect(video_scene_valid_config($settings))->toBeTrue();
        $settings['video_scene_' . $setting] = $value;
        expect(video_scene_valid_config($settings))->toBeFalse();
    } finally {
        $GLOBALS['video_scene_python'] = $previous_python;
    }
})->with([
    'zero threshold' => ['threshold', '0'],
    'maximum threshold' => ['threshold', '1'],
    'invalid timeout' => ['timeout', '-1'],
    'interpreter override' => ['python', '/missing/python'],
    'line break in key' => ['api_key', "key\nextra"],
    'invalid model' => ['model', []],
    'invalid audio flag' => ['audio', 'unexpected'],
    'invalid audio overlap' => ['audio_overlap', 31],
]);

test('formats scene timestamps without wrapping after a day', function (float $seconds, string $expected): void {
    expect(video_scene_timestamp($seconds))->toBe($expected);
})->with([
    'fractional seconds' => [0.04, '00:00:00'],
    'hours' => [3661.99, '01:01:01'],
    'more than a day' => [90000.0, '25:00:00'],
]);

test('rejects invalid worker scene manifests', function (array $changes): void {
    $scene = [
        'shot_id' => 1,
        'probability' => 0.8,
        'start_frame' => 0,
        'end_frame' => 0,
        'start_time' => 0.0,
        'end_time' => 0.04,
        'description' => '',
    ];

    expect(fn () => video_scene_validate_scenes([array_replace($scene, $changes)], sys_get_temp_dir()))
        ->toThrow(RuntimeException::class, 'Invalid scene manifest.');
})->with([
    'zero-length scene' => [['end_time' => 0]],
    'negative frame' => [['start_frame' => -1]],
    'infinite time' => [['end_time' => INF]],
    'out-of-range probability' => [['probability' => 1.5]],
    'invalid shot reference' => [['shot_id' => 0]],
]);

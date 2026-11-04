<?php

/** Isolated integration test using core database, permission and job helpers. */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require dirname(__DIR__, 4) . '/include/boot.php';
require_once dirname(__DIR__, 4) . '/include/job_functions.php';
require_once dirname(__DIR__, 2) . '/include/video_scene_functions.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// Refuse to overwrite an existing test database or operate on the installation's database.
$test_database = 'rs_test_db';
$existing = ps_value(
    'SELECT COUNT(*) AS value FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
    ['s', $test_database],
    0
);
$assert((int) $existing === 0, 'rs_test_db exists; refusing to overwrite it');
$use_error_exception = true;
ps_query('CREATE DATABASE rs_test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$mysql_db = $test_database;
$query_cache_enabled = false;
$plugins = ['video_scenes'];
$hook_cache = [];
$directory = sys_get_temp_dir() . '/rs_scene_integration_' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$storagedir = $directory;
$tempdir = $directory;
sql_connect();

try {
    CheckDBStruct('dbstruct');
    CheckDBStruct('plugins/video_scenes/dbstruct');
    // Existing installations gain the new output columns through the same schema updater.
    ps_query('ALTER TABLE resource_scene DROP COLUMN shot_id, DROP COLUMN probability');
    CheckDBStruct('plugins/video_scenes/dbstruct');
    $indexes = ps_query('SHOW INDEX FROM resource_scene');
    $assert(in_array('resource_start_frame', array_column($indexes, 'Key_name')), 'Scene resource index missing');
    include dirname(__DIR__, 2) . '/config/config.php';
    include dirname(__DIR__, 2) . '/languages/en.php';
    register_plugin('video_scenes');
    $video_scene_python = $argv[1] ?? $video_scene_python;
    putenv('OMP_NUM_THREADS=2');
    putenv('MKL_NUM_THREADS=2');
    $ffmpeg_path = '/usr/bin';
    $user = new_user('video_scene_test', 3);
    setup_user(get_user($user));
    $ref = create_resource(3, 0, $user, 'video_scene_test', 'mp4', false);
    $assert($ref > 0, 'Could not create isolated video resource');
    $path = get_resource_path($ref, true, '', true, 'mp4');
    run_command(
        '/usr/bin/ffmpeg -v error -y -f lavfi -i ' . escapeshellarg('color=red:s=160x90:r=10:d=1')
        . ' -f lavfi -i ' . escapeshellarg('color=blue:s=160x90:r=10:d=1')
        . ' -filter_complex ' . escapeshellarg('[0:v][1:v]concat=n=2:v=1:a=0')
        . ' -c:v libx264 -pix_fmt yuv420p ' . escapeshellarg($path)
    );
    $assert(is_file($path), 'FFmpeg fixture creation failed');
    hook('afterpreviewcreation', '', [$ref, -1, true]);
    hook('uploadfilesuccess', '', [$ref]);
    $jobs = job_queue_get_jobs('video_scene');
    $assert(count($jobs) === 1, 'Upload and preview hooks did not deduplicate');
    job_queue_update($jobs[0]['ref'], [], STATUS_INPROGRESS);
    hook('afterpreviewcreation', '', [$ref, -1, true]);
    $assert(count(job_queue_get_jobs('video_scene')) === 1, 'Running job was duplicated');

    $data = json_decode($jobs[0]['job_data'], true);
    $assert(video_scene_process($data), 'Pipeline did not complete');
    $rows = ps_query('SELECT * FROM resource_scene WHERE resource = ? ORDER BY start_frame', ['i', $ref]);
    $assert(count($rows) > 0, 'No real scene detections were saved');
    $scene_count = count($rows);
    foreach ($rows as $index => $row) {
        $assert((int) $row['shot_id'] === $index + 1, 'Scene identifier did not round trip');
        $assert((float) $row['probability'] >= 0 && (float) $row['probability'] <= 1, 'Invalid stored probability');
        $assert(abs((float) $row['start_time'] - (int) $row['start_frame'] / 10) < 0.001, 'Incorrect scene start');
        $assert(abs((float) $row['end_time'] - ((int) $row['end_frame'] + 1) / 10) < 0.001, 'Incorrect exclusive end');
    }
    $assert(
        is_file(dirname($path) . '/scene_' . (int) $rows[0]['ref'] . '.jpg'),
        'Scene thumbnail was not stored beside the original'
    );

    // Use real ResourceSpace permissions for an allowed user and two denied cases.
    $assert(video_scene_can_view(get_resource_data($ref)), 'Allowed viewer cannot see scenes');
    $k = '';
    $editor_group = save_usergroup(0, ['name' => 'Scene editor', 'permissions' => 's,g,e0']);
    $editor = new_user('video_scene_editor', $editor_group);
    setup_user(get_user($editor));
    $resource_access_cache = [];
    $assert(!checkperm('a'), 'Editor fixture unexpectedly has administrator permission');
    $assert(video_scene_can_edit(get_resource_data($ref)), 'Resource editor without a cannot edit descriptions');
    $k = 'test-share';
    $assert(!video_scene_can_edit(get_resource_data($ref)), 'External share offered description editing');
    $k = '';
    $viewer_group = save_usergroup(0, ['name' => 'Scene viewer', 'permissions' => 's,g']);
    $viewer = new_user('video_scene_viewer', $viewer_group);
    setup_user(get_user($viewer));
    $resource_access_cache = [];
    $assert(video_scene_can_view(get_resource_data($ref)), 'Read-only viewer cannot view scenes');
    $assert(!video_scene_can_edit(get_resource_data($ref)), 'Read-only viewer can edit descriptions');
    $denied_group = save_usergroup(0, ['name' => 'Scene test denied', 'permissions' => 's,g,z0']);
    $denied_user = new_user('video_scene_denied', $denied_group);
    setup_user(get_user($denied_user));
    $resource_access_cache = [];
    $assert(!video_scene_can_view(get_resource_data($ref)), 'Denied workflow state exposed scenes');
    $assert(!video_scene_can_edit(get_resource_data($ref)), 'Denied workflow state allowed description editing');
    $denied = false;
    try {
        video_scene_process($data);
    } catch (RuntimeException $error) {
        $denied = true;
    }
    $assert($denied, 'Denied job user could process an original');
    setup_user(get_user($user));
    $resource_access_cache = [];
    $userpermissions[] = 'T3_pre';
    $assert(!video_scene_can_view(get_resource_data($ref)), 'Denied video preview exposed scenes');
    $assert(!video_scene_can_edit(get_resource_data($ref)), 'Denied preview allowed description editing');
    setup_user(get_user($user));
    $resource_access_cache = [];

    // Exercise automatic upgrade of an existing populated scene table during normal processing.
    ps_query('ALTER TABLE resource_scene DROP COLUMN shot_id, DROP COLUMN probability');
    $assert(video_scene_process($data), 'Reprocessing and automatic schema upgrade failed');
    $assert(
        (int) ps_value('SELECT COUNT(*) AS value FROM resource_scene WHERE resource = ?', ['i', $ref], 0)
            === $scene_count,
        'Reprocessing duplicated scenes'
    );
    // Failed local processing must preserve previously published results.
    $saved_python = $video_scene_python;
    $video_scene_python = PHP_BINARY;
    $failed = false;
    try {
        video_scene_process($data);
    } catch (RuntimeException $error) {
        $failed = true;
    }
    $assert($failed, 'Invalid worker executable was not rejected');
    $assert(
        (int) ps_value('SELECT COUNT(*) AS value FROM resource_scene WHERE resource = ?', ['i', $ref], 0)
            === $scene_count,
        'Worker failure deleted existing scenes'
    );
    $video_scene_python = $saved_python;

    // Exercise the real plugin job dispatcher and completion status contract.
    $offline_job_delete_completed = false;
    job_queue_update($jobs[0]['ref'], [], STATUS_ACTIVE);
    $job = job_queue_get_job($jobs[0]['ref']);
    job_queue_run_job($job, false);
    $assert(
        (int) job_queue_get_job($jobs[0]['ref'])['status'] === STATUS_COMPLETE,
        'Plugin handler did not mark the job complete'
    );
    $resource_deletion_state = null;
    delete_resource($ref);
    $assert(
        (int) ps_value('SELECT COUNT(*) AS value FROM resource_scene WHERE resource = ?', ['i', $ref], 0) === 0,
        'Permanent resource deletion left scene rows'
    );
    echo "Video scene database/job integration checks passed.\n";
} finally {
    ps_query('DROP DATABASE rs_test_db');
    // Only remove this test's randomly named private temporary directory.
    rcRmdir($directory);
}

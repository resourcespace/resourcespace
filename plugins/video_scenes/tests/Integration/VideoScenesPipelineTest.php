<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

require_once dirname(__DIR__, 2) . '/include/video_scene_functions.php';

$fixture = new class () {
    public array $globals = [];
    public ?int $resource = null;
    public string $original = '';
    public array $jobs = [];
};

beforeEach(function () use ($fixture): void {
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $configuration = (static function (): array {
        require dirname(__DIR__, 2) . '/config/config.php';
        return get_defined_vars();
    })();
    $globals = [
        'plugins', 'hook_cache', 'lang', 'originals_separate_storage', 'offline_job_delete_completed',
        'resource_deletion_state', 'resource_access_cache', 'userpermissions', 'k',
        ...array_keys($configuration),
    ];
    foreach ($globals as $name) {
        $fixture->globals[$name] = $GLOBALS[$name] ?? null;
    }
    foreach ($configuration as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    $GLOBALS['plugins'][] = 'video_scenes';
    $GLOBALS['hook_cache'] = [];
    $GLOBALS['originals_separate_storage'] = true;
    $GLOBALS['resource_deletion_state'] = null;
    $GLOBALS['k'] = '';
    CheckDBStruct('plugins/video_scenes/dbstruct');
    register_plugin('video_scenes');
    register_plugin_language('video_scenes');

    $ref = create_resource(3, 0, user: 1, file_extension: 'mp4');
    expect((int) $ref)->toBeGreaterThan(0);
    $fixture->resource = (int) $ref;
    $fixture->original = get_resource_path($fixture->resource, true, '', true, 'mp4');
    expect(copy(testFixture('Videos/short_5seconds.mp4'), $fixture->original))->toBeTrue();
});

afterEach(function () use ($fixture): void {
    foreach ($fixture->jobs as $job) {
        job_queue_delete($job);
    }
    $fixture->jobs = [];
    if ($fixture->resource !== null) {
        expect(delete_resource($fixture->resource))->toBeTrue();
        $fixture->resource = null;
    }
    foreach ($fixture->globals as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    $fixture->globals = [];
});

test('queues the original once across upload and preview hooks', function () use ($fixture): void {
    $ref = $fixture->resource;
    $source = video_scene_source($ref);
    expect($source['path'])->toBe($fixture->original);
    $code = 'video_scene_' . $ref . '_' . $source['signature'];

    hook('afterpreviewcreation', '', [$ref, 2, true]);
    expect(job_queue_get_jobs(job_code: $code))->toBe([]);
    hook('uploadfilesuccess', '', [$ref]);
    $jobs = job_queue_get_jobs(job_code: $code);
    $fixture->jobs = array_column($jobs, 'ref');
    expect($jobs)->toHaveCount(1);

    hook('afterpreviewcreation', '', [$ref, -1, true]);
    expect(job_queue_get_jobs(job_code: $code))->toHaveCount(1);
    expect(job_queue_get_jobs('video_scene', STATUS_ACTIVE, job_code: $code))->toHaveCount(1);
    expect(job_queue_get_jobs(job_code: $code, exclude_types: ['video_scene']))->toBe([]);
    job_queue_update($jobs[0]['ref'], [], STATUS_INPROGRESS);
    hook('afterpreviewcreation', '', [$ref, -1, true]);
    expect(job_queue_get_jobs(job_code: $code))->toHaveCount(1);
});

test('honours preview permissions and blocks description edits in external shares', function () use ($fixture): void {
    $resource = get_resource_data($fixture->resource);
    expect(video_scene_can_view($resource))->toBeTrue()
        ->and(video_scene_can_edit($resource))->toBeTrue();
    $GLOBALS['k'] = 'external-share';
    expect(video_scene_can_edit($resource))->toBeFalse();
    $GLOBALS['k'] = '';
    $GLOBALS['userpermissions'][] = 'T3_pre';
    expect(video_scene_can_view($resource))->toBeFalse()
        ->and(video_scene_can_edit($resource))->toBeFalse();
});

test('publishes scrambled previews and preserves them when a later worker fails', function () use ($fixture): void {
    $python = getenv('RS_VIDEO_SCENES_PYTHON');
    if (!$python) {
        $this->markTestSkipped('Set RS_VIDEO_SCENES_PYTHON to the interpreter with the scene detector installed.');
    }
    $GLOBALS['video_scene_python'] = $python;
    $GLOBALS['video_scene_api_key'] = '';
    $GLOBALS['video_scene_search_field'] = 0;
    $GLOBALS['offline_job_delete_completed'] = false;
    $ref = $fixture->resource;
    expect(copy(testFixture('Videos/scene-cuts.mp4'), $fixture->original))->toBeTrue();

    video_scene_queue($ref);
    $source = video_scene_source($ref);
    $jobs = job_queue_get_jobs(job_code: 'video_scene_' . $ref . '_' . $source['signature']);
    $fixture->jobs = array_column($jobs, 'ref');
    expect($jobs)->toHaveCount(1);
    job_queue_run_job($jobs[0], false);
    expect((int) job_queue_get_job($jobs[0]['ref'])['status'])->toBe(STATUS_COMPLETE);

    $rows = ps_query('SELECT * FROM resource_scene WHERE resource = ? ORDER BY start_frame', ['i', $ref]);
    expect($rows)->toHaveCount(3);
    $paths = [];
    foreach ($rows as $index => $row) {
        $scene = (int) $row['ref'];
        $path = video_scene_thumbnail_path($ref, $scene);
        $paths[] = $path;
        expect((int) $row['shot_id'])->toBe($index + 1)
            ->and((float) $row['probability'])->toBeBetween(0.0, 1.0)
            ->and((int) $row['start_frame'])->toBe($index * 20)
            ->and((int) $row['end_frame'])->toBe($index * 20 + 19)
            ->and(abs((float) $row['start_time'] - $index * 2))->toBeLessThan(0.000001)
            ->and(abs((float) $row['end_time'] - ($index + 1) * 2))->toBeLessThan(0.000001)
            ->and($path)->toBeFile()->toContain('/resized/')
            ->and(basename($path))->toMatch('/_[0-9a-f]{15}\.scene\.jpg$/')
            ->and(dirname($fixture->original) . '/scene_' . $scene . '.jpg')->not->toBeFile();
    }

    $GLOBALS['video_scene_python'] = PHP_BINARY;
    $data = json_decode($jobs[0]['job_data'], true, 512, JSON_THROW_ON_ERROR);
    expect(fn () => video_scene_process($data))->toThrow(RuntimeException::class)
        ->and(ps_query('SELECT * FROM resource_scene WHERE resource = ? ORDER BY start_frame', ['i', $ref]))
        ->toBe($rows);
    foreach ($paths as $path) {
        expect($path)->toBeFile();
    }
    expect(delete_resource($ref))->toBeTrue();
    $fixture->resource = null;
    expect(ps_query('SELECT ref FROM resource_scene WHERE resource = ?', ['i', $ref]))->toBe([]);
    foreach ($paths as $path) {
        expect($path)->not->toBeFile();
    }
});

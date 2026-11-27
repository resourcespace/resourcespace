<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

require_once dirname(__DIR__, 2) . '/include/video_scene_functions.php';

$fixture = new class () {
    public array $plugins = [];
    public bool $activated = false;
    public ?int $resource = null;
    public string $legacy_thumbnail = '';
    public string $original_description = '<img src=x onerror="window.sceneXss=true">';
};

beforeAll(function () use ($fixture): void {
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $fixture->plugins = $GLOBALS['plugins'];
    if (!is_plugin_activated('video_scenes')) {
        $fixture->activated = activate_plugin('video_scenes');
        expect($fixture->activated)->toBeTrue();
    }
    CheckDBStruct('plugins/video_scenes/dbstruct');
});

afterEach(function () use ($fixture): void {
    if ($fixture->resource === null) {
        return;
    }
    video_scene_delete_resource($fixture->resource);
    $previous_state = $GLOBALS['resource_deletion_state'];
    $GLOBALS['resource_deletion_state'] = null;
    try {
        expect(delete_resource($fixture->resource))->toBeTrue();
    } finally {
        $GLOBALS['resource_deletion_state'] = $previous_state;
        $fixture->resource = null;
    }
});

afterAll(function () use ($fixture): void {
    if ($fixture->activated) {
        deactivate_plugin('video_scenes');
    }
    $GLOBALS['plugins'] = $fixture->plugins;
});

test('migrates thumbnails and saves descriptions through the resource view', function () use ($fixture): void {
    $ref = create_resource(3, 0, user: 1, file_extension: 'mp4');
    expect((int) $ref)->toBeGreaterThan(0);
    $fixture->resource = (int) $ref;
    $original = get_resource_path($ref, true, '', true, 'mp4');
    expect(copy(testFixture('Videos/short_5seconds.mp4'), $original))->toBeTrue();
    expect(copy($original, get_resource_path($ref, true, 'pre', true, 'mp4')))->toBeTrue();
    ps_query(
        'INSERT INTO resource_scene (resource, start_frame, end_frame, start_time, end_time, description)
         VALUES (?, 10, 19, 1, 2, ?)',
        ['i', $ref, 's', $fixture->original_description]
    );
    $scene = (int) sql_insert_id();
    $fixture->legacy_thumbnail = dirname($original) . '/scene_' . $scene . '.jpg';
    $thumbnail = imagecreatetruecolor(320, 180);
    expect($thumbnail)->toBeInstanceOf(GdImage::class);
    expect(imagejpeg($thumbnail, $fixture->legacy_thumbnail))->toBeTrue();

    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $description = '.video-scene-description-text';
    $form = '.video-scene-description-form';
    $page->openAndWaitFor($this->browserUrl('/pages/view.php?ref=' . $ref), '.video-scene-panel')
        ->assertSeeIn($description, $fixture->original_description)
        ->assertScript('window.sceneXss === undefined');

    $thumbnail_response = $page->script(<<<'JS'
        (async () => {
            const image = document.querySelector('.video-scene-thumbnail');
            image.scrollIntoView();
            const response = await fetch(image.src);
            return [response.status, response.headers.get('content-type')];
        })()
        JS);
    expect($thumbnail_response)->toBe([200, 'image/jpeg']);
    expect(video_scene_thumbnail_path($ref, $scene))->toBeFile()
        ->and($fixture->legacy_thumbnail)->not->toBeFile();
    $page->click('.video-scene-thumbnail-button');
    $page->getPage()->page()->waitForFunction(<<<'JS'
        (() => {
            const id = document.querySelector('.video-scene-panel').dataset.playerId;
            return window.videojs.getPlayer(id)?.currentTime() >= 1;
        })()
        JS);

    $invalid_requests = $page->script(<<<'JS'
        (async () => {
            const form = document.querySelector('.video-scene-description-form');
            const data = new FormData(form);
            data.set('description', 'This must never be saved');
            const withoutToken = new FormData(form);
            withoutToken.set('description', 'This must never be saved');
            for (const key of withoutToken.keys()) {
                if (!['ref', 'scene', 'ajax', 'description'].includes(key)) {
                    withoutToken.delete(key);
                }
            }
            const get = await fetch(form.action);
            const noToken = await fetch(form.action, {method: 'POST', body: withoutToken});
            const badData = new FormData(form);
            for (const key of badData.keys()) {
                if (!withoutToken.has(key)) {
                    badData.set(key, 'invalid');
                }
            }
            const badToken = await fetch(form.action, {method: 'POST', body: badData});
            data.set('scene', '2147483647');
            const missing = await fetch(form.action, {method: 'POST', body: data});
            return [
                get.status, (await get.json()).error.status,
                noToken.status, badToken.status, missing.status, (await missing.json()).status,
            ];
        })()
        JS);
    expect($invalid_requests)->toBe([405, 405, 400, 400, 404, 'fail']);
    expect(ps_value('SELECT description AS value FROM resource_scene WHERE ref = ?', ['i', $scene], ''))
        ->toBe($fixture->original_description);

    $edited = '<script>window.sceneXss=true</script> Edited scene';
    $edit = '.video-scene-edit';
    $page->click($edit)
        ->fill($form . ' textarea', 'Unsaved text')
        ->click('.video-scene-edit-cancel')
        ->click($edit)
        ->assertValue($form . ' textarea', $fixture->original_description)
        ->fill($form . ' textarea', $edited)
        ->click($form . ' button[type=submit]')
        ->assertSeeIn($description, $edited)
        ->assertScript('window.sceneXss === undefined');
    expect(ps_value('SELECT description AS value FROM resource_scene WHERE ref = ?', ['i', $scene], ''))->toBe($edited);

    $page->resize(1440, 900);
    $view_url = encode_js_value($this->browserUrl('/pages/view.php?ref=' . $ref));
    $page->script("ModalLoad({$view_url})");
    $page->assertSeeIn('#modal ' . $description, $edited)
        ->click('#modal ' . $edit)
        ->fill('#modal ' . $form . ' textarea', '')
        ->click('#modal ' . $form . ' button[type=submit]')
        ->assertSeeIn('#modal ' . $description, 'No description available');
    expect(ps_value('SELECT description AS value FROM resource_scene WHERE ref = ?', ['i', $scene], null))->toBe('');
});

test('shows setup validation errors without saving invalid configuration', function (): void {
    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $timeout = 'input[name=video_scene_timeout]';
    $page->openAndWaitFor($this->browserUrl('/plugins/video_scenes/pages/setup.php'), $timeout);
    $result = $page->script(<<<'JS'
        (async () => {
            const form = document.querySelector('input[name=video_scene_timeout]').form;
            const data = new FormData(form);
            data.set('save', '1');
            data.set('video_scene_timeout', '1');
            const response = await fetch(form.action, {method: 'POST', body: data});
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            return [response.status, page.querySelector('.FormError[role=alert]') !== null];
        })()
        JS);
    expect($result)->toBe([400, true]);
});

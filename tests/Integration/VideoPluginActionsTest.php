<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/plugins/video_bookend/hooks/view.php';
require_once RESOURCESPACE_BASE_PATH . '/plugins/video_splice/hooks/view.php';
require_once RESOURCESPACE_BASE_PATH . '/plugins/video_tracks/hooks/view.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'resource', 'ref', 'access', 'lang', 'urlparams', 'userpermissions', 'videosplice_allowed_extensions',
        'video_tracks_allow_original_custom_formats', 'video_tracks_permitted_video_extensions',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('renders the Trim action for configured extensions and users that can trim the source', function () use (&$fixtures): void {
    register_plugin_language('video_splice');
    $GLOBALS['videosplice_allowed_extensions'] = ['mp4', 'mov'];
    $GLOBALS['access'] = RESOURCE_ACCESS_FULL;
    foreach (['mp4' => true, 'mov' => true, 'avi' => false] as $extension => $visible) {
        $ref = $fixtures->resource(3, 0, file_extension: $extension);
        $GLOBALS['resource'] = get_resource_data($ref, false);
        $GLOBALS['urlparams'] = ['ref' => $ref, 'search' => '!list' . $ref];
        $output = cast_echo_to_string(HookVideo_spliceViewAfterresourceactions(...));
        expect(str_contains($output, 'plugins/video_splice/pages/trim.php?ref=' . $ref))->toBe($visible);
    }

    $resource = $fixtures->resource(3, 0, file_extension: 'mp4');
    $GLOBALS['resource'] = get_resource_data($resource, false);
    $GLOBALS['userpermissions'] = ['s', 'g', 'A'];
    $GLOBALS['access'] = RESOURCE_ACCESS_RESTRICTED;
    expect(cast_echo_to_string(HookVideo_spliceViewAfterresourceactions(...)))->toBeEmpty();
});

it('detects a real video before offering the Bookend action', function () use (&$fixtures): void {
    register_plugin_language('video_bookend');
    $resource = $fixtures->resource(3, 0, file_extension: 'mp4');
    $original = get_resource_path($resource, true, '', true, 'mp4');
    $GLOBALS['ref'] = $resource;
    $GLOBALS['resource'] = get_resource_data($resource, false);
    $GLOBALS['resource']['has_image'] = RESOURCE_PREVIEWS_ALL;
    $GLOBALS['access'] = RESOURCE_ACCESS_FULL;

    $action = static fn (): string => trim(cast_echo_to_string(HookVideo_bookendViewAfterresourceactions(...)));
    expect($action())->toBeEmpty();
    expect(copy(testFixture('Images/image_1.jpg'), $original))->toBeTrue()
        ->and($action())->toBeEmpty();
    expect(copy(testFixture('Videos/short_5seconds.mp4'), $original))->toBeTrue()
        ->and($action())->toContain('plugins/video_bookend/pages/bookend.php?ref=' . $resource);
    $GLOBALS['access'] = RESOURCE_ACCESS_RESTRICTED;
    expect($action())->toBeEmpty();
});

it('renders original video conversion only when configured, permitted and open', function () use (&$fixtures): void {
    register_plugin_language('video_tracks');
    $GLOBALS['video_tracks_permitted_video_extensions'] = ['mp4', 'mov'];
    $video = $fixtures->resource(3, 0, file_extension: 'mp4');
    $unsupported = $fixtures->resource(3, 0, file_extension: 'mkv');
    $render = static fn (int $ref, int $access): string => trim(cast_echo_to_string(
        HookVideo_tracksViewAdditionalresourcetools2(...),
        [get_resource_data($ref, false), $access]
    ));

    $GLOBALS['video_tracks_allow_original_custom_formats'] = false;
    expect($render($video, RESOURCE_ACCESS_FULL))->toBeEmpty();
    $GLOBALS['video_tracks_allow_original_custom_formats'] = true;
    expect($render($video, RESOURCE_ACCESS_FULL))->toContain(
        'plugins/video_tracks/pages/create_video.php?ref=' . $video,
        'for_original=true'
    );
    expect($render($unsupported, RESOURCE_ACCESS_FULL))->toBeEmpty()
        ->and($render($video, RESOURCE_ACCESS_RESTRICTED))->toBeEmpty();
});

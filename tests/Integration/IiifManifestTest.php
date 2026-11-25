<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once RESOURCESPACE_BASE_PATH . '/include/iiif_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
require_once RESOURCESPACE_BASE_PATH . '/include/video_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userdata', 'userpermissions', 'hidden_fields_cache', 'defaultlanguage', 'use_error_exception']);
    $GLOBALS['use_error_exception'] = true;
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('publishes visible metadata and ordered media canvases in a IIIF Presentation 3 manifest', function (
    bool $include_video,
) use (&$fixtures): void {
    /** @var ResourceSpaceBootTestCase $this */
    if ($include_video && !get_utility_path('ffmpeg')) {
        $this->markTestSkipped('Probing the video fixture requires FFmpeg.');
    }
    $id = generateRandomTestID(10);
    $identifier = $fixtures->field('Object identifier', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'iiif' . $id, true);
    $sequence = $fixtures->field('Page', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $license = $fixtures->field('License', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $colours = $fixtures->field('Palette', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $private = $fixtures->field('Private description', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $user = $fixtures->user('iiifreader' . $id, $fixtures->group('IIIF audience ' . $id, "s,f*,f-{$private}"));
    $GLOBALS['defaultlanguage'] = 'en';

    $options = [
        'rootlevel' => '/iiif/',
        'rooturl' => 'https://example.test/iiif/',
        'rootimageurl' => 'https://example.test/iiif/image/',
        'identifier_field' => $identifier,
        'sequence_field' => $sequence,
        'license_field' => $license,
        'description_field' => 18,
        'title_field' => $GLOBALS['view_title_field'],
        'media_extensions' => ['mp4', 'mp3'],
    ];
    $object_node = set_node(null, $identifier, $id, null, 10);
    $license_node = set_node(null, $license, 'Public domain', null, 10);
    $second = $fixtures->resource(3, 0, file_extension: $include_video ? 'mp4' : '');
    $first = $fixtures->resource(1, 0, file_extension: 'jpg');
    $no_file = $fixtures->resource(1, 0);
    foreach ([1 => $first, 2 => $second, 3 => $no_file] as $page => $ref) {
        add_resource_nodes($ref, [$object_node, set_node(null, $sequence, (string) $page, null, $page)]);
    }
    add_resource_nodes($first, [
        $license_node,
        set_node(null, $colours, 'Ochre', null, 10),
        set_node(null, $colours, 'Indigo', null, 20),
    ]);
    $errors = [];
    expect(update_field($first, $options['title_field'], 'Catalogue plate', $errors))->toBeTrue()
        ->and(update_field($first, 18, 'Plate description', $errors))->toBeTrue()
        ->and(update_field($first, $private, 'Withheld from this audience', $errors))->toBeTrue();

    $preview = get_resource_path($first, true, '', true, 'jpg');
    expect(imagejpeg(imagecreatetruecolor(40, 20), $preview))->toBeTrue();
    ps_query('UPDATE resource SET has_image = 1, preview_extension = ? WHERE ref = ?', ['s', 'jpg', 'i', $first]);
    if ($include_video) {
        $video = get_resource_path($second, true, '', true, 'mp4');
        expect(copy(testFixture('Videos/short_5seconds.mp4'), $video))->toBeTrue();
    }

    $reader = get_user($user);
    $GLOBALS['userdata'][0] = $reader;
    setup_user($reader);
    $iiif = new IIIFRequest($options);
    $manifest_url = $options['rooturl'] . $id . '/manifest';
    $iiif->parseUrl($manifest_url);
    $iiif->getResources();
    $iiif->generateManifest();

    $manifest = $iiif->getResponse();
    expect(array_column($iiif->searchresults, 'ref'))->toBe([$first, $second, $no_file])
        ->and($manifest['@context'])->toBe('http://iiif.io/api/presentation/3/context.json')
        ->and($manifest['id'])->toBe($manifest_url)
        ->and($manifest['label']['en'])->toBe(['Catalogue plate'])
        ->and($manifest['summary']['en'])->toBe(['Plate description'])
        ->and($manifest['requiredStatement']['value']['en'])->toBe(['Public domain']);

    $metadata = [];
    foreach ($manifest['metadata'] as $entry) {
        $metadata[$entry['label']['en'][0]] = $entry['value']['en'][0];
    }
    expect($metadata)->toMatchArray([
        'Title' => 'Catalogue plate', 'Caption' => 'Plate description', 'License' => 'Public domain',
    ])->not->toHaveKey('Private description');
    expect(explode(', ', $metadata['Palette']))->toEqualCanonicalizing(['Ochre', 'Indigo']);

    $items = $manifest['items'];
    expect($items)->toHaveCount($include_video ? 2 : 1);
    $image = $items[0]['items'][0]['items'][0]['body'];
    expect($image)->toMatchArray([
        'id' => $options['rootimageurl'] . $first . '/full/max/0/default.jpg',
        'type' => 'Image',
        'width' => 40,
        'height' => 20,
    ]);

    if ($include_video) {
        $media = $items[1]['items'][0]['items'][0]['body'];
        $url = parse_url($media['id']);
        parse_str($url['query'], $parameters);
        expect($media)->toMatchArray(['type' => 'Video', 'format' => 'video/mp4'])
            ->and((float) $media['duration'])->toEqualWithDelta(5.0, 0.2)
            ->and($url['path'])->toEndWith('/pages/download.php')
            ->and($parameters)->toMatchArray(['ref' => (string) $second, 'ext' => 'mp4', 'noattach' => '1']);
    }
})->with([
    'image and resources without media' => [false],
    'image and video' => [true],
]);

it('serves the configured IIIF version with public metadata and an image delivery link', function (
    string $version,
) use (&$fixtures): void {
    $id = 'exhibit' . generateRandomTestID(10);
    $identifier = $fixtures->field('IIIF Identifier', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, $id, true);
    $palette = $fixtures->field('IIIF Palette', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $private = $fixtures->field('Internal IIIF note', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $reader = $fixtures->user('reader' . $id, $fixtures->group('IIIF ' . $id, "s,g,f*,f-{$private}"));
    $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
    add_resource_nodes($resource, [
        set_node(null, $identifier, $id, null, 10),
        set_node(null, $palette, 'Umber', null, 10),
        set_node(null, $palette, 'Indigo', null, 20),
    ]);
    $title = 'IIIF painting ' . $id;
    $private_value = 'Internal condition report ' . $id;
    $errors = [];
    expect(update_field($resource, $GLOBALS['view_title_field'], $title, $errors))->toBeTrue()
        ->and(update_field($resource, $private, $private_value, $errors))->toBeTrue();
    ps_query('UPDATE resource SET has_image = 1, preview_extension = ? WHERE ref = ?', ['s', 'jpg', 'i', $resource]);
    foreach (['' => 40, 'pre' => 30, 'thm' => 20] as $size => $width) {
        $path = get_resource_path($resource, true, $size, true, 'jpg');
        expect(imagejpeg(imagecreatetruecolor($width, 10), $path))->toBeTrue();
    }

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'iiif_enabled' => true, 'iiif_version' => $version,
        'iiif_userid' => $reader, 'iiif_identifier_field' => $identifier, 'iiif_description_field' => 18,
    ]);
    $path = '/iiif/' . $id . '/manifest';
    $manifest = $server->request($path);
    expect($manifest['status'])->toBe(200)
        ->and($manifest['body'])->not->toContain($private_value);
    $json = json_decode($manifest['body'], true, flags: JSON_THROW_ON_ERROR);
    $v2 = $version === '2';
    $directory = $v2 ? '2' : '3';
    expect($json['@context'])->toBe('http://iiif.io/api/presentation/' . $directory . '/context.json')
        ->and($json[$v2 ? '@id' : 'id'])->toBe($server->url . $path);
    $metadata = [];
    foreach ($json['metadata'] as $entry) {
        if ($v2) {
            $metadata[$entry['label'][0]['@value']] = $entry['value'][0]['@value'];
        } else {
            $metadata[$entry['label']['en'][0]] = $entry['value']['en'][0];
        }
    }
    expect($metadata['Title'])->toBe($title)
        ->and(array_map('trim', explode(',', $metadata['IIIF Palette'])))->toEqualCanonicalizing(['Umber', 'Indigo'])
        ->and($metadata)->not->toHaveKey('Internal IIIF note');

    $thumb = $v2 ? $json['thumbnail']['@id'] : $json['thumbnail'][0]['id'];
    expect($thumb)->toStartWith($server->url . '/iiif/image/' . $resource . '/');
    $image = $server->request(parse_url($thumb, PHP_URL_PATH));
    expect($image['status'])->toBe(200)->and($image['headers']['content-type'])->toStartWith('image/jpeg');
    expect(array_slice(getimagesizefromstring($image['body']), 0, 2))->toBe([20, 10]);
})->with(['Presentation 2' => '2', 'Presentation 3' => '3.0']);

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'related_pushed_order_by', 'sort_fields', 'hide_real_filepath', 'resource_path_pull_cache',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('adds and removes resource relationships without replacing existing relationships', function () use (&$fixtures): void {
    $source = $fixtures->resource(1, 0);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(2, 0);
    $third = $fixtures->resource(3, 0);

    expect(update_related_resource($source, [$first, $second]))->toBeTrue();
    expect(get_related_resources($source))->toEqualCanonicalizing([$first, $second]);
    expect(update_related_resource($source, [$third]))->toBeTrue();
    expect(get_related_resources($source))->toEqualCanonicalizing([$first, $second, $third]);

    expect(update_related_resource($second, [$source], false))->toBeTrue();
    expect(get_related_resources($source))->toEqualCanonicalizing([$first, $third]);

    $GLOBALS['userpermissions'] = ['s', 'e0', 'XE2'];
    expect(update_related_resource($source, $second))->toBeFalse();
    expect(get_related_resources($source))->not->toContain($second);
});

it('groups related resource links by extension or resource type on View', function () use (&$fixtures): void {
    $admin = $fixtures->user('related_groups_' . generateRandomTestID(10), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $admin,
    ]);
    $source = $fixtures->resource(1, 0);
    $photo_jpg = $fixtures->resource(1, 0, file_extension: 'jpg');
    $photo_png = $fixtures->resource(1, 0, file_extension: 'png');
    $document_jpg = $fixtures->resource(2, 0, file_extension: 'jpg');
    $document_pdf = $fixtures->resource(2, 0, file_extension: 'pdf');
    expect(update_related_resource($source, [$photo_jpg, $photo_png, $document_jpg, $document_pdf]))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $types = array_column(get_resource_types(), 'name', 'ref');
    $cases = [
        'extension' => ['jpg' => [$photo_jpg, $document_jpg], 'png' => [$photo_png], 'pdf' => [$document_pdf]],
        'type' => [$types[1] => [$photo_jpg, $photo_png], $types[2] => [$document_jpg, $document_pdf]],
    ];
    foreach ($cases as $mode => $expected) {
        $server->configure([
            'sort_relations_by_filetype' => $mode === 'extension', 'sort_relations_by_restype' => $mode === 'type',
        ]);
        $view = $server->request('/pages/view.php?ref=' . $source, headers: $headers);
        expect($view['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $panel = '//*[@id="RelatedResources"]';
        $actual = [];
        foreach ($xpath->query($panel . '//div[contains(@class, "CollectionPanelInfo")]/a') as $link) {
            $heading = $xpath->evaluate('normalize-space(ancestor::div[contains(@class, "CollectionPanelShell")]/preceding-sibling::h4[1])', $link);
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $url_params);
            expect($url_params['search'])->toBe('!related' . $source);
            $actual[$heading][] = (int) $url_params['ref'];
        }
        expect(array_keys($actual))->toEqualCanonicalizing(array_keys($expected));
        foreach ($expected as $heading => $refs) {
            expect($actual[$heading])->toEqualCanonicalizing($refs);
        }
    }
});

it('unrelates only collection members and denies changes to an uneditable collection', function () use (&$fixtures): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Related set ' . generateRandomTestID(16));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(2, 0);
    $outside = $fixtures->resource(3, 0);
    expect(add_resource_to_collection($first, $collection))->toBeTrue()
        ->and(add_resource_to_collection($second, $collection))->toBeTrue();

    expect(relate_all_collection($collection))->toBeTrue()
        ->and(update_related_resource($first, $outside))->toBeTrue();
    expect(get_related_resources($first))->toEqualCanonicalizing([$second, $outside])
        ->and(get_related_resources($second))->toBe([$first]);
    expect(unrelate_all_collection($collection))->toBeTrue();
    expect(get_related_resources($first))->toBe([$outside])
        ->and(get_related_resources($second))->toBeEmpty();

    expect(relate_all_collection($collection))->toBeTrue();
    $GLOBALS['userpermissions'][] = 'XE2';
    expect(unrelate_all_collection($collection))->toBeFalse()
        ->and(get_related_resources($first))->toEqualCanonicalizing([$second, $outside])
        ->and(get_related_resources($second))->toBe([$first]);
});

it('finds related resources and orders pushed metadata from permitted types', function () use (&$fixtures): void {
    $child_type = 2;
    $prior = ps_value('SELECT push_metadata AS value FROM resource_type WHERE ref = ?', ['i', $child_type], 0);
    $fixtures->onCleanup(static fn () => save_resource_type($child_type, ['push_metadata' => $prior]));
    expect(save_resource_type($child_type, ['push_metadata' => false]))->toBeTrue();
    $parent = $fixtures->resource(1, 0);
    $first = $fixtures->resource($child_type, 0);
    $second = $fixtures->resource($child_type, 0);
    $third = $fixtures->resource($child_type, 0);
    relate_all_resources([$parent, $first, $second, $third]);

    expect(array_column(do_search("!related{$parent}"), 'ref'))->toEqualCanonicalizing([$first, $second, $third])
        ->and(get_pushed_metadata_resources($parent))->toBeEmpty();

    $field = $fixtures->field('Pushed metadata order', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $GLOBALS['related_pushed_order_by'] = $GLOBALS['sort_fields'][] = $field;
    foreach ([$second => '1', $third => '2', $first => '3'] as $ref => $label) {
        $node = set_node(null, $field, $label, null, (int) $label);
        add_resource_nodes($ref, [$node]);
        expect(update_resource_field_column($ref, $field, $label))->toBeTrue();
    }

    expect(save_resource_type($child_type, ['push_metadata' => true]))->toBeTrue();
    $results = do_search("!relatedpushed{$parent}");
    expect(array_column($results, 'ref'))->toBe([$second, $third, $first])
        ->and(get_pushed_metadata_resources($parent))->toBe($results);

    $permissions = $GLOBALS['userpermissions'];
    $GLOBALS['userpermissions'][] = "T{$child_type}";
    clear_restype_cache();
    expect(get_pushed_metadata_resources($parent))->toBeEmpty();
    $GLOBALS['userpermissions'] = $permissions;
    clear_restype_cache();
    expect(save_resource_type($child_type, ['push_metadata' => false]))->toBeTrue()
        ->and(get_pushed_metadata_resources($parent))->toBeEmpty();
});

it('uses a related preview only for a resource type configured to pull an available image', function () use (&$fixtures): void {
    $type = 2;
    $previous = ps_value('SELECT pull_images AS value FROM resource_type WHERE ref = ?', ['i', $type], 0);
    $fixtures->onCleanup(static fn () => save_resource_type($type, ['pull_images' => $previous]));
    $GLOBALS['hide_real_filepath'] = false;
    $GLOBALS['resource_path_pull_cache'] = [];

    $photo = $fixtures->resource(1, 0);
    $document = $fixtures->resource($type, 0);
    $empty = $fixtures->resource(1, 0);
    relate_all_resources([$photo, $document, $empty]);
    $preview = get_resource_path($photo, true, 'pre', true, 'jpg');
    expect(copy(testFixture('Images/image_1.jpg'), $preview))->toBeTrue();
    ps_query('UPDATE resource SET has_image = ? WHERE ref = ?', ['i', RESOURCE_PREVIEWS_ALL, 'i', $photo]);

    $url = static fn (int $ref): string => get_resource_path($ref, false, 'pre', false, 'jpg', includemodified: false);
    expect(save_resource_type($type, ['pull_images' => true]))->toBeTrue();
    expect($url($document))->toBe($url($photo));

    $GLOBALS['resource_path_pull_cache'] = [];
    expect(save_resource_type($type, ['pull_images' => false]))->toBeTrue();
    expect($url($document))->not->toBe($url($photo));

    expect(save_resource_type($type, ['pull_images' => true]))->toBeTrue()
        ->and(unlink($preview))->toBeTrue();
    $GLOBALS['resource_path_pull_cache'] = [];
    expect($url($document))->not->toBe($url($photo))
        ->and($url($document))->not->toBe($url($empty));
});

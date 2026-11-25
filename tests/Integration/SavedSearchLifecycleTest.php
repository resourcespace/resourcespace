<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'smartsearch_accessoverride', 'search_all_workflow_states', 'userpermissions', 'uploader_view_override',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('adds matching resources across visible workflow states to the current collection', function () use (&$fixtures): void {
    $term = 'MixedWorkflowSearch' . generateRandomTestID(12);
    $target = $fixtures->collection((int) $GLOBALS['userref'], 'Saved result set ' . $term);
    $open = $fixtures->resource(1, 0);
    $pending = $fixtures->resource(1, -1);
    $excluded_state = $fixtures->resource(1, 2);
    $excluded_title = $fixtures->resource(1, 0);
    $errors = [];
    foreach ([$open, $pending, $excluded_state] as $ref) {
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
    }
    expect(update_field($excluded_title, $GLOBALS['view_title_field'], 'Unrelated title', $errors))->toBeTrue();

    $GLOBALS['search_all_workflow_states'] = true;
    $GLOBALS['uploader_view_override'] = false;
    $GLOBALS['userpermissions'][] = 'z2';
    add_saved_search_items($target, $term, '1', '');
    expect(get_collection_resources($target))->toEqualCanonicalizing([$open, $pending]);
});

it('round trips saved queries and scopes collection searches', function () use (&$fixtures): void {
    $collection = $fixtures->collection($GLOBALS['userref'], 'Saved search ' . generateRandomTestID(8));
    $other = $fixtures->collection($GLOBALS['userref'], 'Other saved search');
    $fixtures->onCleanup(static function () use ($collection, $other): void {
        ps_query('DELETE FROM collection_savedsearch WHERE collection IN (?, ?)', ['i', $collection, 'i', $other]);
    });
    $_POST = [];
    $_GET = ['addsearch' => 'title:O’Reilly café', 'restypes' => '1,2', 'archive' => '0,1'];
    add_saved_search($collection);
    $rows = get_saved_searches($collection);
    expect(count($rows) === 1)->toBeTrue('Saved search not created');
    $row = $rows[0];
    expect($row['search'] === $_GET['addsearch'] && $row['restypes'] === '1,2' && $row['archive'] === '0,1')->toBeTrue('Saved query did not round trip');
    remove_saved_search($other, $row['ref']);
    expect(count(get_saved_searches($collection)) === 1)->toBeTrue('Wrong collection deleted saved search');
    remove_saved_search($collection, $row['ref']);
    expect(get_saved_searches($collection) === [])->toBeTrue('Saved search deletion failed');
    $a = $fixtures->resource(1, 0);
    $b = $fixtures->resource(1, 0);
    $query = '!list' . $a . ':' . $b;
    add_saved_search_items($collection, $query, '', '0');
    add_saved_search_items($collection, $query, '', '0');
    expect(array_map('intval', get_collection_resources($collection)))->toEqualCanonicalizing([$a, $b]);
    $term = 'smartrefresh' . generateRandomTestID(8);
    $GLOBALS['smartsearch_accessoverride'] = false;
    update_field($a, $GLOBALS['view_title_field'], $term);
    $_GET = ['addsearch' => $term, 'restypes' => '1', 'archive' => '0'];
    add_saved_search($collection);
    $smart = get_saved_searches($collection)[0]['ref'];
    update_smart_collection($smart);
    expect(get_collection_resources($collection))->toEqual([$a]);
    update_field($a, $GLOBALS['view_title_field'], 'No longer matches');
    update_field($b, $GLOBALS['view_title_field'], $term);
    update_smart_collection($smart);
    update_smart_collection($smart);
    expect(get_collection_resources($collection))->toEqual([$b]);
    $GLOBALS['userpermissions'] = ['s', 'T1'];
    update_smart_collection($smart);
    expect(get_collection_resources($collection) === [])->toBeTrue('Smart query ignored current resource type permissions');
});

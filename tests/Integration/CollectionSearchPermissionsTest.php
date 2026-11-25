<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
$cache_settings = [
    'CACHE_FC_ACCESS_CONTROL', 'CACHE_FC_CATEG_SUB_FCS', 'CACHE_FC_PERMS_FILTER_SQL',
    'CACHE_FCS_BY_ROOT', 'CACHE_FC_RESOURCES',
];
beforeEach(function () use (&$fixtures, $cache_settings): void {
    $fixtures = new IntegrationFixtures([
        ...$cache_settings, 'userpermissions', 'public_collections_confine_group',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('searches only resources in the featured collection categories available to a restricted user', function () use (
    &$fixtures,
    $cache_settings,
): void {
    $id = generateRandomTestID(12);
    $owner = (int) $GLOBALS['userref'];
    $reader = $fixtures->user('categoryreader' . $id);
    $category = $fixtures->collection($owner, 'Mountains ' . $id);
    $subcategory = $fixtures->collection($owner, 'Cuillin ' . $id);
    $unrelated_category = $fixtures->collection($owner, 'Seasons ' . $id);
    $collections = [];
    foreach ([$category, $subcategory, $unrelated_category] as $parent) {
        $collections[$parent] = $fixtures->collection($owner, 'Assets ' . $parent);
    }
    foreach ([
        $category => 0, $subcategory => $category, $unrelated_category => 0,
        $collections[$category] => $category, $collections[$subcategory] => $subcategory,
        $collections[$unrelated_category] => $unrelated_category,
    ] as $collection => $parent) {
        save_collection($collection, ['featured_collections_changes' => [
            'update_parent' => $parent, 'force_featured_collection_type' => true,
        ]]);
    }

    $field = $fixtures->field('Category search marker', 0, FIELD_TYPE_CHECK_BOX_LIST, 'category' . $id, true);
    $node = set_node(null, $field, 'marker' . $id, null, 10);
    $resources = [];
    foreach ([...array_values($collections), null] as $collection) {
        $ref = $fixtures->resource(1, 0);
        add_resource_nodes($ref, [$node]);
        if ($collection !== null) {
            expect(add_resource_to_collection($ref, $collection))->toBeTrue();
        }
        $resources[] = $ref;
    }

    setup_user(get_user($reader));
    foreach ([
        [['j*'], $resources],
        [['j*', 'J'], array_slice($resources, 0, 3)],
        [["j{$category}", 'J'], array_slice($resources, 0, 2)],
        [["j{$category}", "-j{$subcategory}", 'J'], [$resources[0]]],
    ] as [$permissions, $expected]) {
        foreach ($cache_settings as $setting) {
            $GLOBALS[$setting] = null;
        }
        clear_query_cache('featured_collections');
        $GLOBALS['userpermissions'] = ['s', 'f*', ...$permissions];
        $results = do_search(NODE_TOKEN_PREFIX . $node);
        expect(array_column((array) $results, 'ref'))->toEqualCanonicalizing($expected);
    }
});

it('filters public and featured collection searches by name, date, type and owner group', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $owner = (int) $GLOBALS['userref'];
    $outsider = $fixtures->user('collectionreader' . $id, $fixtures->group('Unrelated group ' . $id, 's'));
    $public = $fixtures->collection($owner, 'Alpha ' . $id, 0, 0, 0, true);
    $outside = $fixtures->collection($outsider, 'Beta ' . $id, 0, 0, 0, true);
    $private = $fixtures->collection($outsider, 'Private ' . $id);
    $category = $fixtures->collection($owner, 'Category ' . $id);
    $featured = $fixtures->collection($owner, 'Gallery ' . $id);
    foreach ([$category => 0, $featured => $category] as $ref => $parent) {
        save_collection($ref, ['featured_collections_changes' => [
            'update_parent' => $parent, 'force_featured_collection_type' => true,
        ]]);
    }
    save_collection($featured, ['created' => '2021-09-01 00:00:00', 'name' => 'Gallery ' . $id]);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($first, $public))->toBeTrue()
        ->and(add_resource_to_collection($second, $public))->toBeTrue()
        ->and(add_resource_to_collection($first, $featured))->toBeTrue();
    $GLOBALS['public_collections_confine_group'] = false;
    $scope = 'collectiontitle:*' . $id;
    $search = static fn (bool $exclude_featured, bool $exclude_public, bool $override = false): array =>
        search_public_collections(
            $scope,
            exclude_themes: $exclude_featured,
            exclude_public: $exclude_public,
            include_resources: true,
            override_group_restrict: $override
        );
    expect(array_column($search(true, false), 'ref'))->toBe([$public, $outside])
        ->and(array_column($search(false, true), 'ref'))->toBe([$featured])
        ->and(array_column($search(true, true), 'ref'))->toBe([$public, $outside]);
    expect(array_map('intval', array_column($search(false, false), 'count', 'ref')))
        ->toBe([$public => 2, $outside => 0, $featured => 1]);
    expect(array_column(search_public_collections('A'), 'ref'))->toContain($public)
        ->not->toContain($outside, $private);
    $dated = search_public_collections($scope . ' basicyear:2021', exclude_themes: false);
    expect(array_column($dated, 'ref'))->toBe([$featured]);

    $GLOBALS['public_collections_confine_group'] = true;
    expect(array_column($search(false, false), 'ref'))->toBe([$public, $featured])
        ->and(array_column($search(false, false, true), 'ref'))->toBe([$public, $outside, $featured]);
});

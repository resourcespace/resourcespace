<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('reports direct resource and child flags without changing featured collection ordering', function () use (
    &$fixtures,
): void {
    $suffix = generateRandomTestID(12);
    $user = (int) $GLOBALS['userref'];
    $parent = $fixtures->collection($user, "FC tile parent {$suffix}");
    $category = $fixtures->collection($user, "FC tile category {$suffix}");
    $leaf = $fixtures->collection($user, "FC tile leaf {$suffix}");
    $grandchild = $fixtures->collection($user, "FC tile grandchild {$suffix}");
    $resource = $fixtures->resource(1, 0);

    foreach ([$parent => 0, $category => $parent, $leaf => $parent, $grandchild => $category] as $ref => $parent_ref) {
        save_collection($ref, [
            'featured_collections_changes' => [
                'update_parent' => $parent_ref,
                'force_featured_collection_type' => true,
            ],
        ]);
    }
    expect(add_resource_to_collection($resource, $leaf))->toBeTrue();
    sql_reorder_records('collection', [$category, $leaf]);

    $all_featured = array_column(get_all_featured_collections(), null, 'ref');
    expect($all_featured)->toHaveKeys([$parent, $category, $leaf, $grandchild])
        ->and((int) $all_featured[$parent]['has_resources'])->toBe(0)
        ->and((int) $all_featured[$parent]['has_children'])->toBe(1)
        ->and((int) $all_featured[$category]['has_resources'])->toBe(0)
        ->and((int) $all_featured[$category]['has_children'])->toBe(1)
        ->and((int) $all_featured[$leaf]['has_resources'])->toBe(1)
        ->and((int) $all_featured[$leaf]['has_children'])->toBe(0)
        ->and((int) $all_featured[$grandchild]['has_resources'])->toBe(0)
        ->and((int) $all_featured[$grandchild]['has_children'])->toBe(0);

    $children = get_featured_collections($parent, ['access_control' => false]);
    expect(array_column($children, 'ref'))->toBe([$category, $leaf])
        ->and((int) $children[0]['has_resources'])->toBe(0)
        ->and((int) $children[0]['has_children'])->toBe(1)
        ->and((int) $children[1]['has_resources'])->toBe(1)
        ->and((int) $children[1]['has_children'])->toBe(0);

    $tile_data = get_featured_collection_tile_data($parent, 0, null);
    expect(array_column($tile_data['featured'], 'ref'))->toBe([$category, $leaf])
        ->and($tile_data['smart'])->toBe([]);
});

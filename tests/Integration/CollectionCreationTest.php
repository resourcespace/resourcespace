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

it('lists featured categories under their parent and finds public collections by name', function () use (&$fixtures): void {
    $name = 'Collection ' . generateRandomTestID(16);
    $user = (int) $GLOBALS['userref'];
    $parent = $fixtures->collection($user, $name . ' parent');
    $child = $fixtures->collection($user, $name . ' child');
    foreach ([$parent => 0, $child => $parent] as $ref => $parent_ref) {
        save_collection($ref, ['featured_collections_changes' => [
            'update_parent' => $parent_ref,
            'force_featured_collection_type' => true,
        ]]);
    }
    expect(array_column(get_featured_collection_categories(0, ['access_control' => false]), 'ref'))->toContain($parent)
        ->and(array_column(get_featured_collection_categories($parent, ['access_control' => false]), 'ref'))
        ->toContain($child);

    $public = $fixtures->collection($user, $name . ' public', 0, 0, 0, true);
    expect(array_column(search_public_collections($name), 'ref'))->toContain($public);
});

it('restores the current permissions after a scoped bypass', function () use (&$fixtures): void {
    $user = $fixtures->user('bypass' . generateRandomTestID(16));
    setup_user(get_user($user));
    $requires_access = static fn (): bool => checkperm('v');

    expect($requires_access())->toBeFalse()
        ->and(bypass_permissions(['v'], $requires_access))->toBeTrue()
        ->and($requires_access())->toBeFalse();
});

it('reserves the default collection name for protected collections on creation and rename', function () use (
    &$fixtures,
): void {
    $user = (int) $GLOBALS['userref'];
    $normal = $fixtures->collection($user, 'Default Collection');
    $protected = $fixtures->collection($user, 'Default Collection', 0, 1);
    $fixtures->onCleanup(static fn () => save_collection($protected, ['cant_delete' => 0]));
    expect(get_collection($normal)['name'])->toBe($GLOBALS['lang']['collection'])
        ->and(get_collection($protected)['name'])->toBe('Default Collection');

    $renamed = $fixtures->collection($user, 'Collection to rename ' . generateRandomTestID(10));
    save_collection($renamed, ['name' => $GLOBALS['lang']['mycollection']]);
    expect(get_collection($renamed)['name'])->toBe($GLOBALS['lang']['collection']);
});

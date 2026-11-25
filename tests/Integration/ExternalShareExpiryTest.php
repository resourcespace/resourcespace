<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'anonymous_login', 'external_share_view_as_internal', 'userpermissions', 'usergroup',
        'userrequestmode', 'usersearchfilter', 'userresourcedefaults', 'username', 'plugins', 'emulate_plugins_set',
    ]);
    $GLOBALS['anonymous_login'] = '';
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('accepts a resource share until it expires and rejects an unknown key', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $future = date('Y-m-d H:i:s', strtotime('+3 days'));
    $past = date('Y-m-d H:i:s', strtotime('-5 days'));
    $key = generate_resource_access_key($resource, $GLOBALS['userref'], RESOURCE_ACCESS_FULL, $future, '');
    expect($key)->toBeString()->not->toBeEmpty();
    $fixtures->onCleanup(static fn () => delete_resource_access_key($resource, $key));

    expect(check_access_key($resource, $key))->toBeTrue()
        ->and(check_access_key($resource, 'unknown-share-key'))->toBeFalse();
    expect(edit_resource_external_access($key, RESOURCE_ACCESS_FULL, $past))->toBeTrue()
        ->and(check_access_key($resource, $key))->toBeFalse();
});

it('accepts a collection share until it expires and rejects shares of an empty collection', function () use (&$fixtures): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Shared ' . generateRandomTestID(12));
    $empty = $fixtures->collection((int) $GLOBALS['userref'], 'Empty share ' . generateRandomTestID(12));
    foreach (range(1, 3) as $unused) {
        expect(add_resource_to_collection($fixtures->resource(1, 0), $collection))->toBeTrue();
    }
    $future = date('Y-m-d H:i:s', strtotime('+3 days'));
    $past = date('Y-m-d H:i:s', strtotime('-5 days'));
    $key = generate_collection_access_key($collection, 0, '', RESOURCE_ACCESS_FULL, $future);
    expect($key)->toBeString()->not->toBeEmpty();
    $empty_key = generate_collection_access_key($empty, 0, '', RESOURCE_ACCESS_FULL, $future);
    expect(check_access_key_collection($collection, $key))->toBeTrue()
        ->and(check_access_key_collection($collection, 'unknown-share-key'))->toBeFalse()
        ->and(check_access_key_collection($empty, (string) $empty_key))->toBeFalse()
        ->and(check_access_key_collection($empty, 'unknown-share-key'))->toBeFalse();
    expect(edit_collection_external_access($key, RESOURCE_ACCESS_FULL, $past))->toBeTrue()
        ->and(check_access_key_collection($collection, $key))->toBeFalse();
});

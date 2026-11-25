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

it('creates a resource of the requested type', function () use (&$fixtures): void {
    $resourceId = $fixtures->resource(1);

    $resource = get_resource_data($resourceId);

    expect($resource)
        ->not
        ->toBeFalse()
        ->and((int) $resource['resource_type'])
        ->toBe(1);
});

it('creates the resource in the requested archive state', function () use (&$fixtures): void {
    $resourceId = $fixtures->resource(1, 2);
    $resource = get_resource_data($resourceId);

    expect((int) $resource['archive'])->toBe(2);
});

it('stores the creation origin in the resource log', function () use (&$fixtures): void {
    $origin = 'integration-test';
    $resourceId = $fixtures->resource(1, 0, -1, $origin);

    $log = get_resource_log($resourceId, -1, ['r.type' => 'c']);

    expect(array_column($log['data'], 'notes'))->toContain($origin);
});

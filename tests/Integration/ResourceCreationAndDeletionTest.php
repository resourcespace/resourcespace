<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'resource_deletion_state']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('rejects invalid creation input without inserting a resource', function (bool $invalid_type): void {
    $type = $invalid_type ? (int) ps_value('SELECT MAX(ref) + 1 AS value FROM resource_type', [], 1) : 1;
    $archive = $invalid_type ? 0 : 'invalid-state';
    $before = ps_value('SELECT COUNT(*) AS value FROM resource', [], 0);

    expect(create_resource($type, $archive))->toBeFalse()
        ->and(ps_value('SELECT COUNT(*) AS value FROM resource', [], 0))->toBe($before);
})->with(['unknown resource type' => true, 'invalid workflow state' => false]);

it('uses the editable workflow state as the initial state', function (int $state) use (&$fixtures): void {
    $GLOBALS['userpermissions'] = ['e' . $state];
    $resource = $fixtures->resource(1, 999, -1, '', 'txt', false);

    $data = get_resource_data($resource, false);
    expect($data)->toBeArray()
        ->and((int) $data['archive'])->toBe($state)
        ->and((int) $data['created_by'])->toBe((int) $GLOBALS['userref'])
        ->and((int) $data['resource_type'])->toBe(1)
        ->and($data['file_extension'])->toBe('txt')
        ->and((int) $data['no_file'])->toBe(1);
})->with(['pending submission' => -2, 'pending review' => -1, 'active' => 0]);

it('archives a deleted resource and logs its later permanent removal', function () use (&$fixtures): void {
    $GLOBALS['resource_deletion_state'] = 3;
    $resource = $fixtures->resource(1, 0);

    delete_resource($resource);
    expect((int) get_resource_data($resource, false)['archive'])->toBe(3)
        ->and(ps_query("SELECT ref FROM resource_log WHERE resource = ? AND type = 'x'", ['i', $resource]))
        ->not->toBeEmpty();

    delete_resource($resource);
    expect(get_resource_data($resource, false))->toBeFalse()
        ->and(ps_query("SELECT ref FROM resource_log WHERE resource = ? AND type = 'xx'", ['i', $resource]))
        ->not->toBeEmpty();
});

it('applies staged hits to the resource hit count', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    update_hitcount($resource);
    update_hitcount($resource);
    copy_hitcount_to_live();

    expect((int) get_resource_data($resource, false)['hit_count'])->toBe(2);
});

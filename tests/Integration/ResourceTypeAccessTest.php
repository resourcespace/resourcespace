<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'edit_access_for_contributor', 'edit_only_own_contributions',
        'open_access_for_contributor', 'resource_access_cache',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('applies group access restrictions only to the relevant workflow states or resource types', function (
    string $permissions,
    array $expected,
) use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $group = $fixtures->group('Resource access ' . $id, $permissions);
    $viewer = $fixtures->user('resource_access_' . $id, $group);
    $resources = [
        $fixtures->resource(1, 0),
        $fixtures->resource(2, 0),
        $fixtures->resource(1, 1),
    ];
    expect(array_map('get_resource_access', $resources))->toBe([0, 0, 0]);

    setup_user(get_user($viewer));
    $GLOBALS['resource_access_cache'] = [];
    expect(array_map('get_resource_access', $resources))->toBe($expected);
})->with([
    'can view open resources' => ['s,g', [0, 0, 0]],
    'all resources restricted' => ['s', [1, 1, 1]],
    'archived resources restricted' => ['s,g,rws1', [0, 0, 1]],
    'archived resources blocked' => ['s,g,z1', [0, 0, 2]],
    'only photos restricted' => ['s,g,X1', [1, 0, 1]],
    'only photos blocked' => ['s,g,T1', [2, 0, 2]],
]);

it('grants open access to a contributor for their own restricted resource only when configured', function () use (
    &$fixtures,
): void {
    $id = generateRandomTestID(10);
    $group = $fixtures->group('Restricted contributor ' . $id, 's,g');
    $contributor = $fixtures->user('restricted_contributor_' . $id, $group);
    $own = $fixtures->resource(1, 0, $contributor);
    $other = $fixtures->resource(1, 0);
    foreach ([$own, $other] as $ref) {
        ps_query('UPDATE resource SET access = ? WHERE ref = ?', ['i', RESOURCE_ACCESS_RESTRICTED, 'i', $ref]);
    }

    setup_user(get_user($contributor));
    $GLOBALS['open_access_for_contributor'] = false;
    $GLOBALS['resource_access_cache'] = [];
    expect(get_resource_access($own))->toBe(RESOURCE_ACCESS_RESTRICTED)
        ->and(get_resource_access($other))->toBe(RESOURCE_ACCESS_RESTRICTED);

    $GLOBALS['open_access_for_contributor'] = true;
    $GLOBALS['resource_access_cache'] = [];
    expect(get_resource_access($own))->toBe(RESOURCE_ACCESS_FULL)
        ->and(get_resource_access($other))->toBe(RESOURCE_ACCESS_RESTRICTED);
});

it('returns all available resource types and can retrieve one by reference', function (): void {
    $types = get_all_resource_types();
    expect($types)->not->toBeEmpty()
        ->and(get_resource_types())->toHaveCount(count($types))
        ->and(get_resource_types((string) $types[0]['ref']))->toHaveCount(1);
});

it('excludes a blocked type from both list and single-type lookups', function (): void {
    $types = get_all_resource_types();
    $ref = $types[0]['ref'];
    $GLOBALS['userpermissions'] = array_values(array_filter(
        $GLOBALS['userpermissions'],
        static fn (string $permission): bool => !str_starts_with($permission, 'T')
    ));
    $GLOBALS['userpermissions'][] = 'T' . $ref;

    expect(get_resource_types())->toHaveCount(count($types) - 1)
        ->and(get_resource_types($ref))->toBeEmpty();
});

it('preserves metadata on a denied transition and removes type-specific nodes on success', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $field = $fixtures->field('Photo only', 1, FIELD_TYPE_CHECK_BOX_LIST, 'photo_' . generateRandomTestID(8));
    $node = set_node(null, $field, 'Photo option', '', 10);
    add_resource_nodes($resource, [$node]);
    $logs = static fn (): int => (int) ps_value(
        'SELECT COUNT(*) AS value FROM resource_log WHERE resource = ?',
        ['i', $resource],
        0
    );
    $permissions = $GLOBALS['userpermissions'];
    $before = get_resource_data($resource, false);
    $nodes = get_resource_nodes($resource);
    $log_count = $logs();

    $GLOBALS['userpermissions'] = ['e0', 'XU2'];
    expect(update_resource_type($resource, 2))->toBeFalse()
        ->and(get_resource_data($resource, false))->toBe($before)
        ->and(get_resource_nodes($resource))->toBe($nodes)
        ->and($logs())->toBe($log_count);

    $GLOBALS['userpermissions'] = $permissions;
    expect(update_resource_type($resource, 2))->toBeTrue()
        ->and((int) get_resource_data($resource, false)['resource_type'])->toBe(2)
        ->and(ps_value(
            'SELECT COUNT(*) AS value FROM resource_node WHERE resource = ? AND node = ?',
            ['i', $resource, 'i', $node],
            -1
        ))->toEqual(0)
        ->and($logs())->toBeGreaterThan($log_count);
});

it('applies workflow, ownership and resource-type edit permissions', function () use (&$fixtures): void {
    $editor = $fixtures->user('editcontributor' . generateRandomTestID(12));
    $own_active = $fixtures->resource(1, 0, $editor);
    $own_pending = $fixtures->resource(1, -2, $editor);
    $own_review = $fixtures->resource(1, -1, $editor);
    $other_active = $fixtures->resource(1, 0);
    $other_pending = $fixtures->resource(1, -2);
    setup_user(get_user($editor));
    $GLOBALS['edit_access_for_contributor'] = false;
    $GLOBALS['edit_only_own_contributions'] = false;
    $permissions = ['s', 'd', 'g', 'f*', 'e-1', 'e-2'];
    $GLOBALS['userpermissions'] = $permissions;

    $template = -$editor;
    $fixtures->trackResource($template);
    expect(get_edit_access($template))->toBeTrue()
        ->and(get_edit_access($own_pending))->toBeTrue()
        ->and(get_edit_access($own_review))->toBeTrue()
        ->and(get_edit_access($own_active))->toBeFalse()
        ->and(get_edit_access($other_pending))->toBeFalse();

    $GLOBALS['edit_access_for_contributor'] = true;
    expect(get_edit_access($own_active))->toBeTrue()->and(get_edit_access($other_active))->toBeFalse();

    $GLOBALS['edit_access_for_contributor'] = false;
    $GLOBALS['edit_only_own_contributions'] = true;
    $GLOBALS['userpermissions'] = [...$permissions, 'e0'];
    expect(get_edit_access($own_active))->toBeTrue()->and(get_edit_access($other_active))->toBeFalse();

    $GLOBALS['edit_only_own_contributions'] = false;
    foreach (['XE1', 'XE', 'z-2'] as $deny) {
        $GLOBALS['userpermissions'] = [...$permissions, $deny];
        expect(get_edit_access($own_pending))->toBeFalse();
    }
    $GLOBALS['userpermissions'] = [...$permissions, 'ert1'];
    expect(get_edit_access($own_active))->toBeTrue()
        ->and(get_edit_access($other_active))->toBeTrue()
        ->and(get_edit_access($other_pending))->toBeTrue();
});

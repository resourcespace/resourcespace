<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'custom_access', 'additional_archive_states', 'lang', 'userpermissions', 'open_access_for_contributor',
        'edit_access_for_contributor', 'resource_access_cache', 'get_resource_data_cache',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('expires user grants and revokes only the selected custom access', function () use (&$fixtures): void {
    global $userref, $usergroup, $custom_access;

    $custom_access = true;
    $resource = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    foreach ([$resource, $control] as $ref) {
        ps_query(
            'INSERT INTO resource_custom_access (resource, usergroup, access) VALUES (?, ?, ?)',
            ['i', $ref, 'i', $usergroup, 'i', RESOURCE_ACCESS_RESTRICTED]
        );
        ps_query(
            'INSERT INTO resource_custom_access (resource, user, access, user_expires) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))',
            ['i', $ref, 'i', $userref, 'i', RESOURCE_ACCESS_FULL]
        );
    }
    expect(get_custom_access_user($resource, $userref) !== RESOURCE_ACCESS_FULL)->toBeFalse('Unexpired user grant was not returned - ');
    ps_query(
        'UPDATE resource_custom_access SET user_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE resource = ? AND user = ?',
        ['i', $resource, 'i', $userref]
    );
    expect(get_custom_access_user($resource, $userref) !== false
        || get_custom_access_user($control, $userref) !== RESOURCE_ACCESS_FULL)->toBeFalse('Expired grant remained valid or affected another resource - ');
    delete_resource_custom_access_usergroups($resource);
    expect(get_custom_access($resource, $usergroup, false) !== false
        || get_custom_access($resource, $usergroup) !== RESOURCE_ACCESS_CONFIDENTIAL
        || get_custom_access($control, $usergroup) !== RESOURCE_ACCESS_RESTRICTED
        || ps_value(
            'SELECT COUNT(*) AS value FROM resource_custom_access WHERE resource = ? AND user = ?',
            ['i', $resource, 'i', $userref],
            0
        ) != 1)->toBeFalse('Revoking group access affected user grants or another resource - ');
    delete_resource_custom_user_access($resource, $userref);
    expect(ps_value('SELECT COUNT(*) AS value FROM resource_custom_access WHERE resource = ?', ['i', $resource], 0) != 0
        || get_custom_access_user($control, $userref) !== RESOURCE_ACCESS_FULL)->toBeFalse('Revoking user access left a grant or affected another resource - ');
});

it('restricts an additional workflow state without changing access to other states', function () use (&$fixtures): void {
    $state = 42;
    $GLOBALS['additional_archive_states'][] = $state;
    $GLOBALS['lang']['status' . $state] = 'Restricted test state';
    $active = $fixtures->resource(1, 0);
    $restricted = $fixtures->resource(1, $state);

    $reader = $fixtures->user('workflowreader' . generateRandomTestID(12));
    setup_user(get_user($reader));
    $GLOBALS['userpermissions'] = ['s', 'g', 'f*', 'rws' . $state];
    expect(get_resource_access($active))->toBe(RESOURCE_ACCESS_FULL)
        ->and(get_resource_access($restricted))->toBe(RESOURCE_ACCESS_RESTRICTED);
});

it('grants open access to the contributor only when the contributor access option is enabled', function () use (&$fixtures): void {
    $user = $fixtures->user('accesscontributor' . generateRandomTestID(12));
    $owned = $fixtures->resource(1, 0, $user);
    $other = $fixtures->resource(1, 0);
    foreach ([$owned, $other] as $ref) {
        expect(put_resource_data($ref, ['access' => RESOURCE_ACCESS_CONFIDENTIAL]))->toBeTrue();
    }
    setup_user(get_user($user));
    $GLOBALS['userpermissions'] = ['s', 'g', 'f*'];
    $GLOBALS['edit_access_for_contributor'] = false;
    $GLOBALS['open_access_for_contributor'] = false;
    unset($GLOBALS['get_resource_data_cache'][$owned], $GLOBALS['get_resource_data_cache'][$other]);
    $GLOBALS['resource_access_cache'] = [];
    expect(get_resource_access($owned))->toBe(RESOURCE_ACCESS_CONFIDENTIAL);
    $GLOBALS['open_access_for_contributor'] = true;
    $GLOBALS['resource_access_cache'] = [];
    expect(get_resource_access($owned))->toBe(RESOURCE_ACCESS_FULL)
        ->and(get_resource_access($other))->toBe(RESOURCE_ACCESS_CONFIDENTIAL);
});

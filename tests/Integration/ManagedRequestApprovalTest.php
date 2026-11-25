<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'owner_field', 'owner_field_mappings']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('changes request status and access only for authorised managers', function () use (&$fixtures): void {
    include_once dirname(__DIR__, 2) . '/include/request_functions.php';
    $user = $fixtures->user('requester_' . generateRandomTestID(6));
    $resource = $fixtures->resource(1, 0);
    $collection = $fixtures->collection($user, 'Request fixture');
    add_resource_to_collection($resource, $collection, true);
    ps_query('INSERT INTO request (user,collection,created,status,request_mode) VALUES (?,?,NOW(),0,1)', ['i', $user, 'i', $collection]);
    $request = sql_insert_id();
    $fixtures->onCleanup(static function () use ($request): void {
        ps_query('DELETE FROM request WHERE ref = ?', ['i', $request]);
    });
    expect(get_request($request)['status'] == 0)->toBeTrue('Pending request fixture missing');
    $_GET = [];
    $_POST = ['status' => '1', 'expires' => '2099-12-31', 'assigned_to' => '0', 'reasonapproved' => 'Approved for testing'];
    $GLOBALS['userpermissions'] = array_unique(array_merge($GLOBALS['userpermissions'], ['R', 'Ra']));
    expect(save_request($request))->toBeTrue('Request approval failed');
    $row = get_request($request);
    expect($row['status'] == 1 && $row['reasonapproved'] === 'Approved for testing')->toBeTrue('Approval status/reason not persisted');
    $access = ps_query('SELECT * FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user]);
    expect(count($access) === 1 && $access[0]['access'] == 0)->toBeTrue('Approval did not grant access');
    save_request($request);
    expect(ps_value('SELECT COUNT(*) value FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user], 0) == 1)->toBeTrue('Repeated approval duplicated grant');
    $_POST = ['status' => '2', 'expires' => '', 'assigned_to' => '0', 'reason' => 'Declined for testing'];
    expect(save_request($request))->toBeTrue('Request rejection failed');
    expect(get_request($request)['status'] == 2 && get_request($request)['reason'] === 'Declined for testing')->toBeTrue('Rejection not persisted');
    expect(ps_value('SELECT COUNT(*) value FROM resource_custom_access WHERE resource = ? AND user = ?', ['i', $resource, 'i', $user], -1) == 0)->toBeTrue('Rejection retained access grant');
});

it('validates the request owner field and discards malformed routing rules', function () use (&$fixtures): void {
    require_once dirname(__DIR__, 2) . '/include/request_functions.php';
    $field = $fixtures->field('Request owner configuration', 0, FIELD_TYPE_DROP_DOWN_LIST);
    $text = $fixtures->field('Unsupported request owner field');
    $node = set_node(null, $field, 'Mapped owner', null, 10);
    $invalid = ['not a node' => 3, $node => 'not a group', $node + 1 => [], $node + 2 => false, $node + 3 => null];
    foreach ([
        [$field, [$node => 3], true],
        ['invalid field', [$node => 3], false],
        [$text, [$node => 3], false],
        [$field, $invalid, false],
        ['invalid field', $invalid, false],
    ] as [$selected, $mapping, $expected]) {
        $GLOBALS['owner_field'] = $selected;
        $GLOBALS['owner_field_mappings'] = $mapping;
        expect(can_use_owner_field())->toBe($expected);
        if ($mapping === $invalid) {
            expect($GLOBALS['owner_field_mappings'])->toBeEmpty();
        }
    }
});

it('routes managed request notifications to eligible owners of the requested resources', function () use (&$fixtures): void {
    require_once dirname(__DIR__, 2) . '/include/request_functions.php';
    $id = generateRandomTestID(12);
    $field = $fixtures->field('Request routing field', 0, FIELD_TYPE_DROP_DOWN_LIST);
    $group_a = $fixtures->group('Owner A ' . $id, 's');
    $group_b = $fixtures->group('Owner B ' . $id, 's');
    $user_a = $fixtures->user('requestownera' . $id, $group_a);
    $user_b = $fixtures->user('requestownerb' . $id, $group_b);
    $unrelated = $fixtures->user('requestownerother' . $id);
    $fixtures->user('nonrecipient' . $id, $group_a);
    $candidates = [
        ['ref' => $user_a, 'email' => 'owner-a@example.invalid'],
        ['ref' => $user_b, 'email' => 'owner-b@example.invalid'],
        ['ref' => $unrelated, 'email' => 'unrelated@example.invalid'],
    ];
    $recipients = array_column($candidates, 'email', 'ref');

    $node_a = set_node(null, $field, 'Ownership A', null, 10);
    $node_b = set_node(null, $field, 'Ownership B', null, 20);
    $unmapped = set_node(null, $field, 'Unmapped', null, 30);
    $GLOBALS['owner_field'] = $field;
    $GLOBALS['owner_field_mappings'] = [$node_a => $group_a, $node_b => $group_b];

    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $third = $fixtures->resource(1, 0);
    $fourth = $fixtures->resource(1, 0);
    foreach ([$first => $node_a, $second => $node_b, $third => $unmapped] as $ref => $node) {
        add_resource_nodes($ref, [$node]);
    }

    expect(get_notification_users_by_owner_field($candidates, [$first, $fourth]))
        ->toBe([$user_a => $recipients[$user_a]]);
    expect(get_notification_users_by_owner_field($candidates, [$first, $second, $fourth]))
        ->toEqual(array_intersect_key($recipients, [$user_a => true, $user_b => true]));
    expect(get_notification_users_by_owner_field($candidates, [$third, $fourth]))->toBeEmpty()
        ->and(get_notification_users_by_owner_field($candidates, [$fourth]))->toBe($recipients)
        ->and(get_notification_users_by_owner_field($candidates, []))->toBeEmpty()
        ->and(get_notification_users_by_owner_field([], [$first, $second, $third]))->toBeEmpty();

    $GLOBALS['owner_field'] = 0;
    expect(get_notification_users_by_owner_field($candidates, [$first, $second, $third]))->toBe($recipients);
});

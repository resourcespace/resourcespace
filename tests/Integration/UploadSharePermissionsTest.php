<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('requires share permission and collection upload access to create a link', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $owner = $fixtures->user('uploadowner' . $id);
    $other = $fixtures->user('uploadother' . $id);
    $own = get_collection($fixtures->collection($owner, 'Own upload collection ' . $id));
    $foreign = get_collection($fixtures->collection($other, 'Other upload collection ' . $id));
    setup_user(get_user($owner));

    $GLOBALS['userpermissions'] = ['s', 'a', 'c', 'd'];
    expect(can_share_upload_link($foreign))->toBeTrue();
    $GLOBALS['userpermissions'] = ['s', 'exup', 'c', 'd'];
    expect(can_share_upload_link($foreign))->toBeFalse()->and(can_share_upload_link($own))->toBeTrue();
    $GLOBALS['userpermissions'] = ['s', 'c', 'd'];
    expect(can_share_upload_link($own))->toBeFalse();
});

it('checks upload share ownership and purges only expired shares in scope', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $owner = $fixtures->user('shareowner' . $id);
    $other = $fixtures->user('shareother' . $id);
    $own = $fixtures->collection($owner, 'Own upload shares ' . $id);
    $foreign = $fixtures->collection($other, 'Other upload shares ' . $id);
    $group = (int) $GLOBALS['usergroup'];
    $future = date('Y-m-d', strtotime('+3 days'));
    $first = create_upload_link($own, ['usergroup' => $group, 'user' => $owner, 'expires' => $future]);
    $second = create_upload_link($own, ['usergroup' => $group, 'user' => $owner, 'expires' => $future]);
    $third = create_upload_link($foreign, ['usergroup' => $group, 'user' => $other, 'expires' => $future]);
    expect($first)->toBeArray()->and($second)->toBeArray()->and($third)->toBeArray();

    setup_user(get_user($owner));
    $GLOBALS['userpermissions'] = ['s', 'c', 'd'];
    expect(can_edit_upload_share($own, $first[0]))->toBeTrue()
        ->and(can_edit_upload_share($foreign, $third[0]))->toBeFalse();
    $GLOBALS['userpermissions'][] = 'a';
    expect(can_edit_upload_share($foreign, $third[0]))->toBeTrue();

    expect(edit_collection_external_access($third[0], -1, '', $group))->toBeTrue();
    $GLOBALS['userpermissions'] = ['s', 'c', 'd', 'ex'];
    expect(can_edit_upload_share($foreign, $third[0]))->toBeTrue();

    $filter = ['share_user' => $owner, 'share_type' => 1, 'share_collection' => $own];
    expect(get_external_shares($filter))->toHaveCount(2);
    expect(edit_collection_external_access($second[0], -1, date('Y-m-d', strtotime('-3 days')), $group))
        ->toBeTrue();
    expect((int) purge_expired_shares($filter))->toBe(1);
    expect(array_column(get_external_shares($filter), 'access_key'))->toBe([$first[0]]);
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'usergroup', 'can_set_admin_usergroup_perms_array', 'usergroup_approval_mappings',
        'notification_users_cache', 'email_notify_usergroups', 'user_select_internal',
    ]);
    unset($GLOBALS['can_set_admin_usergroup_perms_array']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('validates user edits, disables the account and removes its private dashboard tile on deletion', function () use (
    &$fixtures,
): void {
    require_once RESOURCESPACE_BASE_PATH . '/include/dash_functions.php';
    $suffix = generateRandomTestID(12);
    $admin = $fixtures->user('usereditor' . $suffix, 3);
    $name = 'editeduser' . $suffix;
    $user = $fixtures->user($name, 2);
    $sessions = [$admin => generateSecureKey(32), $user => generateSecureKey(32)];
    foreach ($sessions as $ref => $session) {
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $ref]);
    }

    $tile = create_dash_tile('', '', 'Personal dashboard fixture ' . $suffix, 0, 0, 0, 0, 'A note for the account');
    $fixtures->onCleanup(static fn () => delete_dash_tile($tile));
    add_user_dash_tile($user, $tile, 10);
    expect(ps_value('SELECT COUNT(*) AS value FROM user_dash_tile WHERE dash_tile = ?', ['i', $tile], 0))->toBe(1);

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false, 'home_dash' => true]);
    $admin_headers = $server->sessionHeaders($sessions[$admin]);
    $user_headers = $server->sessionHeaders($sessions[$user]);
    expect($server->request('/pages/home.php', headers: $user_headers)['status'])->toBe(200);

    $edit = '/pages/team/team_user_edit.php?ref=' . $user;
    $form = $server->request($edit, headers: $admin_headers);
    expect($form['status'])->toBe(200);
    $fields = LocalTestServer::hiddenFields($form['body'], '//form[contains(@action, "team_user_edit.php")]');
    expect($fields)->toHaveKeys([$GLOBALS['CSRF_token_identifier'], 'save']);
    $data = $fields + [
        'username' => $name, 'fullname' => 'Edited account', 'email' => $name . '@example.invalid',
        'password' => $GLOBALS['lang']['hidden'], 'usergroup' => 2, 'approved' => 1,
    ];
    $invalid = $server->request($edit, $data + ['account_expires' => 'not-a-date'], $admin_headers);
    $validation = str_replace('[value]', 'not-a-date', $GLOBALS['lang']['error_invalid_date_format']);
    expect($invalid['status'])->toBe(200)->and($invalid['body'])->toContain(escape($validation));
    expect(get_user($user)['email'])->not->toBe($data['email']);

    $data['account_expires'] = '2099-12-31';
    expect($server->request($edit, $data, $admin_headers)['status'])->toBe(302);
    unset($GLOBALS['udata_cache'][$user]);
    $updated = get_user($user);
    expect($updated['email'])->toBe($data['email'])->and($updated['account_expires'])->toStartWith('2099-12-31');

    $data['approved'] = 2;
    expect($server->request($edit, $data, $admin_headers)['status'])->toBe(302);
    unset($GLOBALS['udata_cache'][$user]);
    expect((int) get_user($user)['approved'])->toBe(2);
    $disabled = $server->request('/pages/home.php', headers: $user_headers);
    expect($disabled['status'])->toBe(302)
        ->and(parse_url($disabled['headers']['location'], PHP_URL_PATH))->toBe('/login.php');

    expect($server->request($edit, $fields + ['deleteme' => 'yes'], $admin_headers)['status'])->toBe(302);
    unset($GLOBALS['udata_cache'][$user]);
    expect(get_user($user))->toBeFalse()
        ->and(ps_value('SELECT COUNT(*) AS value FROM user_dash_tile WHERE dash_tile = ?', ['i', $tile], 0))->toBe(0)
        ->and(ps_value('SELECT COUNT(*) AS value FROM dash_tile WHERE ref = ?', ['i', $tile], 0))->toBe(0);
});

it('restricts delegated user management to allowed accounts and groups', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(16);
    $admin = $fixtures->group('Boundary admin ' . $suffix, 'a,u');
    $manager = $fixtures->group('Boundary manager ' . $suffix, 'u,U');
    $outside = $fixtures->group('Boundary outside ' . $suffix, 's');
    $user = $fixtures->user('boundary' . $suffix, $outside);
    $before = get_user($user);

    $GLOBALS['userpermissions'] = [];
    expect(checkperm_user_edit($user))->toBeFalse()
        ->and(checkperm_login_as_user($user))->toBeFalse();

    $GLOBALS['userpermissions'] = ['u', 'U'];
    $GLOBALS['usergroup'] = $manager;
    expect(can_set_admin_usergroup($admin))->toBeFalse()
        ->and(can_set_admin_usergroup($outside))->toBeTrue()
        ->and(checkperm_user_edit($user))->toBeFalse()
        ->and(checkperm_login_as_user(['permissions' => 's,a,u']))->toBeFalse();
    expect(save_user($user, ['fullname' => 'Disallowed change', 'usergroup' => $admin]))->not->toBeTrue();
    unset($GLOBALS['udata_cache']);
    expect(get_user($user))->toEqual($before);

    $GLOBALS['userpermissions'] = ['a', 'u'];
    expect(can_set_admin_usergroup($admin))->toBeTrue()
        ->and(checkperm_login_as_user(['permissions' => 'a']))->toBeTrue();
});

it('keeps groups with members and deletes empty groups', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(16);
    $occupied = $fixtures->group('Occupied group ' . $suffix, 's');
    $empty = $fixtures->group('Empty group ' . $suffix, 's');
    $user = $fixtures->user('groupmember' . $suffix, $occupied);

    expect(delete_usergroup($occupied))->toBeFalse()
        ->and((int) get_user($user)['usergroup'])->toBe($occupied);
    expect(delete_usergroup($empty))->toBeTrue()
        ->and(get_usergroup($empty))->toBeFalse();
});

it('finds users with all requested permissions within the caller’s group scope', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $permission = 'pest' . $suffix;
    $delegated = $fixtures->group('Delegated directory ' . $suffix, $permission . ',u,U,t,s');
    $all_groups = $fixtures->group('All groups directory ' . $suffix, $permission . ',u,t,s');
    $child = $fixtures->group('Child directory ' . $suffix, $permission . ',s');
    expect(save_usergroup($child, ['parent' => $delegated]))->not->toBeFalse();
    $manager = $fixtures->user('delegated_' . $suffix, $delegated);
    $unrestricted = $fixtures->user('unrestricted_' . $suffix, $all_groups);
    $member = $fixtures->user('child_' . $suffix, $child);
    $fixtures->user('unrelated_' . $suffix);

    setup_user(get_user($manager));
    expect(array_column(get_users_by_permission([$permission]), 'ref'))->toEqualCanonicalizing([$manager, $member]);
    setup_user(get_user($unrestricted));
    expect(array_column(get_users_by_permission([$permission]), 'ref'))
        ->toEqualCanonicalizing([$manager, $unrestricted, $member]);
    expect(array_column(get_users_by_permission([$permission, 'u', 'U', 't', 's']), 'ref'))->toBe([$manager]);
    setup_user(get_user($member));
    expect(get_users_by_permission([$permission]))->toBeEmpty();
});

it('filters the directory by approval, user group and delegated approval mappings', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $supervisors = $fixtures->group('Approval supervisors ' . $suffix, 's,t,u');
    $subordinates = $fixtures->group('Approval subordinates ' . $suffix, 's');
    $independent = $fixtures->group('Approval independent ' . $suffix, 's,t,u');
    $manager = $fixtures->user('directory_manager_' . $suffix, $supervisors);
    $subordinate = $fixtures->user('directory_subordinate_' . $suffix, $subordinates);
    $other = $fixtures->user('directory_other_' . $suffix, $independent);
    $pending = $fixtures->user('directory_pending_' . $suffix, $independent);
    $scope = [$manager, $subordinate, $other, $pending];
    ps_query(
        'UPDATE user SET approved = 1 WHERE ref IN (?, ?, ?)',
        ['i', $manager, 'i', $subordinate, 'i', $other]
    );
    ps_query('UPDATE user SET approved = 0 WHERE ref = ?', ['i', $pending]);
    $eligible = static fn (array $users): array => array_values(array_intersect(array_column($users, 'ref'), $scope));
    expect($eligible(get_users(0, $suffix, 'u.username', false, -1, 1)))
        ->toEqualCanonicalizing([$manager, $subordinate, $other]);
    expect($eligible(get_users($independent, $suffix)))->toEqualCanonicalizing([$other, $pending]);
    expect($eligible(get_users("{$supervisors},{$subordinates}", $suffix)))
        ->toEqualCanonicalizing([$manager, $subordinate]);

    $GLOBALS['email_notify_usergroups'] = [];
    $GLOBALS['usergroup_approval_mappings'] = [$supervisors => [$subordinates]];
    setup_user(get_user($manager));
    expect($eligible(get_users(0, $suffix, 'u.username', true)))->toEqualCanonicalizing([$manager, $subordinate]);
    setup_user(get_user($other));
    expect($eligible(get_users(0, $suffix, 'u.username', true)))->toEqualCanonicalizing($scope);

    $GLOBALS['notification_users_cache'] = [];
    expect($eligible(get_notification_users('USER_ADMIN', $independent)))->not->toContain($manager);
    unset($GLOBALS['usergroup_approval_mappings']);
    $GLOBALS['notification_users_cache'] = [];
    expect($eligible(get_notification_users('USER_ADMIN', $independent)))->toContain($manager);
});

it('resolves internal usernames and external addresses and skips ineligible internal recipients', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $active_name = 'mail_active_' . $suffix;
    $pending_name = 'mail_pending_' . $suffix;
    $expired_name = 'mail_expired_' . $suffix;
    $invalid_name = 'mail_invalid_' . $suffix;
    $active = $fixtures->user($active_name);
    $pending = $fixtures->user($pending_name);
    $expired = $fixtures->user($expired_name);
    $invalid = $fixtures->user($invalid_name);
    $email = $active_name . '@example.invalid';
    foreach ([
        [$active, $email, 1, null],
        [$pending, $pending_name . '@example.invalid', 0, null],
        [$expired, $expired_name . '@example.invalid', 1, '2000-01-01'],
        [$invalid, 'invalid@localhost', 1, null],
    ] as [$user, $address, $approved, $expiry]) {
        ps_query(
            'UPDATE user SET email = ?, approved = ?, account_expires = ? WHERE ref = ?',
            ['s', $address, 'i', $approved, 's', $expiry, 'i', $user]
        );
    }
    $GLOBALS['user_select_internal'] = false;
    expect(resolve_user_emails([$active_name]))->toBe([
        'unames' => [$active_name], 'emails' => [$email], 'refs' => [$active], 'key_required' => [false],
    ]);
    expect(resolve_user_emails([$email]))->toBe([
        'unames' => [$email], 'emails' => [$email], 'key_required' => [true],
    ]);
    expect(resolve_user_emails([$pending_name, $expired_name, $invalid_name]))->toBeEmpty();
});

it('uses parent permissions only while the child group inherits them', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $parent = $fixtures->group('Parent permissions ' . $id, 's,v');
    $child = $fixtures->group('Child permissions ' . $id, 's,e0');
    $user = $fixtures->user('child_perms_' . $id, $child);

    expect(save_usergroup($child, ['parent' => $parent, 'inherit_flags' => 'permissions']))->toBeTrue();
    unset($GLOBALS['udata_cache'][$user]);
    setup_user(get_user($user));
    expect(checkperm('s'))->toBeTrue()
        ->and(checkperm('v'))->toBeTrue()
        ->and(checkperm('e0'))->toBeFalse();

    expect(save_usergroup($child, ['inherit_flags' => '']))->toBeTrue();
    unset($GLOBALS['udata_cache'][$user]);
    setup_user(get_user($user));
    expect(checkperm('s'))->toBeTrue()
        ->and(checkperm('v'))->toBeFalse()
        ->and(checkperm('e0'))->toBeTrue();
});

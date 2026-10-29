<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['userpermissions'];
    $saved_group = $GLOBALS['usergroup'];
    $cached = $GLOBALS['can_set_admin_usergroup_perms_array'] ?? null;
    $groups = [];
    $user = 0;
    try {
        $admin = save_usergroup(0, ['name' => 'Boundary admin', 'permissions' => 'a,u']);
        $groups[] = $admin;
        $manager = save_usergroup(0, ['name' => 'Boundary manager', 'permissions' => 'u,U']);
        $groups[] = $manager;
        $outside = save_usergroup(0, ['name' => 'Boundary outside', 'permissions' => 's']);
        $groups[] = $outside;
        $user = new_user('boundary_' . test_generate_random_ID(8), $outside);
        unset($GLOBALS['can_set_admin_usergroup_perms_array']);
        $GLOBALS['userpermissions'] = [];
        $check(!checkperm_user_edit($user) && !checkperm_login_as_user($user), 'User without administration permissions can manage accounts');
        $GLOBALS['userpermissions'] = ['u', 'U'];
        $GLOBALS['usergroup'] = $manager;
        $check(!can_set_admin_usergroup($admin), 'Non-admin can select admin group');
        $check(can_set_admin_usergroup($outside), 'Ordinary target group rejected');
        $check(!checkperm_user_edit($user), 'Manager can edit account outside scope');
        $check(!checkperm_login_as_user(['permissions' => 's,a,u']), 'Non-admin can impersonate privileged account');
        $before = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]);
        $check(save_user($user, ['fullname' => 'Unauthorized change', 'usergroup' => $admin]) !== true, 'Out-of-scope save accepted');
        $check(ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]) === $before, 'Rejected account edit persisted changes');
        $check(delete_usergroup($outside) === false, 'Group with members deleted');
        $check(ps_value('SELECT usergroup value FROM user WHERE ref = ?', ['i', $user], 0) == $outside, 'Rejected group deletion changed membership');
        $GLOBALS['userpermissions'] = ['a', 'u'];
        $check(can_set_admin_usergroup($admin) && checkperm_login_as_user(['permissions' => 'a']), 'Admin management rejected');
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        $check(delete_usergroup($outside), 'Empty group deletion rejected');
        $check(ps_value('SELECT COUNT(*) value FROM usergroup WHERE ref = ?', ['i', $outside], -1) == 0, 'Deleted group retained');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
        $GLOBALS['usergroup'] = $saved_group;
        if ($cached === null) {
            unset($GLOBALS['can_set_admin_usergroup_perms_array']);
        } else {
            $GLOBALS['can_set_admin_usergroup_perms_array'] = $cached;
        }
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        foreach ($groups as $group) {
            delete_usergroup($group);
        }
    }
})();

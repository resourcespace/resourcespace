<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $names = ['userref', 'username', 'userpassword', 'password_min_length', 'password_min_alpha', 'password_min_numeric', 'password_min_uppercase', 'password_min_special'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = $GLOBALS[$name] ?? null;
    }
    $username = 'password_lifecycle_' . test_generate_random_ID(6);
    $user = new_user($username);
    try {
        $old = rs_password_hash('RS' . $username . 'OriginalPassword1');
        ps_query('UPDATE user SET password = ?, login_tries = 4 WHERE ref = ?', ['s', $old, 'i', $user]);
        $GLOBALS['userref'] = $user;
        $GLOBALS['username'] = $username;
        $GLOBALS['userpassword'] = $old;
        foreach (['password_min_length' => 12, 'password_min_alpha' => 1, 'password_min_numeric' => 1,
            'password_min_uppercase' => 1, 'password_min_special' => 1] as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        $reset = create_password_reset_key($username);
        $row = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user])[0];
        $check(strlen($reset) === 15 && $row['password_reset_hash'] !== '', 'Reset key not issued');
        $check($reset === substr(hash('sha256', date('Ymd') . $row['password_reset_hash'] . $username . $GLOBALS['scramble_key']), 0, 15), 'Reset key does not match stored credential');
        $replacement = create_password_reset_key($username);
        $check($reset !== $replacement, 'Reset issuance reused previous key');
        foreach (['short', 'onlylowercaseletters', 'NoNumericOrSpecial', 'NoSpecialCharacter1'] as $invalid) {
            $before = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]);
            $check(change_password($invalid) !== true, 'Password policy accepted invalid password');
            $check(ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]) === $before, 'Rejected password changed account');
        }
        $check(change_password('ReplacementPassword2!') === true, 'Valid password change rejected');
        $row = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user])[0];
        $check(rs_password_verify('ReplacementPassword2!', $row['password'], ['username' => $username]), 'New password not persisted');
        $check(!rs_password_verify('OriginalPassword1', $row['password'], ['username' => $username]), 'Old password still verifies');
        $check($row['password_reset_hash'] === null && $row['login_tries'] == 0 && !empty($row['password_last_change']), 'Password change did not clear reset credential/lockout or set change date');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
    }
})();

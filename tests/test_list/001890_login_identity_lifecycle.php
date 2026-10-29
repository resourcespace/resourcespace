<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/login_functions.php';
    $names = ['username', 'password', 'password_hash', 'session_hash', 'usergroup', 'randomised_session_hash'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = $GLOBALS[$name] ?? null;
    }
    $name = 'login_identity_' . test_generate_random_ID(6);
    $user = new_user($name);
    try {
        $hash = rs_password_hash('RS' . $name . 'LoginPassword1');
        ps_query('UPDATE user SET password = ?, approved = 1, login_tries = 0 WHERE ref = ?', ['s', $hash, 'i', $user]);
        $GLOBALS['randomised_session_hash'] = true;
        $login = perform_login($name, 'LoginPassword1');
        $check($login['valid'] && $login['ref'] == $user, 'Login did not identify fixture user');
        $session = ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '');
        $check($session !== '' && $session === $login['session_hash'], 'Login session not persisted');
        $second = perform_login($name, 'LoginPassword1');
        $check($second['valid'] && $second['session_hash'] !== $session, 'New randomized login reused old session');
        $before = ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '');
        $check(!perform_login($name, 'WrongPassword2')['valid'], 'Wrong password logged in');
        $check(ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '') === $before, 'Rejected login changed authenticated session');
        ps_query('UPDATE user SET approved = 2 WHERE ref = ?', ['i', $user]);
        unset($GLOBALS['udata_cache']);
        $check(!perform_login($name, 'LoginPassword1')['valid'], 'Disabled account logged in');
        ps_query('UPDATE user SET approved = 1, account_expires = NOW() - INTERVAL 1 DAY WHERE ref = ?', ['i', $user]);
        unset($GLOBALS['udata_cache']);
        $check(!perform_login($name, 'LoginPassword1')['valid'], 'Expired account logged in');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        unset($GLOBALS['udata_cache']);
    }
})();

<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $resource = create_resource(1, 0);
    $keys = [];
    try {
        foreach (['one', 'two'] as $label) {
            $key = test_generate_random_ID(5);
            $keys[] = $key;
            ps_query('INSERT INTO external_access_keys (resource,access_key,user,access,password_hash) VALUES (?,?,?,?,?)',
                ['i', $resource, 's', $key, 'i', $GLOBALS['userref'], 'i', 0,
                's', hash('sha256', $key . 'SharePassword1' . $GLOBALS['scramble_key'])]);
        }
        [$first, $second] = $keys;
        $hash = hash('sha256', $first . 'SharePassword1' . $GLOBALS['scramble_key']);
        $cookie = hash('sha256', date('Ymd') . $first . $hash . $GLOBALS['scramble_key']);
        $check(check_share_password($first, 'SharePassword1', 'unused'), 'Correct share password rejected');
        $check(!check_share_password($first, 'wrong', 'unused'), 'Wrong password accepted');
        $check(!check_share_password($first, '', ''), 'Empty password/cookie accepted');
        $check(check_share_password($first, '', $cookie), 'Valid share cookie rejected');
        $check(!check_share_password($second, '', $cookie), 'Cookie accepted for another share');
        ps_query('UPDATE external_access_keys SET password_hash = ? WHERE access_key = ?',
            ['s', hash('sha256', $first . 'Replacement2' . $GLOBALS['scramble_key']), 's', $first]);
        $check(!check_share_password($first, '', $cookie), 'Old cookie survived password change');
        $check(!check_share_password($first, 'SharePassword1', 'unused'), 'Old password survived change');
        $check(check_share_password($first, 'Replacement2', 'unused'), 'Replacement password rejected');
        ps_query('DELETE FROM external_access_keys WHERE access_key = ?', ['s', $first]);
        $check(!check_share_password($first, 'Replacement2', 'unused'), 'Revoked share accepted password');
        $check(!check_share_password($first, '', $cookie), 'Revoked share accepted old cookie');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($keys as $key) {
            ps_query('DELETE FROM external_access_keys WHERE access_key = ?', ['s', $key]);
        }
    }
})();

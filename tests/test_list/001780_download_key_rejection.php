<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved_session = $GLOBALS['usersession'] ?? '';
    $user = new_user('download_negative_' . test_generate_random_ID(8));
    $resource = create_resource(1, 0);
    try {
        $key = generate_temp_download_key($user, $resource, 'pre');
        $check($key !== '' && validate_temp_download_key($resource, $key, 'pre', 3600, false), 'Valid temporary key rejected');
        $check(!validate_temp_download_key($resource + 1, $key, 'pre', 3600, false), 'Wrong resource accepted');
        $check(!validate_temp_download_key($resource, $key, 'thm', 3600, false), 'Wrong size accepted');
        $check(!validate_temp_download_key($resource, 'tampered', 'pre', 3600, false), 'Tampered key accepted');
        $expired = base64_encode(rsEncrypt($user . ':' . $resource . ':pre:' . (time() - 7200),
            hash_hmac('sha256', 'dld_key', $GLOBALS['scramble_key']), 32));
        $check(!validate_temp_download_key($resource, $expired, 'pre', 3600, false), 'Expired key accepted');
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        unset($GLOBALS['udata_cache']);
        $check(!validate_temp_download_key($resource, $key, 'pre', 3600, false), 'Deleted user key accepted');
        $GLOBALS['usersession'] = 'download_session_one';
        $link = download_link_generate_key($GLOBALS['userref'], $resource);
        $check(download_link_check_key($link, $resource), 'Valid session download link rejected');
        $check(!download_link_check_key($link, $resource + 1), 'Session link accepted for another resource');
        $GLOBALS['usersession'] = 'download_session_two';
        $check(!download_link_check_key($link, $resource), 'Session link survived session change');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $user]);
        $GLOBALS['usersession'] = $saved_session;
    }
})();

<?php

command_line_only();

return (static function () {
    global $userdata, $userref, $restricted_full_download, $open_access_for_contributor;

    $saved_user = $userdata;
    $saved_restricted_download = $restricted_full_download;
    $saved_contributor_access = $open_access_for_contributor;
    $resource = create_resource(1, 0);
    $id = test_generate_random_ID(8);
    $group = save_usergroup(0, ['name' => 'Download access ' . $id, 'permissions' => 's,g,f*']);
    $reader = new_user('downloadreader' . $id, $group);

    try {
        setup_user(get_user($reader));
        $open_access_for_contributor = false;
        $cases = [
            [RESOURCE_ACCESS_FULL, false, '', true],
            [RESOURCE_ACCESS_RESTRICTED, false, '', false],
            [RESOURCE_ACCESS_RESTRICTED, true, '', true],
            [RESOURCE_ACCESS_RESTRICTED, false, 'thm', true],
            [RESOURCE_ACCESS_CONFIDENTIAL, true, '', false],
            [RESOURCE_ACCESS_CONFIDENTIAL, true, 'thm', false],
        ];
        foreach ($cases as [$access, $allow_original, $size, $expected]) {
            ps_query('UPDATE resource SET access = ? WHERE ref = ?', ['i', $access, 'i', $resource]);
            $GLOBALS['get_resource_data_cache'] = [];
            $GLOBALS['resource_access_cache'] = [];
            $GLOBALS['download_access_cache'] = [];
            $restricted_full_download = $allow_original;
            if (resource_download_allowed($resource, $size, 1) !== $expected) {
                echo "Download access mismatch: access={$access}, size='{$size}', restricted_full_download=" . (int) $allow_original . ' - ';
                return false;
            }
        }

        // A specific user grant opens a confidential original; expiration removes that access.
        ps_query('INSERT INTO resource_custom_access (resource, user, access, user_expires) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))',
            ['i', $resource, 'i', $userref, 'i', RESOURCE_ACCESS_FULL]);
        $GLOBALS['resource_access_cache'] = [];
        if (resource_download_allowed($resource, '', 1) !== true) {
            echo 'Valid user grant did not allow download - ';
            return false;
        }
        ps_query('UPDATE resource_custom_access SET user_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE resource = ? AND user = ?',
            ['i', $resource, 'i', $userref]);
        $GLOBALS['resource_access_cache'] = [];
        if (resource_download_allowed($resource, '', 1) !== false) {
            echo 'Expired user grant still allowed download - ';
            return false;
        }
        return true;
    } finally {
        setup_user($saved_user);
        $restricted_full_download = $saved_restricted_download;
        $open_access_for_contributor = $saved_contributor_access;
        $GLOBALS['get_resource_data_cache'] = [];
        $GLOBALS['resource_access_cache'] = [];
        $GLOBALS['download_access_cache'] = [];
    }
})();

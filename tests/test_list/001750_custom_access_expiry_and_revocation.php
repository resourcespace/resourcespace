<?php

command_line_only();

return (static function () {
    global $userref, $usergroup, $custom_access;

    $saved_custom_access = $custom_access;
    try {
        $custom_access = true;
        $resource = create_resource(1, 0);
        $control = create_resource(1, 0);
        foreach ([$resource, $control] as $ref) {
            ps_query('INSERT INTO resource_custom_access (resource, usergroup, access) VALUES (?, ?, ?)',
                ['i', $ref, 'i', $usergroup, 'i', RESOURCE_ACCESS_RESTRICTED]);
            ps_query('INSERT INTO resource_custom_access (resource, user, access, user_expires) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))',
                ['i', $ref, 'i', $userref, 'i', RESOURCE_ACCESS_FULL]);
        }
        if (get_custom_access_user($resource, $userref) !== RESOURCE_ACCESS_FULL) {
            echo 'Unexpired user grant was not returned - ';
            return false;
        }
        ps_query('UPDATE resource_custom_access SET user_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE resource = ? AND user = ?',
            ['i', $resource, 'i', $userref]);
        if (get_custom_access_user($resource, $userref) !== false
            || get_custom_access_user($control, $userref) !== RESOURCE_ACCESS_FULL) {
            echo 'Expired grant remained valid or affected another resource - ';
            return false;
        }
        delete_resource_custom_access_usergroups($resource);
        if (get_custom_access($resource, $usergroup, false) !== false
            || get_custom_access($resource, $usergroup) !== RESOURCE_ACCESS_CONFIDENTIAL
            || get_custom_access($control, $usergroup) !== RESOURCE_ACCESS_RESTRICTED
            || ps_value('SELECT COUNT(*) AS value FROM resource_custom_access WHERE resource = ? AND user = ?',
                ['i', $resource, 'i', $userref], 0) != 1) {
            echo 'Revoking group access affected user grants or another resource - ';
            return false;
        }
        delete_resource_custom_user_access($resource, $userref);
        if (ps_value('SELECT COUNT(*) AS value FROM resource_custom_access WHERE resource = ?', ['i', $resource], 0) != 0
            || get_custom_access_user($control, $userref) !== RESOURCE_ACCESS_FULL) {
            echo 'Revoking user access left a grant or affected another resource - ';
            return false;
        }
        return true;
    } finally {
        $custom_access = $saved_custom_access;
    }
})();

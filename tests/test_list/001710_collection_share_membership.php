<?php

command_line_only();

return (static function () {
    global $userref, $anonymous_login, $collection_share_warning;

    $saved_anonymous = $anonymous_login;
    $saved_warning = $collection_share_warning ?? false;
    try {
        $anonymous_login = '';
        $collection = create_collection($userref, 'Shared membership ' . test_generate_random_ID(8));
        $first = create_resource(1, 0);
        $second = create_resource(1, 0);
        if (add_resource_to_collection($first, $collection) !== true) {
            echo 'Shared membership setup - ';
            return false;
        }
        $key = generate_collection_access_key($collection, 0, '', 0);
        $rows = static function ($resource) use ($collection, $key) {
            return ps_value('SELECT COUNT(*) AS value FROM external_access_keys WHERE collection = ? AND resource = ? AND access_key = ?',
                ['i', $collection, 'i', $resource, 's', $key], 0);
        };
        if (!is_string($key) || $key === '' || $rows($first) != 1
            || add_resource_to_collection($second, $collection) !== true || $rows($second) != 1) {
            echo 'Adding to a shared collection did not propagate the existing key - ';
            return false;
        }

        // Removing collection access must not revoke an independent resource share.
        $direct_key = generate_resource_access_key($second, $userref, 0, '', '');
        if (!is_string($direct_key) || $direct_key === '') {
            echo 'Independent resource share setup failed - ';
            return false;
        }
        if (remove_resource_from_collection($second, $collection) !== true
            || $rows($second) != 0 || $rows($first) != 1
            || ps_value('SELECT COUNT(*) AS value FROM external_access_keys WHERE resource = ? AND access_key = ?',
                ['i', $second, 's', $direct_key], 0) != 1) {
            echo 'Removing membership did not revoke only the collection share - ';
            return false;
        }
        delete_collection_access_key($collection, $key);
        if ($rows($first) != 0 || get_resource_data($first, false) === false
            || !match_values(get_collection_resources($collection), [$first])) {
            echo 'Revoking a collection key removed resources or retained access - ';
            return false;
        }
        return true;
    } finally {
        $anonymous_login = $saved_anonymous;
        $collection_share_warning = $saved_warning;
    }
})();

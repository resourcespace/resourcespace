<?php

command_line_only();

return (static function () {
    global $userref, $userdata, $anonymous_login;

    $saved_user = $userdata;
    $saved_anonymous = $anonymous_login;
    try {
        $anonymous_login = '';
        $id = test_generate_random_ID(8);
        $source = create_collection($userref, 'Copy source ' . $id);
        $target = create_collection($userref, 'Copy target ' . $id);
        $first = create_resource(1, 0);
        $second = create_resource(1, 0);
        $extra = create_resource(1, 0);
        if (add_resource_to_collection($first, $source, false, '', '', null, null, '', 10) !== true
            || add_resource_to_collection($second, $source, false, '', '', null, null, '', 20) !== true
            || add_resource_to_collection($extra, $target) !== true) {
            echo 'Collection copy setup - ';
            return false;
        }
        copy_collection($source, $target);
        if (!match_values(get_collection_resources($target), [$first, $second, $extra])) {
            echo 'Collection merge lost existing membership - ';
            return false;
        }
        copy_collection($source, $target, true);
        if (get_collection_resources($target) != [$first, $second]
            || get_collection_resources($source) != [$first, $second]) {
            echo 'Collection replacement lost ordering or changed the source - ';
            return false;
        }
        $key = generate_collection_access_key($target, 0, '', 0);
        if (!is_string($key) || $key === '') {
            echo 'Collection deletion share setup - ';
            return false;
        }
        setup_user(get_user(new_user('deletecollection' . $id, 2)));
        if (delete_collection($target) !== false || get_collection($target) === false) {
            echo 'Non-owner deleted a private collection - ';
            return false;
        }
        setup_user($saved_user);
        delete_collection($target); // Successful deletion returns void.
        if (get_collection($target) !== false || get_collection_resources($target) !== []
            || ps_value('SELECT COUNT(*) AS value FROM external_access_keys WHERE collection = ?', ['i', $target], 0) != 0
            || get_collection_resources($source) != [$first, $second]
            || get_resource_data($extra, false) === false || get_resource_data($first, false) === false) {
            echo 'Deleting a collection left shares or membership, or deleted its resources - ';
            return false;
        }
        return true;
    } finally {
        setup_user($saved_user);
        $anonymous_login = $saved_anonymous;
    }
})();

<?php

command_line_only();

return (static function () {
    global $userref;

    $id = test_generate_random_ID(8);
    $field = create_resource_type_field('Rejected edits ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'rejected' . $id);
    $resource = create_resource(1, 0);
    $original = 'Preserve this value';
    $errors = [];
    if (!update_field($resource, $field, $original, $errors)) {
        echo 'Rejected edits setup - ';
        return false;
    }

    ps_query('UPDATE resource_type_field SET required = 1 WHERE ref = ?', ['i', $field]);
    clear_query_cache('schema');
    $errors = [];
    if (update_field($resource, $field, '', $errors) !== false || $errors === []
        || get_data_by_field($resource, $field) !== $original) {
        echo 'Required field accepted an empty value or lost its previous value - ';
        return false;
    }

    $other = new_user('lockowner' . $id, 2);
    try {
        ps_query('UPDATE resource SET lock_user = ? WHERE ref = ?', ['i', $other, 'i', $resource]);
        $GLOBALS['get_resource_data_cache'] = [];
        $errors = [];
        $logs = count(get_resource_log($resource)['data']);
        if (update_field($resource, $field, 'Forbidden replacement', $errors) !== false || $errors === []
            || get_data_by_field($resource, $field) !== $original
            || count(get_resource_log($resource)['data']) !== $logs) {
            echo 'An edit to another user\'s locked resource changed metadata or logs - ';
            return false;
        }

        // The lock owner can still update metadata.
        ps_query('UPDATE resource SET lock_user = ? WHERE ref = ?', ['i', $userref, 'i', $resource]);
        $GLOBALS['get_resource_data_cache'] = [];
        $errors = [];
        if (!update_field($resource, $field, 'Owner replacement', $errors)
            || $errors !== [] || get_data_by_field($resource, $field) !== 'Owner replacement') {
            echo 'Lock owner could not update metadata - ';
            return false;
        }

        return true;
    } finally {
        ps_query('UPDATE resource SET lock_user = NULL WHERE ref = ?', ['i', $resource]);
        $GLOBALS['get_resource_data_cache'] = [];
    }
})();

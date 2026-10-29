<?php

command_line_only();

return (static function () {
    global $userpermissions;

    $id = test_generate_random_ID(8);
    $field = create_resource_type_field('Copy isolation ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'copy' . $id, true);
    $source = create_resource(1, 0, -1, '', 'txt', false);
    $errors = [];
    if (!update_field($source, $field, 'Original value', $errors)) {
        echo 'Copy isolation setup - ';
        return false;
    }

    $count = ps_value('SELECT COUNT(*) AS value FROM resource', [], 0);
    $missing = ps_value('SELECT MAX(ref) + 1 AS value FROM resource', [], 1);
    if (copy_resource($missing) !== false || ps_value('SELECT COUNT(*) AS value FROM resource', [], 0) != $count) {
        echo 'Copying a missing resource created a record - ';
        return false;
    }

    $copy = copy_resource($source);
    $data = get_resource_data($copy, false);
    if ($copy == $source || $data === false || (int) $data['no_file'] !== 1
        || get_data_by_field($copy, $field) !== 'Original value') {
        echo 'Resource copy did not initialise independently - ';
        return false;
    }
    if (!update_field($copy, $field, 'Copy value', $errors)
        || get_data_by_field($source, $field) !== 'Original value') {
        echo 'Editing copied metadata changed the source - ';
        return false;
    }
    if (!update_field($source, $field, '', $errors) || get_data_by_field($copy, $field) !== 'Copy value') {
        echo 'Clearing source metadata changed the copy - ';
        return false;
    }

    // Copying must respect metadata exclusions and field permissions.
    $omitted = create_resource_type_field('Omitted copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'omit' . $id);
    $hidden = create_resource_type_field('Hidden copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'hidden' . $id);
    $readonly = create_resource_type_field('Uneditable copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'uneditable' . $id);
    foreach ([$omitted, $hidden, $readonly] as $ref) {
        if (!update_field($source, $ref, 'Do not copy', $errors)) {
            echo 'Excluded copy fields setup - ';
            return false;
        }
    }
    ps_query('UPDATE resource_type_field SET omit_when_copying = 1 WHERE ref = ?', ['i', $omitted]);
    clear_query_cache('schema');
    $saved_permissions = $userpermissions;
    try {
        $userpermissions[] = 'f-' . $hidden;
        $userpermissions[] = 'F' . $readonly;
        $filtered_copy = copy_resource($source);
        if (get_resource_data($filtered_copy, false) === false) {
            echo 'Filtered resource copy failed - ';
            return false;
        }
        foreach ([$omitted, $hidden, $readonly] as $ref) {
            if (get_data_by_field($filtered_copy, $ref) !== '' || get_data_by_field($source, $ref) !== 'Do not copy') {
                echo 'Copy retained excluded metadata or changed its source - ';
                return false;
            }
        }
    } finally {
        $userpermissions = $saved_permissions;
    }

    return $errors === [];
})();

<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $names = ['multiple', 'is_template', 'upload_review_mode', 'check_edit_checksums', 'userpermissions', 'uploadparams'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = $GLOBALS[$name] ?? null;
    }
    $get = $_GET;
    $post = $_POST;
    try {
        $GLOBALS['multiple'] = false;
        $GLOBALS['is_template'] = false;
        $GLOBALS['upload_review_mode'] = false;
        $GLOBALS['uploadparams'] = [];
        $resource = create_resource(1, 0);
        $field = create_resource_type_field('Edit form fixture', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'editformfixture', true);
        ps_query('UPDATE resource_type_field SET required = 1 WHERE ref = ?', ['i', $field]);
        clear_query_cache('schema');
        $_GET = [];
        $_POST = ['autosave_field' => $field, 'field_' . $field => 'Initial searchable value', 'field_' . $field . '_displayed' => 'block'];
        $check(process_edit_form($resource, get_resource_data($resource, false)) === true, 'Valid edit form submission failed');
        $check(get_data_by_field($resource, $field, true) === 'Initial searchable value', 'Form edit did not persist');
        $_POST['field_' . $field] = '';
        $result = process_edit_form($resource, get_resource_data($resource, false));
        $check(is_array($result) && isset($result[$field]), 'Required form field accepted empty value');
        $check(get_data_by_field($resource, $field, true) === 'Initial searchable value', 'Invalid form edit cleared required value');
        $_POST['field_' . $field] = 'Conflicting edit';
        $_POST['field_' . $field . '_checksum'] = md5('outdated form value');
        $result = process_edit_form($resource, get_resource_data($resource, false));
        $check(is_array($result) && isset($result[$field]), 'Stale form checksum accepted');
        $check(get_data_by_field($resource, $field, true) === 'Initial searchable value', 'Stale form overwrote persisted value');
        unset($_POST['field_' . $field . '_checksum']);
        $GLOBALS['userpermissions'] = array_merge($saved['userpermissions'], ['F' . $field]);
        $_POST['field_' . $field] = 'Unauthorized value';
        process_edit_form($resource, get_resource_data($resource, false));
        $check(get_data_by_field($resource, $field, true) === 'Initial searchable value', 'Form edited forbidden metadata field');
        $GLOBALS['userpermissions'] = ['e0'];
        $_POST = ['autosave_field' => 'Status', 'status' => '-1'];
        process_edit_form($resource, get_resource_data($resource, false));
        $check(get_resource_data($resource, false)['archive'] == 0, 'Form applied forbidden workflow transition');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        $_GET = $get;
        $_POST = $post;
    }
})();

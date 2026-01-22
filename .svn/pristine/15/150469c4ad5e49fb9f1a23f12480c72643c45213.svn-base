<?php
command_line_only();

$resource_ref = create_resource(1, -2, $userref, '');

$field_ref = create_resource_type_field('Required field', 1);
ps_query('UPDATE resource_type_field SET `required` = 1 WHERE ref = ?;', array('i', $field_ref));

# Manually add entry to table - not done automatically during test initialisation.
ps_query('INSERT INTO archive_states (code, name, skip_required_fields) VALUES (-1, \'Pending review\', 0)');
$row = sql_insert_id();
clear_query_cache("workflow");

$required_fields = ps_array('SELECT ref AS `value` FROM resource_type_field WHERE `required` = 1;');


# 1. Attempt to move resource with blank required field to pending review state.
$result = update_archive_required_fields_check($resource_ref, -1);
if (count($result) != count($required_fields)) {
    echo 'Test 1 ';
    return false;
}

# 2. Attempt to move resource with blank required field to deleted state.
unset($GLOBALS['update_archive_required_fields_check'][$resource_ref]);
$result = update_archive_required_fields_check($resource_ref, $resource_deletion_state);
if (count($result) != 0) {
    echo 'Test 2 ';
    return false;
}

# 3. Attempt to move resource with blank required field to pending submission state.
unset($GLOBALS['update_archive_required_fields_check'][$resource_ref]);
$result = update_archive_required_fields_check($resource_ref, -2);
if (count($result) != 0) {
    echo 'Test 3 ';
    return false;
}

# 4. Exclude pending review state from required field checking and try again.
ps_query('UPDATE archive_states SET skip_required_fields = 1 WHERE code = -1;');
unset($GLOBALS['update_archive_required_fields_check'][$resource_ref]);
unset($GLOBALS['update_archive_required_fields_check_hook'][-1]);
clear_query_cache("workflow");
$result = update_archive_required_fields_check($resource_ref, -1);
if (count($result) != 0) {
    echo 'Test 4 ';
    return false;
}

# 5. Set required fields and check again moving to pending review where it requires no missed required fields.
ps_query('UPDATE archive_states SET skip_required_fields = 0 WHERE code = -1;');
unset($GLOBALS['update_archive_required_fields_check'][$resource_ref]);
clear_query_cache("workflow");
foreach($required_fields as $field) {
    update_field($resource_ref, $field, 'test');
}
$result = update_archive_required_fields_check($resource_ref, -1);
if (count($result) != 0) {
    echo 'Test 5 ';
    return false;
}

ps_query('DELETE FROM archive_states WHERE ref = ?;', array('i', $row));
clear_query_cache("workflow");
delete_resource_type_field($field_ref);
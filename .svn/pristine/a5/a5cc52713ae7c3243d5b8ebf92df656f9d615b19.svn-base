<?php

command_line_only();


if (!db_use_multiple_connection_modes()) {
    echo "INFO: System NOT configured for read-only connection mode! - ";
    return true;
}
db_clear_connection_mode();


// During a DB transaction, the connection mode should be forced to RW automatically by db_begin_transaction().
db_begin_transaction('test_111');
if (db_get_connection_mode() !== 'read_write') {
    echo 'Force RW connection with db_begin_transaction() - ';
    return false;
}
$new_ref = create_resource(1, 0);
$resource_data = get_resource_data($new_ref, false);
db_end_transaction('test_111');
if ($resource_data === false) {
    echo 'Use RW connection mode while reading data (inside transaction) - ';
    return false;
}

// Allow resetting connection mode after db_end_transaction
db_begin_transaction('test_111');
$new_ref = create_resource(1, 0);
db_end_transaction('test_111');
db_set_connection_mode('read_only');
if (db_get_connection_mode() !== 'read_only') {
    echo 'Allow setting new connection mode after db_end_transaction() - ';
    return false;
}

// Allow resetting connection mode after db_rollback_transaction
db_begin_transaction('test_111');
$new_ref = create_resource(1, 0);
db_rollback_transaction('test_111');
db_set_connection_mode('read_only');
if (db_get_connection_mode() !== 'read_only') {
    echo 'Allow setting new connection mode after db_rollback_transaction() - ';
    return false;
}


// Teardown
unset($new_ref, $resource_data);
db_clear_connection_mode();

return true;

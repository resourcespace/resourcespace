<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['use_db_transaction'];
    $key = 'transaction_test_' . test_generate_random_ID(8);
    $other = $key . '_insert';
    try {
        $GLOBALS['use_db_transaction'] = true;
        $value = static fn ($name) => ps_value('SELECT value FROM sysvars WHERE name = ?', ['s', $name], null);
        $check(db_begin_transaction('contents_commit'), 'Could not begin transaction');
        ps_query('INSERT INTO sysvars (name,value) VALUES (?,?)', ['s', $key, 's', 'original']);
        $check(db_end_transaction('contents_commit'), 'Commit failed');
        $check($value($key) === 'original', 'Committed insert not persisted');
        $check(db_begin_transaction('contents_rollback'), 'Could not begin rollback transaction');
        ps_query('UPDATE sysvars SET value = ? WHERE name = ?', ['s', 'changed', 's', $key]);
        ps_query('INSERT INTO sysvars (name,value) VALUES (?,?)', ['s', $other, 's', 'new']);
        $check($value($key) === 'changed' && $value($other) === 'new', 'Writes not visible within transaction');
        $check(db_rollback_transaction('contents_rollback'), 'Rollback failed');
        $check($value($key) === 'original' && $value($other) === null, 'Rollback retained insert/update');
        db_begin_transaction('delete_rollback');
        ps_query('DELETE FROM sysvars WHERE name = ?', ['s', $key]);
        $check($value($key) === null, 'Delete not visible within transaction');
        db_rollback_transaction('delete_rollback');
        $check($value($key) === 'original', 'Rollback did not restore deleted row');
        db_begin_transaction('update_commit');
        ps_query('UPDATE sysvars SET value = ? WHERE name = ?', ['s', 'committed', 's', $key]);
        db_end_transaction('update_commit');
        $check($value($key) === 'committed', 'Committed update lost');
        db_begin_transaction('delete_commit');
        ps_query('DELETE FROM sysvars WHERE name = ?', ['s', $key]);
        db_end_transaction('delete_commit');
        $check($value($key) === null, 'Committed delete lost');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        if (!empty($GLOBALS['sql_transaction_in_progress'])) {
            db_rollback_transaction('cleanup');
        }
        ps_query('DELETE FROM sysvars WHERE name IN (?,?)', ['s', $key, 's', $other]);
        $GLOBALS['use_db_transaction'] = $saved;
        db_clear_connection_mode();
    }
})();

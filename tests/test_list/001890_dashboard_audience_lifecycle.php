<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/dash_functions.php';
    $tiles = [];
    $a = new_user('dash_a_' . test_generate_random_ID(6));
    $b = new_user('dash_b_' . test_generate_random_ID(6));
    $saved = $GLOBALS['userpermissions'];
    try {
        $title = 'Dash lifecycle ' . test_generate_random_ID(6);
        $personal = create_dash_tile('pages/dash_tile.php?search=test', '', $title, 0, false, 10, false);
        $tiles[] = $personal;
        add_user_dash_tile($a, $personal, 90, false);
        $GLOBALS['userpermissions'] = [];
        $check(can_edit_tile($personal, 0, $a) && !can_edit_tile($personal, 0, $b), 'Personal tile ownership not isolated');
        $check(!can_edit_tile($personal, 1, $a), 'Ordinary user can edit shared tile');
        $all = create_dash_tile('pages/dash_tile.php?search=all', '', $title . ' all', 0, true, 20, false);
        $tiles[] = $all;
        foreach ([$a, $b] as $user) {
            $check(ps_value('SELECT COUNT(*) value FROM user_dash_tile WHERE user = ? AND dash_tile = ?', ['i', $user, 'i', $all], 0) == 1, 'Global tile not distributed');
        }
        reorder_user_dash($a);
        $order = ps_array('SELECT dash_tile value FROM user_dash_tile WHERE user = ? ORDER BY order_by', ['i', $a]);
        $check(array_search($all, $order) < array_search($personal, $order), 'Reorder changed relative tile positions');
        $positions = ps_array('SELECT order_by value FROM user_dash_tile WHERE user = ? ORDER BY order_by', ['i', $a]);
        $check($positions == range(10, count($positions) * 10, 10), 'Reorder did not persist normalized positions');
        delete_dash_tile($all);
        $check(ps_value('SELECT COUNT(*) value FROM dash_tile WHERE ref = ?', ['i', $all], -1) == 0
            && ps_value('SELECT COUNT(*) value FROM user_dash_tile WHERE dash_tile = ?', ['i', $all], -1) == 0, 'Tile deletion left dependent records');
        $check(can_edit_tile($personal, 0, $a), 'Shared tile deletion changed personal tile');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
        foreach ($tiles as $tile) {
            delete_dash_tile($tile, true, true);
        }
        ps_query('DELETE FROM user_dash_tile WHERE user IN (?,?)', ['i', $a, 'i', $b]);
        ps_query('DELETE FROM user WHERE ref IN (?,?)', ['i', $a, 'i', $b]);
    }
})();

<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['user_rating_only_once'];
    $resource = create_resource(1, 0);
    $a = $GLOBALS['userref'];
    $b = new_user('rating_' . test_generate_random_ID(8));
    try {
        $GLOBALS['user_rating_only_once'] = true;
        $expect = static function ($total, $count) use ($resource, $check) {
            $row = get_resource_data($resource, false);
            $check($row['user_rating_total'] == $total && $row['user_rating_count'] == $count, 'Rating aggregate incorrect');
            $check(ps_value('SELECT COUNT(*) value FROM user_rating WHERE ref = ?', ['i', $resource], -1) == $count, 'Rating rows duplicated or missing');
        };
        user_rating_save($a, $resource, 3);
        $expect(3, 1);
        user_rating_save($b, $resource, 5);
        $expect(8, 2);
        user_rating_save($a, $resource, 4);
        $expect(9, 2);
        user_rating_save($a, $resource, 0);
        $expect(5, 1);
        $check(ps_value('SELECT rating value FROM user_rating WHERE user = ? AND ref = ?', ['i', $b, 'i', $resource], -1) == 5, 'Removing rating changed another user rating');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['user_rating_only_once'] = $saved;
        ps_query('DELETE FROM user_rating WHERE ref = ?', ['i', $resource]);
        ps_query('DELETE FROM user WHERE ref = ?', ['i', $b]);
    }
})();

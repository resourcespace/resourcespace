<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/message_functions.php';
    $a = new_user('inbox_a_' . test_generate_random_ID(8));
    $b = new_user('inbox_b_' . test_generate_random_ID(8));
    try {
        message_add([$a, $b], 'Shared inbox fixture');
        $messages = [];
        message_get($messages, $a);
        $check(count($messages) === 1 && $messages[0]['message'] === 'Shared inbox fixture', 'Recipient inbox missing message');
        $message = $messages[0]['message_id'];
        $a_ref = $messages[0]['ref'];
        message_seen($a_ref);
        $check(!message_get($messages, $a), 'Seen message remains unread');
        $check(message_get($messages, $b) && count($messages) === 1, 'Reading changed another recipient inbox');
        $check(message_get($messages, $a, false, true) && count($messages) === 1, 'Seen message disappeared from complete inbox');
        message_unseen($a_ref);
        $check(message_get($messages, $a), 'Unread transition failed');
        message_remove($message);
        $check(!message_get($messages, $a) && !message_get($messages, $b), 'Deleted message remains in inbox');
        $check(ps_value('SELECT COUNT(*) value FROM user_message WHERE message = ?', ['i', $message], -1) == 0, 'Message deletion left recipient records');
        message_add($a, 'Expired fixture', '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, -3600);
        message_get($messages, $a, true, true);
        $check(count($messages) === 1, 'Expired message fixture missing');
        $expired = $messages[0]['message_id'];
        $check(!message_get($messages, $a), 'Expired message shown in current inbox');
        message_purge();
        $check(!message_get($messages, $a, true, true), 'Purge retained expired message');
        $check(ps_value('SELECT COUNT(*) value FROM user_message WHERE message = ?', ['i', $expired], -1) == 0, 'Purge left recipient records');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        // Use recipient mappings rather than last insert IDs (message_add inserts several tables).
        foreach (ps_array('SELECT message value FROM user_message WHERE user IN (?,?)', ['i', $a, 'i', $b]) as $id) {
            message_remove($id);
        }
        ps_query('DELETE FROM user WHERE ref IN (?,?)', ['i', $a, 'i', $b]);
    }
})();

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['language']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('keeps read state and deletion isolated between message recipients', function () use (&$fixtures): void {
    $a = $fixtures->user('inbox_a_' . generateRandomTestID(8));
    $b = $fixtures->user('inbox_b_' . generateRandomTestID(8));
    $fixtures->onCleanup(static function () use ($a, $b): void {
        $ids = ps_array('SELECT DISTINCT message AS value FROM user_message WHERE user IN (?, ?)', ['i', $a, 'i', $b]);
        foreach ($ids as $id) {
            message_remove($id);
        }
    });
    message_add([$a, $b], 'Shared inbox fixture');
    $messages = [];
    message_get($messages, $a);
    expect(count($messages) === 1 && $messages[0]['message'] === 'Shared inbox fixture')->toBeTrue('Recipient inbox missing message');
    $message = $messages[0]['message_id'];
    $a_ref = $messages[0]['ref'];
    message_seen($a_ref);
    expect(!message_get($messages, $a))->toBeTrue('Seen message remains unread');
    expect(message_get($messages, $b) && count($messages) === 1)->toBeTrue('Reading changed another recipient inbox');
    expect(message_get($messages, $a, false, true) && count($messages) === 1)->toBeTrue('Seen message disappeared from complete inbox');
    message_unseen($a_ref);
    expect(message_get($messages, $a))->toBeTrue('Unread transition failed');
    message_remove($message);
    expect(!message_get($messages, $a) && !message_get($messages, $b))->toBeTrue('Deleted message remains in inbox');
    expect(ps_value('SELECT COUNT(*) value FROM user_message WHERE message = ?', ['i', $message], -1) == 0)->toBeTrue('Message deletion left recipient records');
    message_add($a, 'Expired fixture', '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, -3600);
    message_get($messages, $a, true, true);
    expect(count($messages) === 1)->toBeTrue('Expired message fixture missing');
    $expired = $messages[0]['message_id'];
    expect(!message_get($messages, $a))->toBeTrue('Expired message shown in current inbox');
    message_purge();
    expect(!message_get($messages, $a, true, true))->toBeTrue('Purge retained expired message');
    expect(ps_value('SELECT COUNT(*) value FROM user_message WHERE message = ?', ['i', $expired], -1) == 0)->toBeTrue('Purge left recipient records');
});

it('localises notification messages and email content and honours the recipient preference', function () use (&$fixtures): void {
    $user = $fixtures->user('notification_' . generateRandomTestID(10));
    $email = 'notification@example.invalid';
    ps_query("UPDATE user SET approved = 1, email = ?, lang = 'fr' WHERE ref = ?", ['s', $email, 'i', $user]);
    unset($GLOBALS['udata_cache'][$user]);
    $fixtures->onCleanup(static function () use ($user): void {
        foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $user]) as $id) {
            message_remove($id);
        }
        ps_query('DELETE FROM user_preferences WHERE user = ?', ['i', $user]);
        clear_query_cache('preferences');
    });

    $url = $GLOBALS['baseurl'] . '/pages/user/user_messages.php';
    $message = new ResourceSpaceUserNotification();
    $message->set_subject('lang_youraccountdetails');
    $message->append_subject(' - %%REF', ['%%REF'], [$user]);
    $GLOBALS['language'] = 'fr';
    $message->set_text('i18n_~en:English~fr:French');
    $message->append_text(' : appended text');
    $message->user_preference = [
        'user_pref_system_management_notifications' => ['requiredvalue' => true, 'default' => true],
    ];
    $message->url = $url;

    expect(set_config_option($user, 'email_user_notifications', false))->toBeTrue();
    $result = send_user_notification([$user], $message);
    expect($result['messages'])->toBe([['user' => $user, 'message' => 'French : appended text', 'url' => $url]])
        ->and($result['emails'])->toBeEmpty();
    $inbox = [];
    expect(message_get($inbox, $user))->toBeTrue()->and(array_column($inbox, 'message'))->toBe(['French : appended text']);

    expect(set_config_option($user, 'email_user_notifications', true))->toBeTrue();
    $result = send_user_notification([$user], $message);
    expect($result['messages'])->toBeEmpty()->and($result['emails'])->toHaveCount(1);
    expect($result['emails'][0]['email'])->toBe($email)
        ->and($result['emails'][0]['subject'])->toBe('Vos informations de connexion - ' . $user)
        ->and($result['emails'][0]['body'])->toContain('French : appended text', $url);

    expect(set_config_option($user, 'user_pref_system_management_notifications', false))->toBeTrue();
    $result = send_user_notification([$user], $message);
    expect($result['messages'])->toBeEmpty()->and($result['emails'])->toBeEmpty();
});

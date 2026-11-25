<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('notifies prior downloaders within the expiry window using their notification preference', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $id = generateRandomTestID(10);
        $match = $fixtures->resource(1, 0);
        $outside = $fixtures->resource(1, 0);
        $field = $fixtures->field('Expiry ' . $id, 1, FIELD_TYPE_EXPIRY_DATE);
        $errors = [];
        expect(update_field($match, $field, date('Y-m-d', strtotime('+3 days')), $errors))->toBeTrue()
            ->and(update_field($outside, $field, date('Y-m-d', strtotime('+5 days')), $errors))->toBeTrue();

        $users = [];
        foreach (['inbox', 'email', 'unrelated', 'outside_window'] as $role) {
            $user = $fixtures->user('expiry_' . $role . '_' . $id, 3);
            $users[$role] = $user;
            ps_query('UPDATE user SET approved = 1, email = ? WHERE ref = ?', [
                's', $role . '@example.invalid', 'i', $user,
            ]);
            unset($GLOBALS['udata_cache'][$user]);
            expect(set_config_option($user, 'email_user_notifications', $role === 'email'))->toBeTrue();
            $fixtures->onCleanup(static function () use ($user): void {
                foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $user]) as $message) {
                    message_remove($message);
                }
                ps_query('DELETE FROM user_preferences WHERE user = ?', ['i', $user]);
                clear_query_cache('preferences');
            });
        }
        setup_user(get_user($users['inbox']));
        expect(resource_log($match, LOG_CODE_DOWNLOADED, 0))->toBeInt();
        expect(resource_log($match, LOG_CODE_DOWNLOADED, 0))->toBeInt();
        setup_user(get_user($users['email']));
        expect(resource_log($match, LOG_CODE_DOWNLOADED, 0))->toBeInt();
        setup_user(get_user($users['outside_window']));
        expect(resource_log($outside, LOG_CODE_DOWNLOADED, 0))->toBeInt();

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure(['notify_on_resource_expiry_days' => 3, 'baseurl' => $server->url]);
        LocalTestScript::run('batch/expiry_notification.php');
        $messages = [];
        expect(message_get($messages, $users['inbox']))->toBeTrue();
        expect($messages)->toHaveCount(1);
        expect($messages[0]['message'])->toContain(str_replace('%X', '3', $GLOBALS['lang']['resource_expiry_x_days']));
        expect(parse_url($messages[0]['url'], PHP_URL_QUERY))->toBe('r=' . $match);
        foreach (['email', 'unrelated', 'outside_window'] as $role) {
            expect(message_get($messages, $users[$role]))->toBeFalse();
        }
    } finally {
        $fixtures->cleanUp();
    }
});

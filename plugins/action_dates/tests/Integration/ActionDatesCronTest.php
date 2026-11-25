<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once dirname(__DIR__, 2) . '/hooks/all.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $settings = [
        'lang', 'userref', 'userpermissions', 'resource_deletion_state', 'notification_users_cache',
        'email_notify_usergroups', 'action_dates_deletefield', 'action_dates_eligible_states',
        'action_dates_restrictfield', 'action_dates_reallydelete', 'action_dates_email_for_state',
        'action_dates_email_for_restrict', 'action_dates_email_admin_days', 'action_dates_new_state',
        'action_dates_remove_from_collection', 'action_dates_extra_config', 'action_dates_workflow_actions',
        'action_dates_weekdays',
    ];
    $fixtures = new IntegrationFixtures($settings);
    $previous_run = get_sysvar('last_action_dates_cron', '1970-01-01');
    $fixtures->onCleanup(static fn () => set_sysvar('last_action_dates_cron', $previous_run));
    set_sysvar('last_action_dates_cron', '1970-01-01');

    foreach ([
        'action_dates_deletefield' => 0, 'action_dates_restrictfield' => 0,
        'action_dates_eligible_states' => [], 'action_dates_reallydelete' => false,
        'action_dates_email_for_state' => false, 'action_dates_email_for_restrict' => false,
        'action_dates_email_admin_days' => '', 'action_dates_new_state' => 2,
        'action_dates_remove_from_collection' => true, 'action_dates_extra_config' => [],
        'action_dates_workflow_actions' => false, 'action_dates_weekdays' => range(0, 6),
        'resource_deletion_state' => 3, 'notification_users_cache' => [], 'email_notify_usergroups' => [],
    ] as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    register_plugin_language('action_dates');
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('accepts empty configuration and defers work on an excluded weekday', function (): void {
    $GLOBALS['action_dates_weekdays'] = [(int) date('w', strtotime('+1 day'))];
    $run = static fn (): string => cast_echo_to_string(HookAction_datesCronCron(...));
    expect($run())->toContain('not correct weekday to run')
        ->and(get_sysvar('last_action_dates_cron'))->toBe('1970-01-01');

    $GLOBALS['action_dates_weekdays'] = range(0, 6);
    expect($run())->toContain('Running cron tasks')
        ->and(get_sysvar('last_action_dates_cron'))->not->toBe('1970-01-01')
        ->and($run())->toContain('Skipping action date cron');
});

it('moves and restricts overdue resources only from eligible states and applies extra actions', function () use (&$fixtures): void {
    $action = $fixtures->field('Scheduled action', 0, FIELD_TYPE_DATE);
    $restriction = $fixtures->field('Scheduled restriction', 0, FIELD_TYPE_DATE);
    $extra = $fixtures->field('Other scheduled action', 0, FIELD_TYPE_DATE);
    $active = $fixtures->resource(1, 0);
    $review = $fixtures->resource(1, 1);
    $outside = $fixtures->resource(1, -1);
    $future = $fixtures->resource(1, 0);
    $different = $fixtures->resource(1, 0);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Scheduled ' . generateRandomTestID(12));
    foreach ([$active, $review, $outside, $future] as $ref) {
        $date = $ref === $future ? '2099-01-01' : '2001-01-01';
        $errors = [];
        expect(update_field($ref, $action, $date, $errors))->toBeTrue()
            ->and(update_field($ref, $restriction, $date, $errors))->toBeTrue()
            ->and(add_resource_to_collection($ref, $collection))->toBeTrue();
    }
    $errors = [];
    expect(update_field($different, $extra, '2001-01-02', $errors))->toBeTrue();

    $GLOBALS['action_dates_deletefield'] = $action;
    $GLOBALS['action_dates_restrictfield'] = $restriction;
    $GLOBALS['action_dates_eligible_states'] = [0, 1];
    $GLOBALS['action_dates_extra_config'] = [['field' => $extra, 'status' => 1]];
    $output = cast_echo_to_string(HookAction_datesCronCron(...));
    expect($output)->toContain('Restricting resource ' . $active, 'Moving resource ' . $review);

    foreach ([$active, $review] as $ref) {
        expect(get_resource_data($ref, false))->toMatchArray(['archive' => 2, 'access' => RESOURCE_ACCESS_RESTRICTED]);
    }
    expect(get_collection_resources($collection))->toEqualCanonicalizing([$outside, $future])
        ->and(get_resource_data($outside, false))->toMatchArray(['archive' => -1, 'access' => RESOURCE_ACCESS_FULL])
        ->and(get_resource_data($future, false))->toMatchArray(['archive' => 0, 'access' => RESOURCE_ACCESS_FULL])
        ->and((int) get_resource_data($different, false)['archive'])->toBe(1);
});

it('sends one message per action and links only resources still due within the notice period', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Action dates audience ' . $id, 's,t,e0,f*');
    $recipient = $fixtures->user('actiondates' . $id, $group);
    ps_query('UPDATE user SET approved = 1, email = ? WHERE ref = ?', ['s', 'dates@example.invalid', 'i', $recipient]);
    $fixtures->onCleanup(static function () use ($recipient): void {
        foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $recipient]) as $message) {
            message_remove($message);
        }
        ps_query('DELETE FROM user_preferences WHERE user = ?', ['i', $recipient]);
        clear_query_cache('preferences');
    });
    expect(set_config_option($recipient, 'user_pref_resource_notifications', true))->toBeTrue()
        ->and(set_config_option($recipient, 'email_user_notifications', false))->toBeTrue();
    $GLOBALS['email_notify_usergroups'] = [$group];
    $GLOBALS['notification_users_cache'] = [];

    $action = $fixtures->field('Notice action date', 0, FIELD_TYPE_DATE);
    $restrict = $fixtures->field('Notice restriction date', 0, FIELD_TYPE_DATE);
    $early = $fixtures->resource(1, 0);
    $later = $fixtures->resource(1, 0);
    $excluded = $fixtures->resource(1, 0);
    $due_now = $fixtures->resource(1, 0);
    $restricted = $fixtures->resource(1, 0);
    $errors = [];
    foreach ([
        [$early, $action, 2], [$later, $action, 4], [$excluded, $action, 8],
        [$due_now, $action, -1], [$restricted, $restrict, 3], [$due_now, $restrict, -1],
    ] as [$resource, $field, $days]) {
        expect(update_field($resource, $field, date('Y-m-d', strtotime("{$days} days")), $errors))->toBeTrue();
    }
    set_sysvar('last_action_dates_cron', date('Y-m-d H:i:s', strtotime('-3 days')));
    $GLOBALS['action_dates_deletefield'] = $action;
    $GLOBALS['action_dates_restrictfield'] = $restrict;
    $GLOBALS['action_dates_email_admin_days'] = 5;
    $GLOBALS['action_dates_email_for_state'] = true;
    $GLOBALS['action_dates_email_for_restrict'] = true;
    cast_echo_to_string(HookAction_datesCronCron(...));
    $inbox = [];
    expect(message_get($inbox, $recipient))->toBeTrue()->and($inbox)->toHaveCount(2);

    $linked = [];
    foreach ($inbox as $message) {
        parse_str((string) parse_url($message['url'], PHP_URL_QUERY), $params);
        $refs = explode(':', substr($params['search'], strlen('!list')));
        $key = str_contains($message['message'], 'restricted') ? 'restriction' : 'state';
        $linked[$key] = array_map('intval', $refs);
        expect($message['message'])->toContain($key === 'restriction' ? '3 days' : '2 to 4 days');
    }
    expect($linked['restriction'])->toBe([$restricted])
        ->and($linked['state'])->toEqualCanonicalizing([$early, $later]);
    expect(get_resource_data($due_now, false))->toMatchArray(['archive' => 2, 'access' => RESOURCE_ACCESS_RESTRICTED]);
});

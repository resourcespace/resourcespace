<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'configs_overwritten', 'system_wide_config_options', 'user_pref_resource_notifications',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('filters system configuration and personal preferences by label and stored changes', function () use (&$fixtures): void {
    $user = $fixtures->user('configsearch' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $user]);
    $system_settings = ['upload_then_edit' => true, 'no_welcometext' => true];
    $fixtures->onCleanup(static function () use ($user): void {
        ps_query('DELETE FROM user_preferences WHERE user = ?', ['i', $user]);
        clear_query_cache('preferences');
    });
    foreach ($system_settings as $parameter => $value) {
        $before = null;
        $had_value = get_config_option([], $parameter, $before);
        $fixtures->onCleanup(static function () use ($parameter, $had_value, $before): void {
            if ($had_value) {
                set_config_option(null, $parameter, $before);
            } else {
                ps_query('DELETE FROM user_preferences WHERE user IS NULL AND usergroup IS NULL AND parameter = ?', [
                    's', $parameter,
                ]);
                clear_query_cache('preferences');
            }
        });
        expect(set_config_option(null, $parameter, $value))->toBeTrue();
    }
    expect(set_config_option($user, 'upload_then_edit', false))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    foreach ([
        '/pages/admin/admin_system_config.php' => ['no_welcometext', 'searchbyday', '1'],
        '/pages/user/user_preferences.php' => ['retina_mode', 'retina_mode', '0'],
    ] as $path => [$unrelated, $unmodified, $upload_value]) {
        $all = $server->request($path, headers: $headers);
        expect($all['status'])->toBe(200);
        $form = LocalTestServer::hiddenFields($all['body'], '//form[@id="SearchSystemPages"]');
        expect($form)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $search = $server->request($path, $form + ['filter' => 'pload sequence', 'only_modified' => 'yes'], $headers);
        expect($search['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($search['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('string(//select[@name="upload_then_edit"]/option[@selected]/@value)'))->toBe($upload_value)
            ->and($xpath->evaluate('count(//select[@name="' . $unrelated . '"])'))->toBe(0.0)
            ->and($xpath->evaluate('count(//input[@name="clear_search"])'))->toBe(1.0);

        $clear = $server->request($path, $form + ['filter' => '', 'only_modified' => 'no'], $headers);
        expect($clear['status'])->toBe(200);
        $document->loadHTML($clear['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(//select[@name="upload_then_edit"])'))->toBe(1.0)
            ->and($xpath->evaluate('count(//select[@name="' . $unrelated . '"])'))->toBe(1.0)
            ->and($xpath->evaluate('count(//input[@name="clear_search"])'))->toBe(0.0);

        $modified = $server->request($path, $form + ['filter' => '', 'only_modified' => 'yes'], $headers);
        expect($modified['status'])->toBe(200);
        $document->loadHTML($modified['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(//select[@name="upload_then_edit"])'))->toBe(1.0)
            ->and($xpath->evaluate('count(//select[@name="' . $unmodified . '"])'))->toBe(0.0);
        if ($unrelated !== $unmodified) {
            expect($xpath->evaluate('count(//select[@name="' . $unrelated . '"])'))->toBe(1.0);
        }
    }
});

it('resolves the user preference ahead of the group preference and uses the configured fallback', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Preference group ' . $id, 's');
    $user = $fixtures->user('preferenceowner' . $id, $group);
    $config = ['user' => $user, 'usergroup' => $group];
    $name = 'pest_preference_' . $id;
    $GLOBALS[$name] = 'Runtime setting';
    $fixtures->onCleanup(static function () use ($name): void {
        unset($GLOBALS[$name]);
        ps_query('DELETE FROM user_preferences WHERE parameter = ?', ['s', $name]);
        clear_query_cache('preferences');
    });

    $value = null;
    expect(get_config_option($config, '', $value))->toBeFalse()->and($value)->toBeNull();
    expect(get_config_option([], $name, $value))->toBeFalse()->and($value)->toBe('Runtime setting');
    expect(get_config_option($config, $name, $value, 'Explicit fallback'))->toBeFalse()
        ->and($value)->toBe('Explicit fallback');

    expect(set_config_option(null, $name, 'System preference'))->toBeTrue();
    expect(get_config_option([], $name, $value))->toBeTrue()->and($value)->toBe('System preference');

    expect(set_usergroup_config_option($group, $name, 'Group preference'))->toBeTrue();
    expect(get_config_option($config, $name, $value))->toBeTrue()->and($value)->toBe('Group preference');

    expect(set_config_option($user, $name, 'Personal preference'))->toBeTrue();
    expect(get_config_option($config, $name, $value))->toBeTrue()->and($value)->toBe('Personal preference');
    expect(get_config_option(['usergroup' => $group], $name, $value))->toBeTrue()
        ->and($value)->toBe('Group preference');
});

it('falls back to the signed group configuration after setting up a group member', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Signed notification preferences ' . $id, 's');
    $GLOBALS['user_pref_resource_notifications'] = false;
    $GLOBALS['system_wide_config_options']['user_pref_resource_notifications'] = false;
    $code = '$user_pref_resource_notifications = true;';
    expect(save_usergroup($group, ['config_options' => '//SIG' . sign_code($code) . "\n" . $code]))->not->toBeFalse();
    $user = $fixtures->user('notificationmember' . $id, $group);
    setup_user(get_user($user));

    $value = null;
    expect(get_config_option(['user' => $user, 'usergroup' => $group], 'user_pref_resource_notifications', $value))
        ->toBeFalse()
        ->and($value)->toBeTrue();
});

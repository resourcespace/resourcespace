<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;
use Symfony\Component\Process\Process;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('preserves plugin settings across activation changes', function () use (&$fixtures): void {
    include_once dirname(__DIR__, 2) . '/include/plugin_functions.php';
    $name = 'test_framework_' . generateRandomTestID(6);
    $directory = $GLOBALS['storagedir'] . '/plugins/' . $name;
    $fixtures->onCleanup(static function () use ($name, $directory): void {
        ps_query('DELETE FROM plugins WHERE name = ?', ['s', $name]);
        clear_query_cache('plugins');
        $registration = $directory . '/' . $name . '.yaml';
        if (is_file($registration)) {
            unlink($registration);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    });
    expect(mkdir($directory, 0700, true))->toBeTrue();
    file_put_contents($directory . '/' . $name . '.yaml', "name: {$name}\nversion: 1.0\ndesc: Core plugin lifecycle fixture\nauthor: Tests\n");
    expect(activate_plugin($name))->toBeTrue('Plugin activation failed');
    $config = ['label' => 'Café 日本語', 'enabled' => true, 'values' => ['first', 'second'], 'count' => 42];
    expect(set_plugin_config($name, $config) && get_plugin_config($name) === $config)->toBeTrue('Plugin config round trip failed');
    expect(is_plugin_activated($name) && in_array($name, array_column(get_active_plugins(), 'name')))->toBeTrue('Active plugin missing from registry');
    deactivate_plugin($name);
    expect(!is_plugin_activated($name) && !in_array($name, array_column(get_active_plugins(), 'name')))->toBeTrue('Deactivated plugin still active');
    expect(get_plugin_config($name) === $config)->toBeTrue('Deactivation lost plugin configuration');
    expect(activate_plugin($name) && get_plugin_config($name) === $config)->toBeTrue('Reactivation lost stored configuration');
    purge_plugin_config($name);
    expect(get_plugin_config($name) === null)->toBeTrue('Plugin configuration purge failed');
    expect(!activate_plugin('missing_plugin_' . generateRandomTestID(8)))->toBeTrue('Missing plugin activation succeeded');
});

it('applies plugin group selections saved through the Plugin Manager to five installed plugins', function () use (
    &$fixtures,
): void {
    $id = generateRandomTestID(12);
    $allowed = $fixtures->group('Plugin access ' . $id, 's');
    $denied = $fixtures->group('No plugin access ' . $id, 's');
    $admin = $fixtures->user('plugin_admin_' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $admin,
    ]);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $plugins = ['refineresults', 'resource_usage', 'rss2', 'track_field_history', 'embedslideshow'];
    $page = '/pages/team/team_plugins_groups.php';

    foreach ($plugins as $plugin) {
        $previous = ps_query('SELECT inst_version, enabled_groups FROM plugins WHERE name = ?', ['s', $plugin]);
        $fixtures->onCleanup(static function () use ($plugin, $previous): void {
            if ($previous === []) {
                ps_query('DELETE FROM plugins WHERE name = ?', ['s', $plugin]);
            } else {
                ps_query('UPDATE plugins SET inst_version = ?, enabled_groups = ? WHERE name = ?', [
                    'd', $previous[0]['inst_version'], 's', $previous[0]['enabled_groups'], 's', $plugin,
                ]);
            }
            clear_query_cache('plugins');
        });
        expect(activate_plugin($plugin))->toBeTrue();
        $group_form = $server->request($page . '?plugin=' . $plugin, headers: $headers);
        expect($group_form['status'])->toBe(200);
        $form_tokens = LocalTestServer::hiddenFields($group_form['body'], '//form[contains(@action, "team_plugins_groups")]');
        expect($form_tokens)->toHaveKey('plugin', $plugin);
        $save = $server->request(
            $page . '?save=true',
            $form_tokens + ['access' => 'some', 'group_' . $allowed => 'yes'],
            $headers
        );
        expect($save['status'])->toBe(302);
        expect(ps_value('SELECT enabled_groups AS value FROM plugins WHERE name = ?', ['s', $plugin], ''))
            ->toBe((string) $allowed);
    }
    $group_plugins = static function (int $group): array {
        // Each group gets a fresh process because plugin registration includes PHP hooks and configuration.
        $script = <<<'PHP_CODE'
            require 'tests/Support/bootstrap-resourcespace-test-env.php';
            echo json_encode(register_group_access_plugins((int) $argv[1]), JSON_THROW_ON_ERROR);
            PHP_CODE;
        $process = new Process([PHP_BINARY, '-r', $script, (string) $group], RESOURCESPACE_BASE_PATH);
        $process->mustRun();
        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    };

    expect(array_values(array_intersect($plugins, $group_plugins($allowed))))->toBe($plugins);
    expect(array_intersect($plugins, $group_plugins($denied)))->toBeEmpty();
});

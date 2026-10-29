<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/plugin_functions.php';
    $name = 'test_framework_' . test_generate_random_ID(6);
    $directory = $GLOBALS['storagedir'] . '/plugins/' . $name;
    try {
        mkdir($directory, 0700, true);
        file_put_contents($directory . '/' . $name . '.yaml', "name: {$name}\nversion: 1.0\ndesc: Core plugin lifecycle fixture\nauthor: Tests\n");
        $check(activate_plugin($name), 'Plugin activation failed');
        $config = ['label' => 'Café 日本語', 'enabled' => true, 'values' => ['first', 'second'], 'count' => 42];
        $check(set_plugin_config($name, $config) && get_plugin_config($name) === $config, 'Plugin config round trip failed');
        $check(is_plugin_activated($name) && in_array($name, array_column(get_active_plugins(), 'name')), 'Active plugin missing from registry');
        deactivate_plugin($name);
        $check(!is_plugin_activated($name) && !in_array($name, array_column(get_active_plugins(), 'name')), 'Deactivated plugin still active');
        $check(get_plugin_config($name) === $config, 'Deactivation lost plugin configuration');
        $check(activate_plugin($name) && get_plugin_config($name) === $config, 'Reactivation lost stored configuration');
        purge_plugin_config($name);
        $check(get_plugin_config($name) === null, 'Plugin configuration purge failed');
        $check(!activate_plugin('missing_plugin_' . test_generate_random_ID(8)), 'Missing plugin activation succeeded');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        ps_query('DELETE FROM plugins WHERE name = ?', ['s', $name]);
        clear_query_cache('plugins');
        if (is_file($directory . '/' . $name . '.yaml')) {
            unlink($directory . '/' . $name . '.yaml');
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
})();

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

foreach (['api_functions', 'ajax_functions', 'api_bindings', 'login_functions', 'dash_functions', 'tab_functions'] as $include) {
    require_once RESOURCESPACE_BASE_PATH . '/include/' . $include . '.php';
}

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'enable_related_resources', 'comments_resource_enable', 'valid_upload_paths', 'userpermissions',
        'get_resource_data_cache',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('smoke tests the declared API binding contracts against the test installation', function () use (&$fixtures): void {
    $cwd = getcwd();
    $server = $_SERVER;
    $response = http_response_code();
    $fixtures->onCleanup(static function () use ($cwd, $server, $response): void {
        chdir($cwd);
        $_SERVER = $server;
        http_response_code($response ?: 200);
    });
    chdir(RESOURCESPACE_BASE_PATH . '/api');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
    $GLOBALS['enable_related_resources'] = true;
    $GLOBALS['comments_resource_enable'] = true;
    $GLOBALS['valid_upload_paths'] = [];
    $id = generateRandomTestID(10);
    $original = (int) $GLOBALS['userref'];
    $admin = $fixtures->user('api_smoke_admin_' . $id, 3);
    setup_user(get_user($admin));
    $resource = $fixtures->resource(1, 0);
    $other = $fixtures->resource(1, 0);
    $collection = $fixtures->collection($admin, 'API smoke ' . $id);
    $foreign_collection = $fixtures->collection($original, 'API foreign ' . $id);
    $field = $fixtures->field('API text ' . $id);
    $list_field = $fixtures->field('API nodes ' . $id, 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $node = set_node(null, $list_field, 'Smoke node', null, 10);
    $alternative = add_alternative_file($resource, 'Smoke alternative');
    ps_query('INSERT INTO tab (name, order_by) VALUES (?, ?)', ['s', 'API smoke ' . $id, 'i', 100]);
    $tab = sql_insert_id();
    $fixtures->onCleanup(static fn () => delete_tabs([$tab]));
    ps_query('INSERT INTO report (name, query) VALUES (?, ?)', ['s', 'API smoke ' . $id, 's', 'SELECT 1 AS smoke_result']);
    $report = sql_insert_id();
    $fixtures->onCleanup(static fn () => ps_query('DELETE FROM report WHERE ref = ?', ['i', $report]));

    $array = 'is_array';
    $bool = 'is_bool';
    $true = static fn (mixed $value): bool => $value === true;
    $false = static fn (mixed $value): bool => $value === false;
    $positive = static fn (mixed $value): bool => is_numeric($value) && $value > 0;
    $success = static fn (mixed $value): bool => is_array($value) && ($value['status'] ?? '') === 'success';
    $cases = [
        'create_resource' => [[1, 0], $positive],
        'copy_resource' => [[$resource], $positive],
        'do_search' => [['!resource' . $resource], static fn($v) => is_array($v) && in_array($resource, array_column($v, 'ref'))],
        'search_get_previews' => [['!resource' . $resource], $array],
        'get_resource_field_data' => [[$resource], $array],
        'update_field' => [[$resource, $field, 'API smoke value'], $true],
        'get_data_by_field' => [[$resource, $field], static fn($v) => $v === 'API smoke value'],
        'get_resource_log' => [[$resource], $array],
        'update_resource_type' => [[$resource, 1], $bool],
        'get_resource_path' => [[(string) $resource, null, '', false], 'is_string'],
        'get_resource_data' => [[$resource], static fn($v) => is_array($v) && (int) $v['ref'] === $resource],
        'put_resource_data' => [[$resource, ['access' => 0]], $bool],
        'get_alternative_files' => [[$resource], $array],
        'get_resource_types' => [[], static fn($v) => is_array($v) && count($v) > 0],
        'add_alternative_file' => [[$resource, 'API alternative'], $positive],
        'delete_access_keys' => [['smoke-nonexistent-key', (string) $resource, '-'], $true],
        'delete_alternative_file' => [[$resource, $alternative], $bool],
        'upload_file' => [[$resource, true, false, false, '/api-smoke-invalid/file.txt'], $false],
        'upload_file_by_url' => [[$resource, true, false, false, 'invalid-url'], $false],
        'get_related_resources' => [[$resource], $array],
        'get_field_options' => [[$list_field], $array],
        'get_nodes' => [[$list_field], $array],
        'update_resource_lock' => [[$resource, 0], $true],
        'get_user_collections' => [[], $array],
        'add_resource_to_collection' => [[$resource, $collection], $bool],
        'collection_add_resources' => [[$collection, (string) $other], $bool],
        'get_resource_collections' => [[$resource], $array],
        'get_collections_resource_count' => [[(string) $collection], $array],
        'remove_resource_from_collection' => [[$resource, $collection], $bool],
        'collection_remove_resources' => [[$collection, (string) $other], $bool],
        'create_collection' => [['API created ' . $id], $positive],
        'search_public_collections' => [[], $array],
        'set_node' => [['NULL', $list_field, 'API added node'], $positive],
        'add_resource_nodes' => [[$resource, (string) $node], $true],
        'add_resource_nodes_multi' => [[(string) $other, (string) $node], $true],
        'resource_log_last_rows' => [[], $array],
        'get_resource_all_image_sizes' => [[$resource], $array],
        'get_node_id' => [['Smoke node', $list_field], static fn($v) => (int) $v === (int) $node],
        'replace_resource_file' => [[$resource, '/api-smoke-invalid/file.txt'], static fn($v) => is_array($v) && $v['Status'] === 'FAILED'],
        'update_related_resource' => [[$resource, (string) $other], $bool],
        'get_users' => [[], $array],
        'save_collection' => [[$collection, ['name' => 'API renamed ' . $id]], $true],
        'get_collection' => [[$collection], static fn($v) => is_array($v) && $v['name'] === 'API renamed ' . $id],
        'send_user_message' => [['api_missing_' . $id, 'Smoke message'], static fn($v) => $v === $GLOBALS['lang']['error_invalid_user']],
        'get_profile_image' => [[$GLOBALS['userref']], 'is_string'],
        'get_system_status' => [[true], $array],
        'relate_all_resources' => [[$resource . ',' . $other], $bool],
        'show_hide_collection' => [[$collection, true, $GLOBALS['userref']], $bool],
        'send_collection_to_admin' => [[$foreign_collection], $false],
        'reorder_featured_collections' => [[[]], $bool],
        'get_dash_search_data' => [['pages/search.php?search=!resource' . $resource, 0], $array],
        'reorder_tabs' => [[[$tab]], $true],
        'save_tab' => [[['ref' => $tab, 'name' => 'API renamed tab']], $success],
        'delete_tabs' => [[[$tab]], $true],
        'mark_email_as_invalid' => [[''], $false],
        'get_user_message' => [[0], static fn($v) => $v === false || is_array($v)],
        'get_users_by_permission' => [['a'], $array],
        'upload_multipart' => [[$resource, true, false], static fn($v) => is_array($v) && ($v['status'] ?? '') === 'fail'],
        'get_resource_type_fields' => [[], static fn($v) => is_array($v) && count($v) > 0],
        'create_resource_type_field' => [['API created ' . $id, '0', FIELD_TYPE_TEXT_BOX_SINGLE_LINE], $success],
        'get_featured_collections' => [[0], $array],
        'get_edit_access' => [[$resource], $true],
        'toggle_active_state_for_nodes' => [[[$node]], $array],
        'get_processing_message' => [[], static fn($v) => $v === false || is_array($v)],
        'checkperm' => [['a'], $true],
        'get_resource_access' => [[$resource], static fn($v) => $v === 0],
        'resource_file_readonly' => [[$resource], $success],
        'new_user' => [['api_smoke_user_' . $id, 2], $success],
        'save_user' => [[$GLOBALS['userref'], json_encode(['fullname' => 'API smoke admin'])], $success],
        'get_resource_comments' => [[$resource], $array],
        'delete_comment' => [[0], $true],
        'get_reports' => [[], $array],
        'do_report' => [[$report, '2026-01-01', '2026-01-02'], static fn($v) => is_array($v) && (int) ($v[0]['smoke_result'] ?? 0) === 1],
        'delete_resources_in_collection' => [[$collection], $bool],
        'delete_collection' => [[$collection], static fn($v) => $v === null && get_collection($collection) === false],
        'delete_resource' => [[$other], $bool],
    ];

    $bindings = [];
    foreach (get_defined_functions()['user'] as $function) {
        $reflection = new ReflectionFunction($function);
        if ($reflection->getFileName() === RESOURCESPACE_BASE_PATH . '/include/api_bindings.php') {
            $bindings[] = substr($function, 4);
        }
    }
    expect($bindings)->toEqualCanonicalizing(array_keys($cases));
    foreach ($cases as $binding => [$arguments, $validate]) {
        http_response_code(200);
        $result = ('api_' . $binding)(...$arguments);
        if (in_array($binding, ['create_resource', 'copy_resource'], true) && is_numeric($result)) {
            $fixtures->trackResource((int) $result);
        } elseif ($binding === 'create_collection' && is_numeric($result)) {
            $fixtures->trackCollection((int) $result);
        } elseif ($binding === 'create_resource_type_field' && isset($result['data']['ref'])) {
            $fixtures->onCleanup(static fn () => delete_resource_type_field($result['data']['ref']));
        } elseif ($binding === 'new_user' && isset($result['data']['ref'])) {
            $fixtures->onCleanup(static function () use ($result): void {
                $post = $_POST;
                try {
                    $_POST = ['deleteme' => 'yes'];
                    save_user($result['data']['ref']);
                } finally {
                    $_POST = $post;
                }
            });
        }
        expect($validate($result))->toBeTrue('Unexpected return value from API binding ' . $binding);
    }
});

it('validates resource lock arguments and enforces lock ownership', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $administrator = get_user($GLOBALS['userref']);
    $admin = (int) $administrator['ref'];
    $editor = $fixtures->user('api_lock_editor_' . generateRandomTestID(10));
    setup_user(get_user($editor));
    $GLOBALS['userpermissions'] = ['e0'];
    $assert_lock = static function (array $arguments, bool $result, int $owner) use ($resource): void {
        unset($GLOBALS['get_resource_data_cache'][$resource]);
        expect(api_update_resource_lock(...$arguments))->toBe($result)
            ->and((int) ps_value('SELECT lock_user AS value FROM resource WHERE ref = ?', ['i', $resource], -1))
            ->toBe($owner);
    };
    $assert_lock([$resource, 1], true, $editor);
    $assert_lock([$resource, 0], true, 0);
    $assert_lock([$resource, 1, null], true, $editor);
    $assert_lock([$resource, 0], true, 0);

    foreach ([['e0', 'nolock'], []] as $permissions) {
        $GLOBALS['userpermissions'] = $permissions;
        $assert_lock([$resource, 1], false, 0);
    }
    $GLOBALS['userpermissions'] = ['e0'];
    $assert_lock([$resource, 1, $admin], true, $admin);
    $assert_lock([$resource, 0], false, $admin);
    $assert_lock([$resource, 1, $editor], false, $admin);
    setup_user($administrator);
    $assert_lock([$resource, 0], true, 0);
    $assert_lock([(string) $resource, '1', (string) $editor], true, $editor);
    $assert_lock([$resource, 0], true, 0);

    foreach (['username', '', '1.5', [], new stdClass(), true, 1.5] as $invalid) {
        $assert_lock([$invalid, 1], false, 0);
        $assert_lock([$resource, $invalid], false, 0);
        $assert_lock([$resource, 1, $invalid], false, 0);
    }
    $assert_lock([0, 1], false, 0);
    $assert_lock([-1, 1], false, 0);
    $assert_lock([$resource, 2], false, 0);
});

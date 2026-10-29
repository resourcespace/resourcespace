<?php

command_line_only();

// Binding smoke tests. HTTP authentication is covered separately by 001590_api_logging.php.
include_once __DIR__ . '/../../include/api_functions.php';
include_once __DIR__ . '/../../include/ajax_functions.php';
include_once __DIR__ . '/../../include/api_bindings.php';
include_once __DIR__ . '/../../include/login_functions.php';
include_once __DIR__ . '/../../include/dash_functions.php';
include_once __DIR__ . '/../../include/tab_functions.php';

return (static function () {
    $saved_server = $_SERVER;
    $saved_files = $_FILES;
    $saved_user = get_user($GLOBALS['userref']);
    $saved_cwd = getcwd();
    $settings = ['enable_related_resources', 'comments_resource_enable', 'valid_upload_paths'];
    $saved_settings = array_intersect_key($GLOBALS, array_flip($settings));
    $resources = $collections = $fields = $users = [];
    $tab = $report = null;
    $current = 'fixture setup';
    // The CLI runner has already printed progress. Ignore only PHP's HTTP-header warning;
    // all other warnings/notices become failures with the binding name in the diagnostic.
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (str_starts_with($message, 'http_response_code(): Cannot set response code')) {
            return true;
        }
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        // Match the API entry point's working directory (some bindings use relative includes).
        chdir(__DIR__ . '/../../api');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
        $_FILES = [];
        $GLOBALS['enable_related_resources'] = true;
        $GLOBALS['comments_resource_enable'] = true;
        $GLOBALS['valid_upload_paths'] = [];
        setup_user(get_user(new_user('api_smoke_admin_' . test_generate_random_ID(4), 3)));
        $users[] = $GLOBALS['userref'];
        $id = test_generate_random_ID(4);
        $resource = create_resource(1, 0);
        $other = create_resource(1, 0);
        $resources = [$resource, $other];
        $collection = create_collection($GLOBALS['userref'], 'API smoke ' . $id);
        $foreign_collection = create_collection($saved_user['ref'], 'API foreign ' . $id);
        $collections = [$collection, $foreign_collection];
        $field = create_resource_type_field('API text ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'api_text_' . $id);
        $list_field = create_resource_type_field('API nodes ' . $id, 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'api_nodes_' . $id);
        $fields = [$field, $list_field];
        $node = set_node(null, $list_field, 'Smoke node', '', 0);
        $alternative = add_alternative_file($resource, 'Smoke alternative');
        ps_query('INSERT INTO tab (`name`, `order_by`) VALUES (?, ?)', ['s', 'API smoke ' . $id, 'i', 100]);
        $tab = sql_insert_id();
        ps_query('INSERT INTO report (`name`, `query`) VALUES (?, ?)', ['s', 'API smoke ' . $id, 's', 'SELECT 1 AS smoke_result']);
        $report = sql_insert_id();

        $array = 'is_array';
        $bool = 'is_bool';
        $true = static fn($value) => $value === true;
        $false = static fn($value) => $value === false;
        $positive = static fn($value) => is_numeric($value) && $value > 0;
        $success = static fn($value) => is_array($value) && ($value['status'] ?? '') === 'success';
        // Each case supplies API arguments and an explicit expected return contract.
        // Validation cases are intentional for remote/HTTP uploads and notification operations.
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

        // Reflection limits coverage to functions actually declared in api_bindings.php.
        $bindings = [];
        foreach (get_defined_functions()['user'] as $function) {
            $reflection = new ReflectionFunction($function);
            if ($reflection->getFileName() === realpath(__DIR__ . '/../../include/api_bindings.php')) {
                $bindings[] = substr($function, 4);
            }
        }
        $missing = array_diff($bindings, array_keys($cases));
        $obsolete = array_diff(array_keys($cases), $bindings);
        if ($missing !== [] || $obsolete !== []) {
            echo 'API coverage mismatch: missing ' . implode(', ', $missing) . '; obsolete ' . implode(', ', $obsolete) . ' - ';
            return false;
        }
        foreach ($cases as $current => [$arguments, $validate]) {
            http_response_code(200);
            $result = call_user_func_array('api_' . $current, $arguments);
            if (in_array($current, ['create_resource', 'copy_resource']) && is_numeric($result)) {
                $resources[] = (int) $result;
            } elseif ($current === 'create_collection' && is_numeric($result)) {
                $collections[] = (int) $result;
            } elseif ($current === 'create_resource_type_field' && isset($result['data']['ref'])) {
                $fields[] = $result['data']['ref'];
            } elseif ($current === 'new_user' && isset($result['data']['ref'])) {
                $users[] = $result['data']['ref'];
            }
            if (!$validate($result)) {
                echo "API {$current} returned an unexpected result: " . json_encode($result) . ' - ';
                return false;
            }
        }
        $current = 'update_resource_lock';
        $lock_resource = create_resource(1, 0);
        $resources[] = $lock_resource;
        $admin = get_user($GLOBALS['userref']);
        $lock_user = new_user('api_lock_user_' . $id, 2);
        $users[] = $lock_user;
        setup_user(get_user($lock_user));
        $GLOBALS['userpermissions'] = ['e0'];

        // Check both the return value and the stored owner after every operation.
        $check_lock = static function ($arguments, $expected, $owner) use ($lock_resource) {
            // Each API call normally runs in a separate request with a fresh resource cache.
            unset($GLOBALS['get_resource_data_cache'][$lock_resource]);
            $result = api_update_resource_lock(...$arguments);
            $actual_owner = ps_value('SELECT lock_user AS value FROM resource WHERE ref = ?', ['i', $lock_resource], -1);
            if ($result !== $expected || (int) $actual_owner !== (int) $owner) {
                throw new RuntimeException('Unexpected lock result or owner for ' . json_encode($arguments));
            }
        };
        $check_lock([$lock_resource, 1], true, $lock_user);
        $check_lock([$lock_resource, 0], true, 0);
        $check_lock([$lock_resource, 1, null], true, $lock_user);
        $check_lock([$lock_resource, 0], true, 0);

        $GLOBALS['userpermissions'][] = 'nolock';
        $check_lock([$lock_resource, 1], false, 0);
        $GLOBALS['userpermissions'] = [];
        $check_lock([$lock_resource, 1], false, 0);
        $GLOBALS['userpermissions'] = ['e0'];
        $check_lock([$lock_resource, 1, (int) $admin['ref']], true, $admin['ref']);
        $check_lock([$lock_resource, 0], false, $admin['ref']);
        $check_lock([$lock_resource, 1, $lock_user], false, $admin['ref']);

        setup_user($admin);
        $check_lock([$lock_resource, 0], true, 0);
        $check_lock([(string) $lock_resource, '1', (string) $lock_user], true, $lock_user);
        // Administrators can unlock another user's resource.
        $check_lock([$lock_resource, 0], true, 0);
        foreach (['username', '', '1.5', [], new stdClass(), true, 1.5] as $invalid) {
            $check_lock([$invalid, 1], false, 0);
            $check_lock([$lock_resource, $invalid], false, 0);
            $check_lock([$lock_resource, 1, $invalid], false, 0);
        }
        $check_lock([0, 1], false, 0);
        $check_lock([-1, 1], false, 0);
        $check_lock([$lock_resource, 2], false, 0);
        return true;
    } catch (Throwable $error) {
        echo "API {$current}: {$error->getMessage()} - ";
        return false;
    } finally {
        foreach (array_unique($resources) as $ref) {
            delete_resource($ref);
        }
        foreach (array_unique($collections) as $ref) {
            delete_collection($ref);
        }
        foreach (array_unique($fields) as $ref) {
            delete_resource_type_field($ref);
        }
        if ($tab !== null) {
            delete_tabs([$tab]);
        }
        if ($report !== null) {
            ps_query('DELETE FROM report WHERE ref = ?', ['i', $report]);
        }
        setup_user($saved_user);
        foreach ($users as $ref) {
            ps_query('DELETE FROM user WHERE ref = ?', ['i', $ref]);
        }
        foreach ($saved_settings as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        $_SERVER = $saved_server;
        $_FILES = $saved_files;
        chdir($saved_cwd);
        http_response_code(200);
        restore_error_handler();
    }
})();

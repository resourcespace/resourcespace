<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
    $fixtures = null;
});

it('reuses category paths when preparing and rebuilding a search column', function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['config_show_performance_footer', 'querylog', 'querytime', 'querycount']);
    $setting = 'thumbs_display_fields';
    $previous_value = null;
    $had_value = get_config_option([], $setting, $previous_value);
    $fixtures->onCleanup(static fn() => $had_value
        ? set_config_option(null, $setting, $previous_value)
        : delete_config_option([], $setting));
    $previous_preparation = get_sysvar(SYSVAR_PREPARING_SEARCH_FIELDS, null);
    $fixtures->onCleanup(static fn() => set_sysvar(SYSVAR_PREPARING_SEARCH_FIELDS, $previous_preparation));

    $field = $fixtures->field('Search tree ' . generateRandomTestID(12), 0, FIELD_TYPE_CATEGORY_TREE);
    $parent = set_node(null, $field, 'Parent', null, 1);
    $child = set_node(null, $field, 'Child', $parent, 1);
    $grandchild = set_node(null, $field, 'Grandchild', $child, 1);
    $other = set_node(null, $field, 'Other', $child, 2);
    $template = -$fixtures->user('search-tree-' . generateRandomTestID(12), 3);
    expect(get_resource_data($template, false))->toBeArray();
    $fixtures->trackResource($template);
    $resources = [$template, $fixtures->resource(1), $fixtures->resource(1), $fixtures->resource(1)];
    foreach ($resources as $resource) {
        expect(add_resource_nodes($resource, [$grandchild, $other], false, false))->toBeTrue();
    }
    $cached_values = static fn() => ps_array(
        "SELECT field{$field} AS value FROM resource WHERE ref IN (" . ps_param_insert(count($resources))
            . ') ORDER BY ref',
        ps_param_fill($resources, 'i')
    );
    $expected_values = static fn(string $parent_name): array => array_fill(
        0,
        count($resources),
        $parent_name . '/Child/Grandchild' . $GLOBALS['field_column_string_separator'] . $parent_name . '/Child/Other'
    );
    $rebuild = static function (bool $preparing) use ($field): void {
        $GLOBALS['config_show_performance_footer'] = true;
        $GLOBALS['querylog'] = [];
        $GLOBALS['querytime'] = 0;
        $GLOBALS['querycount'] = 0;
        update_fieldx($field, $preparing);
        $tree_queries = array_filter(
            $GLOBALS['querylog'],
            static fn(string $sql): bool => str_contains($sql, 'LEFT OUTER JOIN resource_node rn'),
            ARRAY_FILTER_USE_KEY
        );
        expect(array_sum(array_column($tree_queries, 'dupe')))->toBe(2);
        expect(array_sum(array_column(array_filter(
            $GLOBALS['querylog'],
            static fn(string $sql): bool => str_contains($sql, 'FROM resource WHERE ref = ? FOR UPDATE'),
            ARRAY_FILTER_USE_KEY
        ), 'dupe')))->toBeGreaterThanOrEqual(4);
    };

    config_register_search_field_columns([$field]);
    $rebuild(true);
    expect($cached_values())->toBe($expected_values('Parent'));

    // A stale snapshot must be refreshed even when every selected node already exists in it.
    $snapshot = get_category_tree_column_paths($field);
    expect(set_node($parent, $field, 'Renamed', null, 1))->toBe($parent);
    $field_data = get_resource_type_field($field);
    refresh_search_field_column_batch($resources, $field_data, $snapshot);
    expect($cached_values())->toBe($expected_values('Renamed'));

    expect(set_config_option(null, $setting, (string) $field))->toBeTrue();
    set_sysvar(SYSVAR_PREPARING_SEARCH_FIELDS, null);
    $rebuild(false);
    expect($cached_values())->toBe($expected_values('Renamed'));
});

it('saves system configuration through the admin form and applies typed overrides', function () use (&$fixtures): void {
    $booleans = [
        'relate_on_upload' => false,
        'relate_on_upload_default' => false,
        'show_status_and_access_on_upload' => false,
        'replace_resource_preserve_option' => false,
        'replace_resource_preserve_default' => false,
        'allow_smart_collections' => false,
        'themes_in_my_collections' => false,
        'user_account_auto_creation' => false,
        'registration_group_select' => false,
        'download_summary' => false,
        'download_usage' => false,
        'download_usage_email' => false,
        'usage_comment_blank' => false,
        'resource_share_expire_never' => true,
        'external_share_view_as_internal' => false,
        'simple_search_date' => true,
        'list_view_status_column' => false,
        'view_resource_collections' => false,
        'disable_geocoding' => false,
        'tabs_on_edit' => false,
        'public_collections_top_nav' => false,
        'mycontributions_link' => false,
    ];
    $options = [
        ...array_keys($booleans),
        'resource_share_expire_days',
        'user_account_auto_creation_usergroup',
        'portrait_landscape_field',
        'download_filename_format',
        'download_usage_options',
        ...CONFIG_SEARCH_FIELD_LISTS,
    ];
    $settings = array_diff($options, ['show_status_and_access_on_upload']);
    $fixtures = new IntegrationFixtures([
        ...$settings,
        'system_config_hide',
        'download_usage_options_retired',
        'resource_log_previous_ref',
        'system_wide_config_options',
    ]);
    // Assignment keeps the default reference shared with show_access_on_upload intact.
    $upload_status = $GLOBALS['show_status_and_access_on_upload'];
    $fixtures->onCleanup(static function () use ($upload_status): void {
        $GLOBALS['show_status_and_access_on_upload'] = $upload_status;
    });
    $clear = static function (string $name): void {
        ps_query(
            'DELETE FROM user_preferences WHERE user IS NULL AND usergroup IS NULL AND parameter = ?',
            ['s', $name]
        );
        clear_query_cache('preferences');
    };
    foreach ($options as $name) {
        $before = null;
        if (get_config_option([], $name, $before)) {
            $fixtures->onCleanup(static fn() => set_config_option(null, $name, $before));
            $clear($name);
        } else {
            $fixtures->onCleanup(static fn() => $clear($name));
        }
    }

    $suffix = generateRandomTestID(12);
    $orientation = $fixtures->field("Orientation {$suffix}");
    $date = $fixtures->field("Date {$suffix}", 0, FIELD_TYPE_DATE_AND_OPTIONAL_TIME);
    $group = $fixtures->group("Registration {$suffix}", 's');
    expect(save_usergroup($group, ['allow_registration_selection' => 1]))->not->toBeFalse();
    $admin = $fixtures->user("config-admin-{$suffix}", 3);
    $member = $fixtures->user("config-member-{$suffix}", $group);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $file_settings = [
        'baseurl' => $server->url,
        'session_autologout' => false,
        'enable_related_resources' => true,
        'allow_account_request' => true,
        'resource_share_expire_days_default' => 30,
        'simple_search_date' => false,
        'download_usage_options' => ['Print', 'Web'],
        'list_display_fields' => [8, 3, 12],
        'thumbs_display_fields' => [8],
        'sort_fields' => [12],
        'default_sort' => 'field12',
    ];
    $server->configure($file_settings);
    $headers = [];
    foreach ([$admin, $member] as $user) {
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);
        $headers[$user] = $server->sessionHeaders($session);
    }

    $path = '/pages/admin/admin_system_config.php';
    $page = $server->request($path, headers: $headers[$admin]);
    expect($page['status'])->toBe(200);
    $token = LocalTestServer::hiddenFields($page['body'], '//form[@id="SearchSystemPages"]');
    expect($token)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $rendered = static function (string $body): DOMXPath {
        $document = new DOMDocument();
        $document->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new DOMXPath($document);
    };
    $xpath = $rendered($page['body']);
    expect($xpath->evaluate('count(//input[@id="simple_search_date"][@checked])'))->toBe(0.0)
        ->and($xpath->evaluate('count(//input[@id="resource_share_expire_never"][@checked])'))->toBe(1.0)
        ->and($xpath->evaluate('string(//input[@id="resource_share_expire_days"]/@value)'))->toBe('150')
        ->and($xpath->evaluate('count(//*[@id="relate_on_upload_default"])'))->toBe(0.0)
        ->and($xpath->evaluate('count(//*[@id="user_account_auto_creation_usergroup"])'))->toBe(0.0);

    $payload = static fn(string $name, string $value): array => [
        'ajax' => 'true',
        'autosave' => 'true',
        'autosave_option_name' => $name,
        'autosave_option_value' => $value,
    ];
    $admin_headers = $headers[$admin];
    $save = static function (string $name, string $value) use ($server, $path, $admin_headers, $payload, $token): bool {
        $response = $server->request($path, $payload($name, $value) + $token, $admin_headers);
        expect($response['status'])->toBe(200);
        return json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR)['success'];
    };
    $save_usage_options = static function (
        array $options,
        string $version
    ) use (
        $server,
        $path,
        $admin_headers,
        $payload,
        $token
    ): array {
        $response = $server->request(
            $path,
            $payload('download_usage_options', json_encode(
                $options + ['order' => array_keys($options['active'])],
                JSON_THROW_ON_ERROR
            )) + $token
                + ['autosave_option_version' => $version],
            $admin_headers
        );
        expect($response['status'])->toBe(200)->and($response['body'])->toBeJson();
        return json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
    };
    $save_field_list = static function (
        string $name,
        string $value,
        string $current_version
    ) use (
        $server,
        $path,
        $admin_headers,
        $payload,
        $token
    ): array {
        $response = $server->request(
            $path,
            $payload($name, $value) + $token + ['autosave_option_version' => $current_version],
            $admin_headers
        );
        expect($response['status'])->toBe(200)->and($response['body'])->toBeJson();
        return json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
    };
    $fields_versions = [];
    foreach (CONFIG_SEARCH_FIELD_LISTS as $setting) {
        $fields_versions[$setting] = $xpath->evaluate('string(//input[@id="' . $setting . '"]/@data-option-version)');
        expect($xpath->evaluate('string(//input[@id="' . $setting . '"]/@value)'))
            ->toBe(implode(',', $file_settings[$setting]));
    }
    $thumbs_version = $fields_versions['thumbs_display_fields'];
    $reordered = $save_field_list('list_display_fields', '12,8,3', $fields_versions['list_display_fields']);
    expect($reordered['success'])->toBeTrue()
        ->and($save_field_list('list_display_fields', '8,12', $fields_versions['list_display_fields'])['success'])
        ->toBeFalse()
        ->and($save_field_list('thumbs_display_fields', '8,8', $thumbs_version)['success'])
        ->toBeFalse()
        ->and($save_field_list('thumbs_display_fields', '2147483647', $thumbs_version)['success'])
        ->toBeFalse()
        ->and($save_field_list('sort_fields', '8', $fields_versions['sort_fields'])['success'])
        ->toBeFalse()
        ->and($save_field_list('sort_fields', '8,12', $fields_versions['sort_fields'])['success'])
        ->toBeTrue();
    $empty = $save_field_list('thumbs_display_fields', '', $thumbs_version);
    expect($empty['success'])->toBeTrue();
    $version = $xpath->evaluate('string(//input[@id="download_usage_options"]/@data-option-version)');
    expect(json_decode($xpath->evaluate('string(//input[@id="download_usage_options"]/@value)'), true))
        ->toBe(['active' => ['Print', 'Web'], 'retired' => [], 'order' => [0, 1]]);
    $options_update = [
        'active' => [2 => str_repeat('Exhibition ', 22) . '2026', 0 => 'Printed "edition" \\ archive'],
        'retired' => [1 => 'Web'],
        'order' => [2, 0],
    ];
    expect($save_usage_options($options_update, $version)['success'])->toBeFalse();

    $change = $payload('simple_search_date', '1');
    $invalid_token = [$GLOBALS['CSRF_token_identifier'] => 'invalid'];
    $get_request = $server->request($path . '?' . http_build_query($change), headers: $headers[$admin]);
    expect($get_request['status'])->toBe(405)
        ->and($server->request($path, $change, $headers[$admin])['status'])->toBe(400)
        ->and($server->request($path, $change + $invalid_token, $headers[$admin])['status'])->toBe(400);
    // A CSRF token for a separate, authorised page allows the member to reach the configuration permission check.
    $preferences = $server->request('/pages/user/user_preferences.php', headers: $headers[$member]);
    $member_token = LocalTestServer::hiddenFields($preferences['body'], '//form[@id="SearchSystemPages"]');
    expect($member_token)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    expect($server->request($path, $change + $member_token, $headers[$member])['body'])->toBe('Permission denied.');
    $stored = null;
    expect(get_config_option([], 'simple_search_date', $stored))->toBeFalse();

    expect($save('relate_on_upload_default', '1'))->toBeFalse()
        ->and($save('replace_resource_preserve_default', '1'))->toBeFalse()
        ->and($save('download_usage_email', '1'))->toBeFalse()
        ->and($save('user_account_auto_creation_usergroup', (string) $group))->toBeFalse()
        ->and($save('list_view_status_column', 'true'))->toBeFalse()
        ->and($save('resource_share_expire_days', '29'))->toBeFalse()
        ->and($save('resource_share_expire_days', '3651'))->toBeFalse()
        ->and($save('resource_share_expire_days', '30.5'))->toBeFalse()
        ->and($save('portrait_landscape_field', (string) $date))->toBeFalse()
        ->and($save('portrait_landscape_field', '2147483647'))->toBeFalse();

    // Enable each parent before saving its dependent options.
    $values = array_map(static fn(bool $default): bool => !$default, $booleans);
    $values['simple_search_date'] = true; // Override the false value configured in the file.
    $values += [
        'resource_share_expire_days' => 120,
        'user_account_auto_creation_usergroup' => $group,
        'portrait_landscape_field' => $orientation,
        'download_filename_format' => 'Example %resource.%extension',
    ];
    foreach ($values as $name => $value) {
        $submitted = is_bool($value) ? (string) (int) $value : (string) $value;
        expect($save($name, $submitted))->toBeTrue($name);
        expect(get_config_option([], $name, $stored))->toBeTrue()
            ->and($stored)->toBe($submitted);
    }
    expect($save_usage_options(['active' => [], 'retired' => [0 => 'Print', 1 => 'Web']], $version)['success'])
        ->toBeFalse();
    expect($save_usage_options(array_replace($options_update, ['order' => [2, 2]]), $version)['success'])
        ->toBeFalse();
    $resource = $fixtures->resource(1);
    expect(update_field($resource, $orientation, 'Search field backfill'))->toBeTrue();
    $template = -$admin;
    expect(get_resource_data($template, false))->toBeArray();
    $fixtures->trackResource($template);
    expect(update_field($template, $orientation, 'Existing upload template'))->toBeTrue();
    $backfill = $save_field_list('thumbs_display_fields', (string) $orientation, $empty['version']);
    expect($backfill)->toHaveKey('success', false);
    $job_code = 'prepare_search_fields_thumbs_display_fields';
    $fixtures->onCleanup(static function () use ($job_code): void {
        foreach (job_queue_get_jobs(job_code: $job_code) as $job) {
            job_queue_delete($job['ref']);
        }
    });
    $server->configure($file_settings + ['offline_job_queue' => true]);
    $backfill = $save_field_list('thumbs_display_fields', (string) $orientation, $empty['version']);
    expect($backfill)->toHaveKeys(['success', 'queued'])
        ->and($backfill['success'])->toBeTrue()
        ->and($backfill['queued'])->toBeTrue();
    expect(get_config_option([], 'thumbs_display_fields', $stored))->toBeTrue()->and($stored)->toBe('');
    $jobs = job_queue_get_jobs('prepare_search_fields', STATUS_ACTIVE, job_code: $job_code);
    expect($jobs)->toHaveCount(1);

    $previous_preparation = get_sysvar(SYSVAR_PREPARING_SEARCH_FIELDS, null);
    $fixtures->onCleanup(static fn() => set_sysvar(SYSVAR_PREPARING_SEARCH_FIELDS, $previous_preparation));
    $previous_thumbs_fields = $GLOBALS['thumbs_display_fields'];
    config_register_search_field_columns([$orientation]);
    expect(get_resource_table_joins())->not->toContain($orientation);
    expect(get_resource_table_joins(true))->toContain($orientation);
    // These writes happen after registration, with the previous search list still loaded.
    expect(update_field($resource, $orientation, 'Edited during preparation'))->toBeTrue();
    $cached_value = static fn(int $ref) => ps_value(
        "SELECT field{$orientation} AS value FROM resource WHERE ref = ?",
        ['i', $ref],
        null
    );
    expect($cached_value($resource))->toBe('Edited during preparation')
        ->and($cached_value($template))->toBeNull();
    $upload = copy_resource($template, origin: 'test upload');
    expect($upload)->toBeInt();
    $fixtures->trackResource($upload);
    expect($cached_value($upload))->toBe('Existing upload template');

    update_fieldx($orientation, true);
    $post = $_POST;
    try {
        $_POST = ["field_{$orientation}" => 'Edited after scanning'];
        expect(save_resource_data($resource, false, (string) $orientation))->toBeTrue();
    } finally {
        $_POST = $post;
    }
    expect($cached_value($resource))->toBe('Edited after scanning');
    $new_upload = $fixtures->resource(1);
    $upload_node = set_node(null, $orientation, 'New upload after scanning', null, 0);
    expect(add_resource_nodes($new_upload, [$upload_node], false, false))->toBeTrue()
        ->and($cached_value($new_upload))->toBe('New upload after scanning');

    expect(job_queue_run_job($jobs[0], false))->toBe('Complete');
    expect((int) job_queue_get_job($jobs[0]['ref'])['status'])->toBe(STATUS_COMPLETE);
    expect(get_config_option([], 'thumbs_display_fields', $stored))->toBeTrue()
        ->and($stored)->toBe((string) $orientation);
    expect(get_search_field_column_state()['preparing'])->toBe([])
        ->and($cached_value($resource))->toBe('Edited after scanning')
        ->and($cached_value($template))->toBe('Existing upload template');
    $upload_after_backfill = copy_resource($template, origin: 'test upload');
    expect($upload_after_backfill)->toBeInt();
    $fixtures->trackResource($upload_after_backfill);
    expect($cached_value($upload_after_backfill))->toBe('Existing upload template');
    $current_thumbs_fields = $GLOBALS['thumbs_display_fields'];
    try {
        $GLOBALS['thumbs_display_fields'] = $previous_thumbs_fields;
        expect(get_resource_table_joins())->not->toContain($orientation);
        expect(update_field($resource, $orientation, 'Edited by an earlier request'))->toBeTrue()
            ->and($cached_value($resource))->toBe('Edited by an earlier request');
    } finally {
        $GLOBALS['thumbs_display_fields'] = $current_thumbs_fields;
    }
    update_resource_field_column($template, $orientation, '');
    update_fieldx($orientation);
    expect($cached_value($template))->toBe('Existing upload template');
    $server->configure($file_settings);
    $download_log = resource_log($resource, LOG_CODE_DOWNLOADED, 0, usage: 1);
    expect($download_log)->toBeInt();
    $saved = $save_usage_options($options_update, $version);
    expect($saved['success'])->toBeTrue()
        ->and($save_usage_options($options_update, $version)['success'])->toBeFalse();
    expect($save_usage_options(['active' => [1 => 'New label'], 'retired' => [0 => 'Print']], $saved['version']))
        ->toHaveKey('success', false);
    expect(get_config_option([], 'download_usage_options', $stored))->toBeTrue()
        ->and(strlen($stored))->toBeGreaterThan(255)
        ->and(json_decode($stored, true))->toBe($options_update);
    process_config_options([]);
    expect($GLOBALS['list_display_fields'])->toBe([12, 8, 3])
        ->and($GLOBALS['sort_fields'])->toBe([8, 12])
        ->and($GLOBALS['thumbs_display_fields'])->toBe([$orientation]);
    expect($GLOBALS['download_usage_options'])->toBe($options_update['active'])
        ->and($GLOBALS['download_usage_options_retired'])->toBe($options_update['retired']);
    $usage_page = $server->request("/pages/download_usage.php?ref={$resource}", headers: $admin_headers);
    $usage_form = $rendered($usage_page['body']);
    expect($usage_form->evaluate('count(//select[@id="usage"]/option[@value="1"])'))->toBe(0.0)
        ->and($usage_form->evaluate('string(//select[@id="usage"]/option[2]/@value)'))->toBe('2')
        ->and($usage_form->evaluate('string(//select[@id="usage"]/option[3]/@value)'))->toBe('0');
    $log_page = $server->request("/pages/log_entry.php?ref={$download_log}", headers: $admin_headers);
    expect($rendered($log_page['body'])->evaluate('count(//*[normalize-space(text())="Web"])'))->toBe(1.0);
    foreach ($values as $name => $value) {
        expect($GLOBALS[$name])->toBe($value);
    }

    expect($save('user_account_auto_creation_usergroup', '2147483647'))->toBeFalse()
        ->and($save('user_account_auto_creation_usergroup', $group . 'e0'))->toBeFalse()
        ->and($save('resource_share_expire_days_default', '121'))->toBeFalse()
        ->and($save('resource_share_expire_days', '0'))->toBeFalse();
    $xpath = $rendered($server->request($path, headers: $headers[$admin])['body']);
    expect($xpath->evaluate('count(//input[@id="simple_search_date"][@checked])'))->toBe(1.0)
        ->and($xpath->evaluate('count(//input[@id="relate_on_upload_default"][@checked])'))->toBe(1.0)
        ->and($xpath->evaluate('string(//select[@id="portrait_landscape_field"]/option[@selected]/@value)'))
        ->toBe((string) $orientation)
        ->and($xpath->evaluate('string(//select[@id="user_account_auto_creation_usergroup"]/option[@selected]/@value)'))
        ->toBe((string) $group);

    expect($save('portrait_landscape_field', ''))->toBeTrue();
    process_config_options([]);
    expect($GLOBALS['portrait_landscape_field'])->toBe('');

    $server->configure($file_settings + [
        'system_config_hide' => ['simple_search_date'],
        'remove_usage_textbox' => true,
    ]);
    expect($save('simple_search_date', '0'))->toBeFalse()
        ->and($save('usage_comment_blank', '0'))->toBeFalse();
    $xpath = $rendered($server->request($path, headers: $headers[$admin])['body']);
    expect($xpath->evaluate('count(//*[@id="simple_search_date"])'))->toBe(0.0);
    $GLOBALS['system_config_hide'] = ['simple_search_date'];
    $GLOBALS['simple_search_date'] = false;
    process_config_options([]);
    expect($GLOBALS['simple_search_date'])->toBeFalse()
        ->and(get_config_option([], 'simple_search_date', $stored))->toBeTrue()
        ->and($stored)->toBe('1');
});

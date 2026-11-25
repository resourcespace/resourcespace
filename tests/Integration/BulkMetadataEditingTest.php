<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'get_required_fields', 'update_archive_required_fields_check', 'update_archive_required_fields_check_hook',
        'plugins', 'hook_cache', 'user_local_timezone',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('applies a bulk text edit to each collection member using its original value', function (
    string $mode,
    string $input,
    string $first_expected,
    string $second_expected,
) use (&$fixtures): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Bulk metadata ' . generateRandomTestID(16));
    $target = $fixtures->field('Bulk test target');
    $source = $fixtures->field('Bulk test source');
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $errors = [];
    foreach ([$first => 'red apple', $second => 'red plum', $control => 'red pear'] as $resource => $value) {
        expect(update_field($resource, $target, $value, $errors))->toBeTrue()
            ->and(update_field($resource, $source, $value . ' source', $errors))->toBeTrue();
    }
    expect(add_resource_to_collection($first, $collection))->toBeTrue()
        ->and(add_resource_to_collection($second, $collection))->toBeTrue();

    expect(save_resource_data_multi($collection, [], [
        "editthis_field_{$target}" => 'yes',
        "modeselect_{$target}" => $mode,
        "field_{$target}" => $input,
        "find_{$target}" => 'red',
        "replace_{$target}" => 'ripe',
        "copy_from_field_{$target}" => $source,
    ]))->toBeTrue();

    expect(get_data_by_field($first, $target, true))->toBe($first_expected)
        ->and(get_data_by_field($second, $target, true))->toBe($second_expected)
        ->and(get_data_by_field($control, $target, true))->toBe('red pear');
})->with([
    'find and replace' => ['FR', '', 'ripe apple', 'ripe plum'],
    'replace' => ['RT', 'ripe', 'ripe', 'ripe'],
    'copy from a field' => ['CF', '', 'red apple source', 'red plum source'],
    'prepend' => ['PP', 'ripe', 'ripe red apple', 'ripe red plum'],
    'append' => ['AP', 'ripe', 'red apple ripe', 'red plum ripe'],
    'remove' => ['RM', 'red ', 'apple', 'plum'],
]);

it('logs only changed option selections when bulk appending and removing', function () use (&$fixtures): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Bulk options ' . generateRandomTestID(16));
    $field = $fixtures->field('Bulk options test', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $apple = set_node(null, $field, 'Apple', null, 10);
    $plum = set_node(null, $field, 'Plum', null, 20);
    expect($apple)->toBeInt()->and($plum)->toBeInt();

    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    add_resource_nodes($first, [$apple]);
    add_resource_nodes($second, [$plum]);
    expect(add_resource_to_collection($first, $collection))->toBeTrue()
        ->and(add_resource_to_collection($second, $collection))->toBeTrue();
    $edit = static function (string $mode, int $node) use ($collection, $field): void {
        expect(save_resource_data_multi($collection, [], [
            "editthis_field_{$field}" => 'yes',
            "modeselect_{$field}" => $mode,
            'nodes' => [$field => [$node]],
        ]))->toBeTrue();
    };
    $log_count = static fn (int $ref): int => (int) ps_value(
        'SELECT COUNT(*) AS value FROM resource_log WHERE resource = ? AND resource_type_field = ?',
        ['i', $ref, 'i', $field],
        0
    );

    $first_before = $log_count($first);
    $second_before = $log_count($second);
    $edit('AP', $apple);
    expect(get_resource_nodes($first, $field))->toBe([$apple])
        ->and(get_resource_nodes($second, $field))->toEqualCanonicalizing([$apple, $plum])
        ->and($log_count($first))->toBe($first_before)
        ->and($log_count($second))->toBe($second_before + 1);

    $edit('RM', $plum);
    expect(get_resource_nodes($first, $field))->toBe([$apple])
        ->and(get_resource_nodes($second, $field))->toBe([$apple])
        ->and($log_count($first))->toBe($first_before)
        ->and($log_count($second))->toBe($second_before + 2);

});

it('restores the prior bulk metadata value and preserves required fields when the prior value was blank', function (
    bool $fixed_list,
) use (&$fixtures): void {
    require_once RESOURCESPACE_BASE_PATH . '/plugins/rse_version/hooks/all.php';
    if (!in_array('rse_version', $GLOBALS['plugins'], true)) {
        $GLOBALS['plugins'][] = 'rse_version';
    }
    $GLOBALS['hook_cache'] = [];
    $GLOBALS['user_local_timezone'] = 'UTC';

    $previous = get_sysvar('fixed_list_revert_enabled', null);
    $fixtures->onCleanup(static fn () => set_sysvar('fixed_list_revert_enabled', $previous));
    set_sysvar('fixed_list_revert_enabled', '2024-01-01 00:00:00');

    $type = $fixed_list ? FIELD_TYPE_CHECK_BOX_LIST : FIELD_TYPE_TEXT_BOX_SINGLE_LINE;
    $field = $fixtures->field('Required historical value ' . generateRandomTestID(10), 1, $type);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['required' => true]);
    expect(save_resource_type_field($field, $columns, ['required' => 1]))->toBeTrue();
    if ($fixed_list) {
        expect(set_node(null, $field, 'First value', null, 10))->toBeInt();
        expect(set_node(null, $field, 'Second value', null, 20))->toBeInt();
    }
    $resource = $fixtures->resource(1, 0);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Revert metadata ' . generateRandomTestID(10));
    expect(add_resource_to_collection($resource, $collection))->toBeTrue();
    $errors = [];
    expect(update_field($resource, $field, 'First value', $errors))->toBeTrue();
    ps_query('UPDATE resource_log SET date = ? WHERE resource = ? AND resource_type_field = ?', [
        's', '2024-03-01 09:00:00', 'i', $resource, 'i', $field,
    ]);
    expect(update_field($resource, $field, 'Second value', $errors))->toBeTrue();
    expect(get_data_by_field($resource, $field, true))->toBe('Second value');

    $batch_edit = static fn (string $mode, string $date = '') => save_resource_data_multi($collection, [], [
        'editthis_field_' . $field => 'yes', 'modeselect_' . $field => $mode,
        'field_' . $field => '', 'revert_' . $field => $date,
    ]);
    foreach ([['RT', ''], ['Revert', '2024-03-01 08:00']] as [$mode, $date]) {
        $failure = $batch_edit($mode, $date);
        expect($failure)->toBeArray();
        $warning = $fixed_list && $mode === 'Revert' ? 'batch_edit_save_warning_alert' : 'requiredfield';
        expect(implode(' ', $failure))->toContain($GLOBALS['lang'][$warning]);
        expect(get_data_by_field($resource, $field, true))->toBe('Second value');
    }

    expect($batch_edit('Revert', '2024-03-01 10:00'))->toBeTrue();
    expect(get_data_by_field($resource, $field, true))->toBe('First value');
})->with(['text' => [false], 'fixed list' => [true]]);

it('publishes only collection members whose required metadata is complete', function () use (&$fixtures): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Workflow batch ' . generateRandomTestID(12));
    $field = $fixtures->field('Required workflow field', 1);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['required' => true]);
    expect(save_resource_type_field($field, $columns, ['required' => 1]))->toBeTrue();
    unset($GLOBALS['get_required_fields'][1]);
    $missing = $fixtures->resource(1, -2);
    $complete = $fixtures->resource(1, -2);
    $errors = [];
    foreach (get_required_fields(1) as $required) {
        expect(update_field($complete, $required['ref'], 'Complete metadata', $errors))->toBeTrue();
    }
    expect(add_resource_to_collection($missing, $collection))->toBeTrue()
        ->and(add_resource_to_collection($complete, $collection))->toBeTrue();

    $result = save_resource_data_multi($collection, [], ['editthis_status' => 'yes', 'status' => 0]);
    expect($result)->toBeArray();
    expect(implode(' ', $result))->toContain($GLOBALS['lang']['error-edit_status_change_missing_required_fields'])
        ->toContain((string) $missing);
    expect((int) get_resource_data($missing, false)['archive'])->toBe(-2)
        ->and((int) get_resource_data($complete, false)['archive'])->toBe(0);
});

it('allows incomplete metadata in the submission or deletion state but validates on publication', function () use (&$fixtures): void {
    $field = $fixtures->field('Review requirement', 1);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['required' => true]);
    expect(save_resource_type_field($field, $columns, ['required' => 1]))->toBeTrue();
    unset($GLOBALS['get_required_fields'][1]);
    $resource = $fixtures->resource(1, -2);

    expect(update_archive_required_fields_check($resource, $GLOBALS['resource_deletion_state']))->toBeEmpty()
        ->and(update_archive_required_fields_check($resource, -2))->toBeEmpty()
        ->and(array_column(update_archive_required_fields_check($resource, 0), 'ref'))->toContain($field);

    $errors = [];
    foreach (get_required_fields(1) as $required) {
        expect(update_field($resource, $required['ref'], 'Complete metadata', $errors))->toBeTrue();
    }
    unset($GLOBALS['update_archive_required_fields_check'][$resource]);
    expect(update_archive_required_fields_check($resource, 0))->toBeEmpty();
});

it('honours the workflow destination’s required-field exemption', function () use (&$fixtures): void {
    require_once dirname(__DIR__, 2) . '/plugins/rse_workflow/hooks/all.php';
    if (!in_array('rse_workflow', $GLOBALS['plugins'], true)) {
        $GLOBALS['plugins'][] = 'rse_workflow';
    }
    $GLOBALS['hook_cache'] = [];

    $state = (int) ps_value('SELECT COALESCE(MAX(code), 3) + 1 AS value FROM archive_states', [], 4);
    ps_query(
        'INSERT INTO archive_states (code, name, skip_required_fields) VALUES (?, ?, 0)',
        ['i', $state, 's', 'Required metadata test destination']
    );
    $state_ref = sql_insert_id();
    $fixtures->onCleanup(static function () use ($state_ref): void {
        ps_query('DELETE FROM archive_states WHERE ref = ?', ['i', $state_ref]);
        clear_query_cache('workflow');
    });
    clear_query_cache('workflow');

    $field = $fixtures->field('Workflow exemption requirement', 1);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['required' => true]);
    expect(save_resource_type_field($field, $columns, ['required' => 1]))->toBeTrue();
    unset($GLOBALS['get_required_fields'][1]);
    $resource = $fixtures->resource(1, -2);
    expect(array_column(update_archive_required_fields_check($resource, $state), 'ref'))->toContain($field);

    ps_query('UPDATE archive_states SET skip_required_fields = 1 WHERE ref = ?', ['i', $state_ref]);
    clear_query_cache('workflow');
    unset(
        $GLOBALS['update_archive_required_fields_check'][$resource],
        $GLOBALS['update_archive_required_fields_check_hook'][$state]
    );
    expect(update_archive_required_fields_check($resource, $state))->toBeEmpty();
});

it('updates location and zoom for selected collection resources and logs the coordinate change', function () use (
    &$fixtures,
): void {
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Located ' . generateRandomTestID(12));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $outside = $fixtures->resource(1, 0);
    foreach ([$first, $second] as $ref) {
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
    }

    expect(save_resource_data_multi($collection, [], [
        'editlocation' => 'yes', 'location' => '51.500, -0.125', 'editmapzoom' => 'yes', 'mapzoom' => '12',
    ]))->toBeTrue();
    foreach ([$first, $second] as $ref) {
        $resource = get_resource_data($ref, false);
        expect((float) $resource['geo_lat'])->toBe(51.5)
            ->and((float) $resource['geo_long'])->toBe(-0.125)
            ->and((int) $resource['mapzoom'])->toBe(12)
            ->and(array_column(get_resource_log($ref)['data'], 'notes'))->toContain('Edited Location');
    }
    expect(get_resource_data($outside, false))->toMatchArray(['geo_lat' => null, 'geo_long' => null, 'mapzoom' => null]);
    expect(save_resource_data_multi($collection, [], ['editlocation' => 'yes', 'location' => 'invalid']))
        ->toContain($GLOBALS['lang']['location-validation-error']);
    expect((float) get_resource_data($first, false)['geo_lat'])->toBe(51.5);
});

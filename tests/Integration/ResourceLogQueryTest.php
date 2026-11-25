<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'lang']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('filters recent resource log entries by reference, time, field and event with access checks', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $first_field = $fixtures->field('Resource log text');
    $second_field = $fixtures->field('Resource log options', 1, FIELD_TYPE_CHECK_BOX_LIST);
    $insert = static function (int $hours_ago, int $field, string $code) use ($resource): int {
        ps_query(
            'INSERT INTO resource_log (date, resource, resource_type_field, type)
             VALUES (DATE_SUB(NOW(), INTERVAL ? HOUR), ?, ?, ?)',
            ['i', $hours_ago, 'i', $resource, 'i', $field, 's', $code]
        );
        return sql_insert_id();
    };
    $fixtures->onCleanup(static function () use ($resource): void {
        ps_query('DELETE FROM resource_log WHERE resource = ?', ['i', $resource]);
    });

    $old = $insert(73, $first_field, LOG_CODE_EDITED);
    $recent_edit = $insert(47, $first_field, LOG_CODE_EDITED);
    $recent_copy = $insert(2, $second_field, LOG_CODE_COPIED);
    $recent_delete = $insert(1, $second_field, LOG_CODE_DELETED);
    $recent = static fn (array $fields, array $codes = []): array =>
        array_column(resource_log_last_rows(0, 3, 0, $fields, $codes), 'ref');

    expect(array_column(resource_log_last_rows($old, 1, 0, [$first_field]), 'ref'))
        ->toEqualCanonicalizing([$old, $recent_edit])
        ->and($recent([$first_field]))->toBe([$recent_edit])
        ->and($recent([$first_field, $second_field]))->toEqualCanonicalizing([$recent_edit, $recent_copy, $recent_delete])
        ->and($recent([23.34, 'invalid field', $second_field], ['invalid event', LOG_CODE_DELETED]))->toBe([$recent_delete])
        ->and($recent([$first_field, $second_field], [LOG_CODE_EDITED, LOG_CODE_COPIED]))
        ->toEqualCanonicalizing([$recent_edit, $recent_copy]);
    expect(resource_log_last_rows($old, 0, 2, [$first_field, $second_field]))->toHaveCount(2);

    $GLOBALS['userpermissions'] = array_values(array_diff($GLOBALS['userpermissions'], ['v']));
    expect(resource_log_last_rows($old, 3))->toBeEmpty();
});

it('queries the combined activity log by user, table, notes and record reference', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $user = $fixtures->user('activity_' . $suffix, 3);
    $resource = $fixtures->resource(1, 0);
    $collection = $fixtures->collection($user, 'Activity fixture ' . $suffix);
    $fixtures->onCleanup(static function () use ($user): void {
        ps_query('DELETE FROM activity_log WHERE user = ?', ['i', $user]);
        ps_query('DELETE FROM resource_log WHERE user = ?', ['i', $user]);
        ps_query('DELETE FROM collection_log WHERE user = ?', ['i', $user]);
    });
    setup_user(get_user($user));
    $where = [
        'activity_log' => "activity_log.user = {$user} AND ",
        'resource_log' => "resource_log.user = {$user} AND ",
        'collection_log' => "collection_log.user = {$user} AND ",
    ];
    // Capture any login or fixture setup entries before exercising the log query.
    $baseline = get_activity_log('', 0, 0, $where, '', null, true);
    foreach (['uploaded', 'edited', 'viewed', 'updated'] as $action) {
        log_activity("Fixture activity {$action}", LOG_CODE_EDITED, "Local entry {$action}", 'activity');
    }
    foreach ([LOG_CODE_CREATED, LOG_CODE_DOWNLOADED, LOG_CODE_EMAILED, LOG_CODE_EDITED] as $action) {
        resource_log($resource, $action, null, 'Fixture resource event ' . $action);
    }
    collection_log($collection, LOG_CODE_COLLECTION_ADDED_RESOURCE, $resource, 'Fixture collection addition');
    collection_log($collection, LOG_CODE_COLLECTION_REMOVED_RESOURCE, $resource, 'Fixture collection removal');
    collection_log($collection, LOG_CODE_COLLECTION_SHARED_COLLECTION, null, 'Fixture collection share');
    collection_log($collection, LOG_CODE_COLLECTION_DELETED_COLLECTION, null, 'Fixture collection deletion');

    $get = static fn (string $query = '', string $table = '', ?int $ref = null): array =>
        get_activity_log($query, 0, -1, $where, $table, $ref);
    $same = array_values(array_filter($get(), static fn (array $row): bool => str_starts_with($row['notes'] ?? '', 'Fixture ')));
    expect($same)->toHaveCount(12)
        ->and($get('Fixture activity', 'activity'))->toHaveCount(4)
        ->and($get('Fixture resource', 'resource', $resource))->toHaveCount(4)
        ->and($get('Fixture collection', 'resource', $resource))->toHaveCount(2)
        ->and($get('Fixture collection', 'collection', $collection))->toHaveCount(2)
        ->and($get('Fixture collection'))->toHaveCount(4)
        ->and(get_activity_log('', 0, 0, $where, '', null, true))->toBe($baseline + 12)
        ->and(get_activity_log('Fixture ', 0, 3, $where, '', null))->toHaveCount(3);

    $GLOBALS['lang']['collectionlog-' . LOG_CODE_COLLECTION_ADDED_RESOURCE] = 'Quote " and apostrophe \' and &';
    expect($get('Fixture collection'))->toHaveCount(4)
        ->and(array_column($get('Fixture collection'), 'operation'))->toContain('Quote " and apostrophe \' and &');
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['get_resource_data_cache']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('preserves metadata after required, locked and permission checks fail', function () use (&$fixtures): void {
    global $userref;

    $id = generateRandomTestID(8);
    $field = $fixtures->field('Rejected edits ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'rejected' . $id);
    $resource = $fixtures->resource(1, 0);
    $original = 'Preserve this value';
    $errors = [];
    expect(!update_field($resource, $field, $original, $errors))->toBeFalse('Rejected edits setup - ');

    ps_query('UPDATE resource_type_field SET required = 1 WHERE ref = ?', ['i', $field]);
    clear_query_cache('schema');
    $errors = [];
    expect(update_field($resource, $field, '', $errors) !== false || $errors === []
        || get_data_by_field($resource, $field) !== $original)->toBeFalse('Required field accepted an empty value or lost its previous value - ');

    $other = $fixtures->user('lockowner' . $id, 2);
    ps_query('UPDATE resource SET lock_user = ? WHERE ref = ?', ['i', $other, 'i', $resource]);
    $GLOBALS['get_resource_data_cache'] = [];
    $errors = [];
    $logs = count(get_resource_log($resource)['data']);
    expect(update_field($resource, $field, 'Forbidden replacement', $errors) !== false || $errors === []
        || get_data_by_field($resource, $field) !== $original
        || count(get_resource_log($resource)['data']) !== $logs)->toBeFalse('An edit to another user\'s locked resource changed metadata or logs - ');

    // The lock owner can still update metadata.
    ps_query('UPDATE resource SET lock_user = ? WHERE ref = ?', ['i', $userref, 'i', $resource]);
    $GLOBALS['get_resource_data_cache'] = [];
    $errors = [];
    expect(!update_field($resource, $field, 'Owner replacement', $errors)
        || $errors !== [] || get_data_by_field($resource, $field) !== 'Owner replacement')->toBeFalse('Lock owner could not update metadata - ');
});

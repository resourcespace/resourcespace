<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['multiple', 'is_template', 'upload_review_mode', 'check_edit_checksums', 'userpermissions', 'uploadparams']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('rejects required, stale and disallowed edit form changes', function () use (&$fixtures): void {
    $permissions = $GLOBALS['userpermissions'];
    $GLOBALS['multiple'] = false;
    $GLOBALS['is_template'] = false;
    $GLOBALS['upload_review_mode'] = false;
    $GLOBALS['uploadparams'] = [];
    $resource = $fixtures->resource(1, 0);
    $field = $fixtures->field('Edit form fixture', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'editformfixture', true);
    ps_query('UPDATE resource_type_field SET required = 1 WHERE ref = ?', ['i', $field]);
    clear_query_cache('schema');
    $_GET = [];
    $_POST = ['autosave_field' => $field, 'field_' . $field => 'Initial searchable value', 'field_' . $field . '_displayed' => 'block'];
    expect(process_edit_form($resource, get_resource_data($resource, false)) === true)->toBeTrue('Valid edit form submission failed');
    expect(get_data_by_field($resource, $field, true) === 'Initial searchable value')->toBeTrue('Form edit did not persist');
    $_POST['field_' . $field] = '';
    $result = process_edit_form($resource, get_resource_data($resource, false));
    expect(is_array($result) && isset($result[$field]))->toBeTrue('Required form field accepted empty value');
    expect(get_data_by_field($resource, $field, true) === 'Initial searchable value')->toBeTrue('Invalid form edit cleared required value');
    $_POST['field_' . $field] = 'Conflicting edit';
    $_POST['field_' . $field . '_checksum'] = md5('outdated form value');
    $result = process_edit_form($resource, get_resource_data($resource, false));
    expect(is_array($result) && isset($result[$field]))->toBeTrue('Stale form checksum accepted');
    expect(get_data_by_field($resource, $field, true) === 'Initial searchable value')->toBeTrue('Stale form overwrote persisted value');
    unset($_POST['field_' . $field . '_checksum']);
    $GLOBALS['userpermissions'] = array_merge($permissions, ['F' . $field]);
    $_POST['field_' . $field] = 'Unauthorized value';
    process_edit_form($resource, get_resource_data($resource, false));
    expect(get_data_by_field($resource, $field, true) === 'Initial searchable value')->toBeTrue('Form edited forbidden metadata field');
    $GLOBALS['userpermissions'] = ['e0'];
    $_POST = ['autosave_field' => 'Status', 'status' => '-1'];
    process_edit_form($resource, get_resource_data($resource, false));
    expect(get_resource_data($resource, false)['archive'] == 0)->toBeTrue('Form applied forbidden workflow transition');
});

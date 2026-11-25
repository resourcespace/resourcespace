<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'field_column_string_separator', 'data_joins']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('copies metadata independently and rejects invalid or unavailable sources', function () use (&$fixtures): void {
    global $userpermissions;

    $id = generateRandomTestID(8);
    $field = $fixtures->field('Copy isolation ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'copy' . $id, true);
    $source = $fixtures->resource(1, 0, -1, '', 'txt', false);
    $errors = [];
    expect(!update_field($source, $field, 'Original value', $errors))->toBeFalse('Copy isolation setup - ');

    $count = ps_value('SELECT COUNT(*) AS value FROM resource', [], 0);
    $missing = ps_value('SELECT MAX(ref) + 1 AS value FROM resource', [], 1);
    expect(copy_resource($missing) !== false || ps_value('SELECT COUNT(*) AS value FROM resource', [], 0) != $count)->toBeFalse('Copying a missing resource created a record - ');

    $copy = copy_resource($source);
    expect($copy)->toBeInt();
    $fixtures->trackResource($copy);
    $data = get_resource_data($copy, false);
    expect($copy == $source || $data === false || (int) $data['no_file'] !== 1
        || get_data_by_field($copy, $field) !== 'Original value')->toBeFalse('Resource copy did not initialise independently - ');
    expect(!update_field($copy, $field, 'Copy value', $errors)
        || get_data_by_field($source, $field) !== 'Original value')->toBeFalse('Editing copied metadata changed the source - ');
    expect(!update_field($source, $field, '', $errors) || get_data_by_field($copy, $field) !== 'Copy value')->toBeFalse('Clearing source metadata changed the copy - ');

    // Copying must respect metadata exclusions and field permissions.
    $omitted = $fixtures->field('Omitted copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'omit' . $id);
    $hidden = $fixtures->field('Hidden copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'hidden' . $id);
    $readonly = $fixtures->field('Uneditable copy field ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'uneditable' . $id);
    foreach ([$omitted, $hidden, $readonly] as $ref) {
        expect(!update_field($source, $ref, 'Do not copy', $errors))->toBeFalse('Excluded copy fields setup - ');
    }
    ps_query('UPDATE resource_type_field SET omit_when_copying = 1 WHERE ref = ?', ['i', $omitted]);
    clear_query_cache('schema');
    $userpermissions[] = 'f-' . $hidden;
    $userpermissions[] = 'F' . $readonly;
    $filtered_copy = copy_resource($source);
    expect($filtered_copy)->toBeInt();
    $fixtures->trackResource($filtered_copy);
    expect(get_resource_data($filtered_copy, false) === false)->toBeFalse('Filtered resource copy failed - ');
    foreach ([$omitted, $hidden, $readonly] as $ref) {
        expect(get_data_by_field($filtered_copy, $ref) !== '' || get_data_by_field($source, $ref) !== 'Do not copy')->toBeFalse('Copy retained excluded metadata or changed its source - ');
    }
});

it('records the copied title in the new resource log', function () use (&$fixtures): void {
    $source = $fixtures->resource(1, 0);
    $title = 'Copied title ' . generateRandomTestID(8);
    $field = (int) $GLOBALS['view_title_field'];
    $errors = [];
    expect(update_field($source, $field, $title, $errors))->toBeTrue();
    $copy = copy_resource($source);
    expect($copy)->toBeInt();
    $fixtures->trackResource($copy);

    expect(get_data_by_field($copy, $field))->toBe($title);
    $edits = get_resource_log($copy, -1, ['r.type' => LOG_CODE_EDITED])['data'];
    expect(array_column($edits, 'diff', 'title'))->toHaveKey(get_resource_type_field($field)['title'], '+ ' . $title);
});

it('stores copied locked options with the configured joined-field separator', function () use (&$fixtures): void {
    $field = $fixtures->field('Locked checklist', 1, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $a = set_node(null, $field, '~en:Scanned negative~fr:Négatif scanné', null, 10);
    $b = set_node(null, $field, '~en:Digital camera~fr:Appareil photo numérique', null, 20);
    $source = $fixtures->resource(1, 0);
    $destination = $fixtures->resource(1, 0);
    add_resource_nodes($source, [$a, $b]);
    $GLOBALS['field_column_string_separator'] = '|:|';
    $GLOBALS['data_joins'][] = $field;
    $fields = get_resource_field_data($destination, false, false);
    $selected = [];
    copy_locked_fields($destination, $fields, $selected, [$field], $source, true);

    expect(get_resource_nodes($destination, $field))->toEqualCanonicalizing([$a, $b]);
    expect(get_resource_data($destination, false)["field{$field}"])->toContain('|:|');
});

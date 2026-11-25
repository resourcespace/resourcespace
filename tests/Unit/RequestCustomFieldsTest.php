<?php

declare(strict_types=1);

require_once RESOURCESPACE_BASE_PATH . '/include/request_functions.php';

$saved_request_field_types = [];
beforeEach(function () use (&$saved_request_field_types): void {
    $saved_request_field_types = array_intersect_key($GLOBALS, ['FIXED_LIST_FIELD_TYPES' => true]);
    $GLOBALS['FIXED_LIST_FIELD_TYPES'] = [FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_CATEGORY_TREE];
});
afterEach(function () use (&$saved_request_field_types): void {
    unset($GLOBALS['FIXED_LIST_FIELD_TYPES']);
    foreach ($saved_request_field_types as $key => $value) {
        $GLOBALS[$key] = $value;
    }
});

it('rejects custom request fields with missing properties', function (string $missing): void {
    $field = [
        'id' => 1,
        'title' => 'Roles',
        'type' => FIELD_TYPE_CHECK_BOX_LIST,
        'required' => true,
        'options' => ['Editor'],
    ];
    unset($field[$missing]);
    expect(get_valid_custom_fields([$field]))->toBe([]);
})->with(['id', 'title', 'type', 'required', 'options']);

it('generates HTML properties for a valid custom request field', function (): void {
    $field = ['id' => 1, 'title' => 'Full name', 'type' => FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'required' => false];
    $validated = get_valid_custom_fields([$field]);

    expect($validated)->toBe([$field])
        ->and(gen_custom_fields_html_props($validated, [])[0]['html_properties'])
        ->toBe(['id' => 'custom_field_1', 'name' => 'custom_field_1']);
});

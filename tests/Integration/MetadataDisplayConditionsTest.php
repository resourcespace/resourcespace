<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['ref', 'use', 'display_check_data', 'userpermissions']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('evaluates saved display conditions against matching metadata', function (
    int $type,
    string $value,
    string $condition,
    bool $expected,
    ?string $parent = null,
) use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $dependency = $fixtures->field('Display dependency', 0, $type, 'displaydependency');
    $candidate = $fixtures->field('Conditional field');
    $parent_node = $parent === null ? null : set_node(null, $dependency, $parent, null, 10);
    $node = set_node(null, $dependency, $value, $parent_node, 10);
    expect($node)->toBeInt();
    add_resource_nodes($resource, [$node]);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['display_condition' => true]);
    expect(save_resource_type_field($candidate, $columns, ['display_condition' => $condition]))->toBeTrue();

    $GLOBALS['ref'] = $GLOBALS['use'] = $resource;
    $fields = get_resource_field_data($resource, false, false);
    $GLOBALS['display_check_data'] = $fields;
    $conditional = array_column($fields, null, 'ref')[$candidate];
    $index = array_search($candidate, array_column($fields, 'ref'), true);
    expect(check_display_condition($index, $conditional, $fields, false, $resource))->toBe($expected);
})->with([
    'matching text' => [FIELD_TYPE_CHECK_BOX_LIST, 'Winter', 'displaydependency=Winter', true],
    'non-matching text' => [FIELD_TYPE_CHECK_BOX_LIST, 'Winter', 'displaydependency=Summer', false],
    'punctuation' => [FIELD_TYPE_CHECK_BOX_LIST, "It's, \\ / here", "displaydependency=It's, \\ / here", true],
    'non-matching punctuation' => [FIELD_TYPE_CHECK_BOX_LIST, "It's, \\ / here", "displaydependency=It's elsewhere", false],
    'tree root' => [FIELD_TYPE_CATEGORY_TREE, 'Winter', 'displaydependency=Winter', true],
    'non-matching tree root' => [FIELD_TYPE_CATEGORY_TREE, 'Winter', 'displaydependency=Summer', false],
    'tree leaf' => [FIELD_TYPE_CATEGORY_TREE, 'Winter', 'displaydependency=Winter', true, 'Seasons'],
    'tree punctuation' => [FIELD_TYPE_CATEGORY_TREE, "It's, \\ / here", "displaydependency=It's, \\ / here", true],
    'tree leaf punctuation' => [
        FIELD_TYPE_CATEGORY_TREE, "It's, \\ / here", "displaydependency=It's, \\ / here", true, 'Seasons',
    ],
    'non-matching tree leaf punctuation' => [
        FIELD_TYPE_CATEGORY_TREE, "It's, \\ / here", 'displaydependency=Seasons', false, 'Seasons',
    ],
    'translated value' => [FIELD_TYPE_CHECK_BOX_LIST, '~en:Winter~fr:Hiver', 'displaydependency=Winter', true],
    'full multilingual value' => [
        FIELD_TYPE_CHECK_BOX_LIST, '~en:Winter~fr:Hiver', 'displaydependency=~en:Winter~fr:Hiver', true,
    ],
    'second OR alternative' => [FIELD_TYPE_DROP_DOWN_LIST, 'Winter', 'displaydependency=Summer|Winter', true],
    'neither OR alternative' => [FIELD_TYPE_RADIO_BUTTONS, 'Winter', 'displaydependency=Summer|Spring', false],
]);

it('keeps a hidden dependency available for evaluation without exposing it', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $dependency = $fixtures->field('Hidden dependency', 0, FIELD_TYPE_CHECK_BOX_LIST, 'hiddendependency');
    $qualifier = $fixtures->field('Second dependency', 0, FIELD_TYPE_CHECK_BOX_LIST, 'seconddependency');
    $candidate = $fixtures->field('Conditional field');
    $GLOBALS['ref'] = $GLOBALS['use'] = $resource;
    $columns = array_intersect_key(get_resource_type_field_columns(), ['display_condition' => true]);
    expect(save_resource_type_field($candidate, $columns, [
        'display_condition' => 'hiddendependency=Winter;seconddependency=Photo',
    ]))->toBeTrue();
    $winter = set_node(null, $dependency, 'Winter', null, 10);
    $photo = set_node(null, $qualifier, 'Photo', null, 10);
    expect($winter)->toBeInt()->and($photo)->toBeInt();
    add_resource_nodes($resource, [$winter]);
    $GLOBALS['userpermissions'] = ["f{$candidate}"];

    $fields = get_resource_field_data($resource, false, false);
    $GLOBALS['display_check_data'] = $fields;
    $by_ref = array_column($fields, null, 'ref');
    $index = array_search($candidate, array_column($fields, 'ref'), true);
    expect($by_ref[$dependency]['can_view'])->toBeFalse()
        ->and(array_column(get_resource_field_data($resource), 'ref'))->toBe([$candidate])
        ->and(check_display_condition($index, $by_ref[$candidate], $fields, false, $resource))->toBeFalse();

    add_resource_nodes($resource, [$photo], false);
    $GLOBALS['display_check_data'] = get_resource_field_data($resource, false, false);
    expect(check_display_condition($index, $by_ref[$candidate], $fields, false, $resource))->toBeTrue();

    $GLOBALS['userpermissions'] = ['f*', "f-{$candidate}"];
    $all = array_column(get_resource_field_data($resource, false, false), null, 'ref');
    expect($all[$candidate]['can_view'])->toBeFalse()
        ->and(array_column(get_resource_field_data($resource), 'ref'))->not->toContain($candidate);

    $GLOBALS['userpermissions'] = ['f*', 'T1'];
    expect(array_column(get_resource_field_data($resource, false, false), 'can_view'))->not->toContain(true)
        ->and(get_resource_field_data($resource))->toBeFalse();

    $GLOBALS['userpermissions'] = ["P{$candidate}"];
    $template_fields = get_resource_field_data(-1, false, false, $resource);
    $visible = array_values(array_filter($template_fields, static fn (array $field): bool => $field['can_view']));
    expect(array_column($visible, 'ref'))->toBe([$candidate])
        ->and(get_resource_field_data(-1, false, true, $resource))->toBe($visible);
});

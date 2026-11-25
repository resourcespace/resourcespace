<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['view_title_field', 'sort_fields', 'corefields', 'fixture_plugin_fields']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('protects metadata fields registered by core and plugins', function () use (&$fixtures): void {
    $core = $fixtures->field('Protected core field');
    $plugin = $fixtures->field('Protected plugin field');

    $original = $GLOBALS['view_title_field'];
    $GLOBALS['view_title_field'] = $core;
    expect(delete_resource_type_field($core))->not->toBeTrue();
    $GLOBALS['view_title_field'] = $original;

    $GLOBALS['sort_fields'][] = $core;
    expect(delete_resource_type_field($core))->not->toBeTrue();

    config_register_core_fieldvars('Pest fixture', ['fixture_plugin_fields']);
    $GLOBALS['fixture_plugin_fields'] = [$plugin];
    expect(delete_resource_type_field($plugin))->not->toBeTrue();
    expect(get_resource_type_field($core))->not->toBeFalse()
        ->and(get_resource_type_field($plugin))->not->toBeFalse();
});

it('applies a global metadata field until its resource types are narrowed', function () use (&$fixtures): void {
    $field = $fixtures->field('Type association');
    $photo_fields = static fn (): array => array_column(get_resource_type_fields([0, 1]), 'ref');
    $document_fields = static fn (): array => array_column(get_resource_type_fields([0, 2]), 'ref');

    expect($photo_fields())->toContain($field)->and($document_fields())->toContain($field);
    update_resource_type_field_resource_types($field, [2]);

    expect($photo_fields())->not->toContain($field)->and($document_fields())->toContain($field);
});

it('looks up metadata nodes by their exact values within a field', function (
    string $saved,
    string $searched,
    bool $found,
) use (&$fixtures): void {
    $field = $fixtures->field('Exact node lookup');
    $node = set_node(null, $field, $saved, null, 10);
    expect($node)->toBeInt();
    expect(get_node_id($searched, $field))->toBe($found ? $node : false);
})->with([
    'matching text' => ['A node name', 'A node name', true],
    'matching diacritics' => ['Aldańa', 'Aldańa', true],
    'missing value' => ['A node name', 'Different value', false],
    'different accent' => ['Aldańa', 'Aldána', false],
]);

it('rejects malformed metadata field definitions', function (string $title, int|string $resource_type, int|string $type): void {
    expect(create_resource_type_field($title, $resource_type, $type))->toBeFalse();
})->with([
    'blank title' => [' ', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE],
    'invalid resource type' => ['Invalid resource type', 'invalid', FIELD_TYPE_TEXT_BOX_SINGLE_LINE],
    'invalid field type' => ['Invalid field type', 0, 'invalid'],
]);

it('normalizes short names, resolves duplicates and defaults null indexing to disabled', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(8);
    $name = 'pestfield' . $suffix;
    $first = $fixtures->field('Metadata field', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $name);
    $duplicate = $fixtures->field('Metadata field', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $name);
    $derived = $fixtures->field("Empty shortname {$suffix}", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, '', null);
    $numeric = $fixtures->field('Numeric shortname', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 251);
    $spaced = $fixtures->field('Spaced shortname', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "spaced name {$suffix}");

    expect(get_resource_type_field($first)['name'])->toBe($name)
        ->and(get_resource_type_field($duplicate)['name'])->not->toBe($name)
        ->and(get_resource_type_field($derived)['name'])->toBe(substr('emptyshortname' . $suffix, 0, 20))
        ->and((int) get_resource_type_field($derived)['keywords_index'])->toBe(0)
        ->and(get_resource_type_field($numeric)['name'])->not->toBeEmpty()
        ->and(get_resource_type_field($spaced)['name'])->not->toBeEmpty();
});

it('sets a group’s defaults for fixed-list and text metadata', function () use (&$fixtures): void {
    $id = generateRandomTestID(8);
    $river_name = 'river' . $id;
    $boat_name = 'boat' . $id;
    $river = $fixtures->field('River', 0, FIELD_TYPE_CHECK_BOX_LIST, $river_name);
    $boat = $fixtures->field('Boat name', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $boat_name);
    $nile = set_node(null, $river, 'Nile', null, 10);
    set_node(null, $river, 'Amazon', null, 20);

    $group = $fixtures->group('Metadata defaults ' . $id, 'c,e-2,s');
    expect(save_usergroup($group, ['resource_defaults' => "{$river_name}=Nile;{$boat_name}=Sea Monster"]))->toBeTrue();
    $user = $fixtures->user('defaultmetadata' . $id, $group);
    setup_user(get_user($user));

    $resource = $fixtures->resource(1);
    set_resource_defaults($resource);
    expect(get_resource_nodes($resource, $river))->toBe([$nile])
        ->and(get_data_by_field($resource, $boat, true))->toBe('Sea Monster');
});

it('returns global and applicable type-specific EXIF mappings with the requested option separator', function () use (&$fixtures): void {
    $field = $fixtures->field('Photo EXIF mapping', 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['exiftool_field' => true]);
    expect(save_resource_type_field($field, $columns, ['exiftool_field' => 'XMP:Headline']))->toBeTrue();

    $photo = array_column(get_exiftool_fields(1, NODE_NAME_STRING_SEPARATOR), null, 'ref');
    $document = array_column(get_exiftool_fields(2), null, 'ref');
    expect($photo[$field]['exiftool_field'])->toBe('XMP:Headline')
        ->and($document)->not->toHaveKey($field)
        ->and($photo[8]['exiftool_field'])->toBe('IPTC:ObjectName,XMP:title')
        ->and($document[8]['exiftool_field'])->toBe($photo[8]['exiftool_field'])
        ->and(explode(NODE_NAME_STRING_SEPARATOR, $photo[3]['options']))->toBe(get_field_options(3, false));
});

it('applies signed autocomplete defaults to blank, selected or forcibly updated fields', function () use (&$fixtures): void {
    $existing = $fixtures->resource(1, 0);
    $first = $fixtures->field('Autocomplete field');
    $set_macro = static function (int $field, string $code): void {
        $signed = '//SIG' . sign_code($code) . "\n" . $code;
        ps_query('UPDATE resource_type_field SET autocomplete_macro = ? WHERE ref = ?', ['s', $signed, 'i', $field]);
        clear_query_cache('schema');
    };
    $set_macro($first, 'return "Default metadata";');
    $new = $fixtures->resource(2, 0);
    expect(get_data_by_field($new, $first))->toBe('Default metadata')
        ->and(get_data_by_field($existing, $first))->toBe('');

    $errors = [];
    expect(update_field($new, $first, 'Manual edit', $errors))->toBeTrue()
        ->and(autocomplete_blank_fields($existing, false))->toBeTrue()
        ->and(autocomplete_blank_fields($new, false))->toBeTrue()
        ->and(get_data_by_field($existing, $first))->toBe('Default metadata')
        ->and(get_data_by_field($new, $first))->toBe('Manual edit');

    $second = $fixtures->field('Second autocomplete field');
    $set_macro($first, 'return "Refreshed metadata";');
    $set_macro($second, 'return "Second default";');
    foreach ([$existing, $new] as $resource) {
        expect(autocomplete_blank_fields($resource, true, true, $first))->toBe([$first => 'Refreshed metadata'])
            ->and(get_data_by_field($resource, $first))->toBe('Refreshed metadata')
            ->and(get_data_by_field($resource, $second))->toBe('');
    }

    expect(update_field($new, $second, 'Another manual edit', $errors))->toBeTrue();
    foreach ([$existing, $new] as $resource) {
        $changes = autocomplete_blank_fields($resource, true, true);
        expect($changes[$first])->toBe('Refreshed metadata')
            ->and($changes[$second])->toBe('Second default')
            ->and(get_data_by_field($resource, $first))->toBe('Refreshed metadata')
            ->and(get_data_by_field($resource, $second))->toBe('Second default');
    }
});

it('reindexes replacements and clears only the edited resource from a shared metadata value', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $name = 'lifecycle' . $suffix;
    $field = $fixtures->field('Indexed metadata lifecycle', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $name, true);
    $current = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $old = 'original' . $suffix;
    $new = 'replacement' . $suffix;
    $errors = [];
    $find = static fn (string $value): array => array_column((array) do_search("{$name}:{$value}"), 'ref');

    expect(update_field($current, $field, $old, $errors))->toBeTrue()
        ->and(update_field($control, $field, $old, $errors))->toBeTrue()
        ->and($find($old))->toEqualCanonicalizing([$current, $control]);

    expect(update_field($current, $name, $new, $errors))->toBeTrue()
        ->and(get_data_by_field($current, $field))->toBe($new)
        ->and($find($old))->toBe([$control])
        ->and($find($new))->toBe([$current]);

    expect(update_field($current, $field, '', $errors))->toBeTrue()
        ->and(get_data_by_field($current, $field))->toBe('')
        ->and($find($new))->toBeEmpty()
        ->and($find($old))->toBe([$control]);

    expect(update_field($current, $field, $old, $errors))->toBeTrue()
        ->and(update_field($current, $field, '', $errors))->toBeTrue()
        ->and(get_resource_nodes($current, $field))->toBeEmpty()
        ->and(get_data_by_field($control, $field))->toBe($old)
        ->and($find($old))->toBe([$control]);

    $logs = count(get_resource_log($current)['data']);
    expect(update_field($current, $field, '', $errors))->toBeTrue()
        ->and($errors)->toBeEmpty()
        ->and(get_resource_log($current)['data'])->toHaveCount($logs);
});

it('loads text and multiple ordered options for resources of different types in one batch', function () use (&$fixtures): void {
    $list = $fixtures->field('Batch genre', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $text = $fixtures->field('Batch text');
    $comedy = set_node(null, $list, 'Comedy', null, 10);
    $action = set_node(null, $list, 'Action', null, 20);
    $horror = set_node(null, $list, 'Horror', null, 30);

    $expected = [];
    foreach ([
        [1, [$comedy, $action], 'Comedy, Action', 'Lemon'],
        [1, [$comedy, $horror], 'Comedy, Horror', 'Orange'],
        [2, [$comedy, $action, $horror], 'Comedy, Action, Horror', 'Mango'],
        [2, [$action], 'Action', 'Apple'],
        [2, [$horror], 'Horror', 'Banana'],
    ] as [$type, $options, $label, $fruit]) {
        $resource = $fixtures->resource($type, 0);
        $errors = [];
        add_resource_nodes($resource, $options);
        expect(update_field($resource, $text, $fruit, $errors))->toBeTrue();
        $expected[$resource] = [$list => $label, $text => $fruit];
    }

    $values = get_resource_field_data_batch(array_keys($expected));
    expect($values)->toHaveCount(count($expected));
    foreach ($expected as $ref => $fields) {
        $stored = array_column($values[$ref], 'value', 'resource_type_field');
        expect(array_intersect_key($stored, $fields))->toEqual($fields);
    }
    expect(get_resource_field_data_batch(['invalid resource']))->toBeEmpty();
});

it('returns flattened field values, raw nodes or a cross-resource text stream on request', function () use (&$fixtures): void {
    $text = $fixtures->field('Metadata read text', 1);
    $dropdown = $fixtures->field('Metadata read list', 1, FIELD_TYPE_DROP_DOWN_LIST);
    $tree = $fixtures->field('Metadata read taxonomy', 1, FIELD_TYPE_CATEGORY_TREE);
    $root = set_node(null, $tree, 'Shapes', null, 10);
    $branch = set_node(null, $tree, 'Polygons', $root, 10);
    $leaf = set_node(null, $tree, 'Triangle', $branch, 10);
    $other_branch = set_node(null, $tree, 'Colour', null, 20);
    $option = set_node(null, $dropdown, 'Selected option', null, 10);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($first, $text, 'Metadata first', $errors))->toBeTrue()
        ->and(update_field($second, $text, 'Metadata second', $errors))->toBeTrue();
    add_resource_nodes($first, [$option, $root, $branch, $leaf, $other_branch]);
    add_resource_nodes($second, [$other_branch]);

    expect(get_data_by_field($first, $text))->toBe('Metadata first')
        ->and(array_column(get_data_by_field($first, $text, false), 'name'))->toBe(['Metadata first'])
        ->and(get_data_by_field($first, $dropdown))->toBe('Selected option')
        ->and(array_column(get_data_by_field($first, $dropdown, false), 'ref'))->toBe([$option])
        ->and(explode(', ', get_data_by_field($first, $tree)))
        ->toEqualCanonicalizing(['Shapes/Polygons/Triangle', 'Colour'])
        ->and(get_data_by_field($second, $tree))->toBe('Colour')
        ->and(array_column(get_data_by_field($second, $tree, false), 'ref'))->toBe([$other_branch]);

    $all_text = get_data_by_field(null, $text);
    expect($all_text)->toBeInstanceOf(Generator::class);
    expect(iterator_to_array($all_text))->toBe(iterator_to_array(get_resources_nodes_by_rtf($text)))
        ->and(get_data_by_field(null, $dropdown))->toBe('');
});

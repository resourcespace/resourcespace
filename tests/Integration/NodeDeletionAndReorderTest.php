<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('maintains taxonomy order, parent links and index records during deletion', function () use (&$fixtures): void {
    $field = $fixtures->field('Lifecycle taxonomy', 0, FIELD_TYPE_CATEGORY_TREE, 'lifecycletaxonomy');
    ps_query('UPDATE resource_type_field SET keywords_index = 1 WHERE ref = ?', ['i', $field]);
    clear_query_cache('schema');
    $parent = set_node(null, $field, 'Parent ' . generateRandomTestID(6), '', 10);
    $leaf = set_node(null, $field, 'Leaf ' . generateRandomTestID(6), $parent, 20);
    $sibling = set_node(null, $field, 'Sibling ' . generateRandomTestID(6), $parent, 30);
    $resource = $fixtures->resource(1, 0);
    add_resource_nodes($resource, [$leaf, $sibling]);
    expect(ps_value('SELECT COUNT(*) value FROM node_keyword WHERE node = ?', ['i', $leaf], 0) > 0)->toBeTrue('Fixture node not indexed');
    reorder_node([$sibling, $leaf]);
    expect(ps_value('SELECT order_by value FROM node WHERE ref = ?', ['i', $sibling], 0) == 10
        && ps_value('SELECT order_by value FROM node WHERE ref = ?', ['i', $leaf], 0) == 20)->toBeTrue('Node ordering not persisted');
    delete_node($parent);
    expect(ps_value('SELECT COUNT(*) value FROM node WHERE ref = ?', ['i', $parent], 0) == 1)->toBeTrue('Populated parent was deleted');
    delete_node($leaf);
    foreach (['node' => 'ref', 'node_keyword' => 'node', 'resource_node' => 'node'] as $table => $column) {
        expect(ps_value("SELECT COUNT(*) value FROM $table WHERE $column = ?", ['i', $leaf], -1) == 0)->toBeTrue('Node deletion left ' . $table . ' records');
    }
    expect(ps_value('SELECT COUNT(*) value FROM resource_node WHERE resource = ? AND node = ?', ['i', $resource, 'i', $sibling], 0) == 1)->toBeTrue('Node deletion removed sibling mapping');
    expect(ps_value('SELECT parent value FROM node WHERE ref = ?', ['i', $sibling], 0) == $parent)->toBeTrue('Ordering/deletion changed sibling hierarchy');
});

it('toggles only fixed-list nodes and leaves free text active', function () use (&$fixtures): void {
    $checkbox = $fixtures->field('Active checkbox', 1, FIELD_TYPE_CHECK_BOX_LIST);
    $text = $fixtures->field('Active text', 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $tree = $fixtures->field('Active tree', 1, FIELD_TYPE_CATEGORY_TREE);
    $resource = $fixtures->resource(1, 0);
    $option = set_node(null, $checkbox, 'Checkbox option', null, 10);
    $root = set_node(null, $tree, 'Root option', null, 10);
    $errors = [];
    expect(update_field($resource, $text, 'Free text value', $errors))->toBeTrue();
    $free_text = get_resource_nodes($resource, $text)[0];

    expect(toggle_active_state_for_nodes([$option, $free_text, $root]))
        ->toEqual([$option => 0, $free_text => 1, $root => 0]);
    expect(toggle_active_state_for_nodes([$option]))->toEqual([$option => 1]);
});

it('propagates tree deactivation down and requires active ancestors when enabling a node', function () use (&$fixtures): void {
    $tree = $fixtures->field('Tree activation', 1, FIELD_TYPE_CATEGORY_TREE);
    $root = set_node(null, $tree, 'Root', null, 10);
    $child = set_node(null, $tree, 'Child', $root, 10);
    $leaf = set_node(null, $tree, 'Leaf', $child, 10);
    $sibling = set_node(null, $tree, 'Sibling branch', null, 20);

    expect(toggle_active_state_for_nodes([$root]))->toEqual([$root => 0, $child => 0, $leaf => 0])
        ->and(toggle_active_state_for_nodes([$leaf]))->toEqual([$leaf => 0])
        ->and(toggle_active_state_for_nodes([$root]))->toEqual([$root => 1])
        ->and(toggle_active_state_for_nodes([$leaf]))->toEqual([$leaf => 0])
        ->and(toggle_active_state_for_nodes([$child]))->toEqual([$child => 1])
        ->and(toggle_active_state_for_nodes([$leaf]))->toEqual([$leaf => 1])
        ->and(toggle_active_state_for_nodes([$child]))->toEqual([$child => 0, $leaf => 0])
        ->and(toggle_active_state_for_nodes([$sibling]))->toEqual([$sibling => 0]);
});

it('creates ordered options and reuses duplicate names only within the same branch', function () use (&$fixtures): void {
    $field = $fixtures->field('Ordered dynamic keywords', 1, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $first = set_node(null, $field, 'Default position', null, null);
    $second = set_node(null, $field, 'Explicit position', null, 40);
    $same = set_node(null, $field, 'Default position', null, null);
    expect($same)->toBe($first);
    $nodes = array_column(get_nodes($field), null, 'ref');
    expect($nodes)->toHaveCount(2)
        ->and($nodes[$first]['name'])->toBe('Default position')
        ->and($nodes[$first]['resource_type_field'])->toBe($field)
        ->and($nodes[$first]['parent'])->toBeNull()
        ->and($nodes[$first]['order_by'])->toBe(10)
        ->and($nodes[$second]['order_by'])->toBe(40);
    foreach ([[$field, ''], [$field, null], ['', 'Missing field'], [null, 'Invalid field']] as [$target, $name]) {
        expect(set_node(null, $target, $name, null, null))->toBeFalse();
    }

    $tree = $fixtures->field('Duplicate names across taxonomy branches', 1, FIELD_TYPE_CATEGORY_TREE);
    $root_a = set_node(null, $tree, 'Parent A', null, null);
    $root_b = set_node(null, $tree, 'Parent B', null, null);
    $root_empty = set_node(null, $tree, 'Empty branch', null, null);
    $child_a = set_node(null, $tree, 'Same child name', $root_a, null);
    $child_b = set_node(null, $tree, 'Same child name', $root_b, null);
    expect($child_a)->not->toBe($child_b);
    set_node(null, $tree, 'Same child name', $root_b, null);
    $unique_child = set_node(null, $tree, 'Unique child name', $root_b, null);
    expect(set_node(null, $tree, 'Unique child name', $root_b, null))->toBe($unique_child);
    expect(array_column(get_nodes($tree, null, true), 'ref'))
        ->toEqualCanonicalizing([$root_a, $root_b, $root_empty, $child_a, $child_b, $unique_child]);
});

it('logs category path changes and restores the historical selection in a batch edit', function () use (&$fixtures): void {
    $tree = $fixtures->field('Versioned taxonomy', 1, FIELD_TYPE_CATEGORY_TREE);
    $fruit = set_node(null, $tree, '~en:Fruit~fr:Fruits', null, 10);
    $vegetables = set_node(null, $tree, '~en:Vegetable~fr:Legume', null, 20);
    $apple = set_node(null, $tree, '~en:Apple~fr:Pomme', $fruit, 10);
    $orange = set_node(null, $tree, '~en:Orange~fr:Orange', $fruit, 20);
    set_node(null, $tree, '~en:Courgette~fr:Courgette', $vegetables, 10);
    $resource = $fixtures->resource(1, 0);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Taxonomy history ' . generateRandomTestID(10));
    expect(add_resource_to_collection($resource, $collection))->toBeTrue();
    add_resource_nodes($resource, [$fruit, $apple], false, true);
    $errors = [];
    expect(update_field($resource, $tree, 'Vegetable/Courgette', $errors))->toBeTrue();
    $_POST = ['ref' => $resource, 'nodes' => [$tree => [$orange, $fruit]], 'submit' => 'true'];
    expect(save_resource_data($resource, false, $tree))->toBeTrue();

    $log = get_resource_log($resource, 1, ['r.type' => LOG_CODE_EDITED])['data'][0];
    expect(explode("\n", $log['diff']))->toEqualCanonicalizing([
        '- ~en:Vegetable~fr:Legume',
        '- ~en:Vegetable~fr:Legume/~en:Courgette~fr:Courgette',
        '+ ~en:Fruit~fr:Fruits',
        '+ ~en:Fruit~fr:Fruits/~en:Orange~fr:Orange',
    ]);
    $cutoff = ps_value('SELECT NOW() AS value', [], '');
    expect(update_field($resource, $tree, '~en:Apple~fr:Pomme', $errors))->toBeTrue();
    ps_query(
        'UPDATE resource_log SET date = DATE_ADD(?, INTERVAL 2 SECOND)
          WHERE resource = ? AND resource_type_field = ? AND ref > ?',
        ['s', $cutoff, 'i', $resource, 'i', $tree, 'i', $log['ref']]
    );
    $settings = [
        'editthis_field_' . $tree => 'true',
        'revert_' . $tree => $cutoff,
        'modeselect_' . $tree => 'Revert',
    ];
    expect(save_resource_data_multi($collection, [], $settings))->toBeTrue()
        ->and(get_data_by_field($resource, $tree))->toBe('Fruit/Orange')
        ->and(get_resource_nodes($resource, $tree))->toEqualCanonicalizing([$fruit, $orange]);
});

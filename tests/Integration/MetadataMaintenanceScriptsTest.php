<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('restores missing search mappings for an indexed field through the reindex command', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $field = $fixtures->field('Reindex fixture', 0, FIELD_TYPE_CHECK_BOX_LIST, 'reindex' . $id, true);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $term = 'maintenance' . $id;
    $node = set_node(null, $field, $term, null, 10);
    add_resource_nodes($first, [$node]);
    add_resource_nodes($second, [$node]);
    expect(array_column(do_search($term), 'ref'))->toEqualCanonicalizing([$first, $second]);

    remove_all_node_keyword_mappings($node);
    expect(array_column((array) do_search($term), 'ref'))->toBeEmpty();
    $output = LocalTestScript::run('pages/tools/reindex.php', [], [(string) $field, 'nodebug']);
    expect($output)->toContain('Indexing nodes for field# ' . $field, '100% completed');
    expect(array_column(do_search($term), 'ref'))->toEqualCanonicalizing([$first, $second]);
});

it('converts existing fixed-list metadata to one text value and preserves category paths', function (
    int $type,
    bool $tree,
    string $expected,
) use (&$fixtures): void {
    $field = $fixtures->field('Migrated metadata', 0, $type);
    $resource = $fixtures->resource(1, 0);
    $single = $fixtures->resource(1, 0);
    $first_name = $tree ? 'Eastern' : 'Forest';
    $second_name = $tree ? 'Western' : 'River';
    $parent = $tree ? set_node(null, $field, 'District', null, 10) : null;
    $first = set_node(null, $field, $first_name, $parent, 20);
    $second = set_node(null, $field, $second_name, $parent, 30);
    add_resource_nodes($resource, $tree ? [$parent, $first, $second] : [$first, $second]);
    add_resource_nodes($single, [$first]);

    $columns = array_intersect_key(get_resource_type_field_columns(), ['type' => true]);
    expect(save_resource_type_field($field, $columns, ['type' => FIELD_TYPE_TEXT_BOX_SINGLE_LINE]))->toBeTrue();
    try {
        // RS_TEST_MODE enables edit checks for CLI node writes.
        setTestConfiguration('$userpermissions = ["e0", "f*"]; $userref = 1;');
        $output = LocalTestScript::run('pages/tools/migrate_fixed_to_text.php', [], [
            '--field=' . $field, '--tree=' . ($tree ? 'yes' : 'no'), '--separator= | ',
        ]);
    } finally {
        setTestConfiguration('');
    }
    expect($output)->toContain('Processing 1 resources.', 'Resource ' . $resource . ' updated.', 'Completed');
    expect(array_column(get_resource_nodes($resource, $field, true), 'name'))->toBe([$expected])
        ->and(get_resource_nodes($single, $field))->toBe([$first]);
})->with([
    'dynamic keywords' => [FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, false, 'Forest | River'],
    'checkbox options' => [FIELD_TYPE_CHECK_BOX_LIST, false, 'Forest | River'],
    'category tree' => [FIELD_TYPE_CATEGORY_TREE, true, 'District/Eastern | District/Western'],
]);

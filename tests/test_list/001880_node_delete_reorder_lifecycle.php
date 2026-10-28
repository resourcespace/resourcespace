<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    try {
        $field = create_resource_type_field('Lifecycle taxonomy', 0, FIELD_TYPE_CATEGORY_TREE, 'lifecycletaxonomy');
        ps_query('UPDATE resource_type_field SET keywords_index = 1 WHERE ref = ?', ['i', $field]);
        clear_query_cache('schema');
        $parent = set_node(null, $field, 'Parent ' . test_generate_random_ID(6), '', 10);
        $leaf = set_node(null, $field, 'Leaf ' . test_generate_random_ID(6), $parent, 20);
        $sibling = set_node(null, $field, 'Sibling ' . test_generate_random_ID(6), $parent, 30);
        $resource = create_resource(1, 0);
        add_resource_nodes($resource, [$leaf, $sibling]);
        $check(ps_value('SELECT COUNT(*) value FROM node_keyword WHERE node = ?', ['i', $leaf], 0) > 0, 'Fixture node not indexed');
        reorder_node([$sibling, $leaf]);
        $check(ps_value('SELECT order_by value FROM node WHERE ref = ?', ['i', $sibling], 0) == 10
            && ps_value('SELECT order_by value FROM node WHERE ref = ?', ['i', $leaf], 0) == 20, 'Node ordering not persisted');
        delete_node($parent);
        $check(ps_value('SELECT COUNT(*) value FROM node WHERE ref = ?', ['i', $parent], 0) == 1, 'Populated parent was deleted');
        delete_node($leaf);
        foreach (['node' => 'ref', 'node_keyword' => 'node', 'resource_node' => 'node'] as $table => $column) {
            $check(ps_value("SELECT COUNT(*) value FROM $table WHERE $column = ?", ['i', $leaf], -1) == 0, 'Node deletion left ' . $table . ' records');
        }
        $check(ps_value('SELECT COUNT(*) value FROM resource_node WHERE resource = ? AND node = ?', ['i', $resource, 'i', $sibling], 0) == 1, 'Node deletion removed sibling mapping');
        $check(ps_value('SELECT parent value FROM node WHERE ref = ?', ['i', $sibling], 0) == $parent, 'Ordering/deletion changed sibling hierarchy');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    }
})();

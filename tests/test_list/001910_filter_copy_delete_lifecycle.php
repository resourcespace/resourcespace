<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $filters = [];
    try {
        $field = create_resource_type_field('Lifecycle filter', 0, FIELD_TYPE_CHECK_BOX_LIST, 'lifecyclefilter');
        $node = set_node(null, $field, 'Unique option', '', 10);
        $original = save_filter(0, 'Lifecycle filter', RS_FILTER_ALL);
        $filters[] = $original;
        save_filter_rule(0, $original, [[RS_FILTER_NODE_IN, [$node]]]);
        $copy = copy_filter($original);
        $filters[] = $copy;
        $original_rules = get_filter_rules($original);
        $copy_rules = get_filter_rules($copy);
        $check($copy != $original && count($original_rules) === 1 && count($copy_rules) === 1, 'Filter copy missing rules');
        $check(filter_check($original, [$node]) && filter_check($copy, [$node]), 'Copied filter does not match original');
        $check(!filter_check($copy, []), 'Copied filter accepts missing required node');
        $rule = ps_value('SELECT ref value FROM filter_rule WHERE filter = ?', ['i', $original], 0);
        $check(delete_filter_rule($rule), 'Rule deletion failed');
        $check(ps_value('SELECT COUNT(*) value FROM filter_rule_node WHERE filter_rule = ?', ['i', $rule], -1) == 0, 'Rule deletion left node mappings');
        $check(filter_check($copy, [$node]) && count(get_filter_rules($copy)) === 1, 'Deleting original rule changed copy');
        $copy_rule = ps_value('SELECT ref value FROM filter_rule WHERE filter = ?', ['i', $copy], 0);
        $check(delete_filter($copy), 'Filter deletion failed');
        $check(ps_value('SELECT COUNT(*) value FROM filter_rule WHERE filter = ?', ['i', $copy], -1) == 0
            && ps_value('SELECT COUNT(*) value FROM filter_rule_node WHERE filter_rule = ?', ['i', $copy_rule], -1) == 0, 'Filter deletion left dependent records');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($filters as $filter) {
            delete_filter($filter);
        }
    }
})();

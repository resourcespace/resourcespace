<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['userpermissions'];
    try {
        $resource = create_resource(1, 0);
        $field = create_resource_type_field('Photo only', 1, FIELD_TYPE_CHECK_BOX_LIST, 'transitionphoto');
        $node = set_node(null, $field, 'Photo option', '', 10);
        add_resource_nodes($resource, [$node]);
        $GLOBALS['userpermissions'] = ['e0', 'XU2'];
        $before = get_resource_data($resource, false);
        $nodes = get_resource_nodes($resource);
        $logs = ps_value('SELECT COUNT(*) value FROM resource_log WHERE resource = ?', ['i', $resource], 0);
        $check(update_resource_type($resource, 2) === false, 'Forbidden resource type transition accepted');
        $check(get_resource_data($resource, false) === $before && get_resource_nodes($resource) === $nodes
            && ps_value('SELECT COUNT(*) value FROM resource_log WHERE resource = ?', ['i', $resource], 0) == $logs, 'Rejected type transition changed metadata/logs');
        $GLOBALS['userpermissions'] = $saved;
        $check(update_resource_type($resource, 2), 'Valid type transition rejected');
        $check(get_resource_data($resource, false)['resource_type'] == 2, 'Resource type not persisted');
        $check(ps_value('SELECT COUNT(*) value FROM resource_node WHERE resource = ? AND node = ?', ['i', $resource, 'i', $node], -1) == 0, 'Type-specific metadata survived transition');
        $check(ps_value('SELECT COUNT(*) value FROM resource_log WHERE resource = ?', ['i', $resource], 0) > $logs, 'Type transition not logged');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
    }
})();

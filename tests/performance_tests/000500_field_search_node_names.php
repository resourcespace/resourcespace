<?php

command_line_only();

// Run independently: php tests/test.php -performance -time 500
return (static function (): bool {
    $saved = [];
    foreach (['query_cache_enabled', 'config_show_performance_footer', 'userpermissions'] as $key) {
        $saved[$key] = $GLOBALS[$key];
    }
    $field = create_resource_type_field('Search benchmark', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'benchnames');
    try {
        $GLOBALS['userpermissions'] = ['f*'];
        for ($batch = 0; $batch < 20; $batch++) {
            $values = [];
            $parameters = [];
            for ($i = 0; $i < 1000; $i++) {
                $index = $batch * 1000 + $i;
                $values[] = '(?, ?, NULL, ?)';
                array_push($parameters, 'i', $field, 's', 'Value' . $index, 'i', $index);
            }
            ps_query(
                'INSERT INTO node (resource_type_field, name, parent, order_by) VALUES ' . implode(', ', $values),
                $parameters
            );
        }

        // Previous page behaviour for these recognised, unquoted field terms.
        $before = static function (string $search, array $keywords): string {
            foreach ($keywords as $keyword) {
                [$shortname, $value] = explode(':', $keyword);
                $ref = ps_value(
                    'SELECT ref AS value FROM resource_type_field WHERE name = ?',
                    ['s', $shortname],
                    0,
                    'schema'
                );
                if (!$ref || !metadata_field_view_access($ref)) {
                    continue;
                }
                $info = get_resource_type_field($ref);
                $nodes = get_nodes($ref);
                $options = explode(';', $value);
                $tokens = '';
                if (count($options) > 1) {
                    foreach ($options as $option) {
                        $node = get_node_by_name($nodes, $option);
                        if ($node === [] && count($nodes) === 10000) {
                            $node = get_nodes($ref, null, false, null, null, $option)[0] ?? [];
                        }
                        if ($node !== []) {
                            $tokens .= NODE_TOKEN_PREFIX . $node['ref'];
                        }
                    }
                    if ($tokens !== '') {
                        $search = str_ireplace($keyword, $tokens, $search);
                    }
                }
            }
            return $search;
        };
        $GLOBALS['query_cache_enabled'] = false;
        $GLOBALS['config_show_performance_footer'] = true;
        echo "\n20,000 nodes; cache disabled; peak PHP allocation excludes fixtures.\n";
        foreach ([FIELD_TYPE_TEXT_BOX_SINGLE_LINE => 'text', FIELD_TYPE_CHECK_BOX_LIST => 'fixed'] as $type => $label) {
            ps_query('UPDATE resource_type_field SET type = ? WHERE ref = ?', ['i', $type, 'i', $field]);
            clear_query_cache('schema');
            $cases = [
                'single x20' => implode(' ', array_fill(0, 20, 'benchnames:Value9999')),
                'OR x20' => implode(' ', array_fill(0, 20, 'benchnames:Value10;Value9999')),
                'fallback x20' => implode(' ', array_fill(0, 20, 'benchnames:Value19999;absent')),
            ];
            foreach ($cases as $case => $search) {
                $keywords = split_keywords($search, false, false, false, false, true);
                $results = [];
                foreach (['before' => $before, 'after' => 'resolve_field_search_node_names'] as $version => $resolve) {
                    $GLOBALS['querycount'] = 0;
                    $GLOBALS['querytime'] = 0;
                    $GLOBALS['querylog'] = [];
                    if (function_exists('memory_reset_peak_usage')) {
                        memory_reset_peak_usage();
                    }
                    $memory = memory_get_usage();
                    $start = microtime(true);
                    $results[$version] = $resolve($search, $keywords);
                    $elapsed = microtime(true) - $start;
                    $peak = memory_get_peak_usage() - $memory;
                    echo sprintf(
                        "%s %-12s %-6s: %7.2f ms elapsed, %7.2f ms queries, %3d queries, %7.1f KiB peak\n",
                        $label,
                        $case,
                        $version,
                        $elapsed * 1000,
                        $GLOBALS['querytime'] * 1000,
                        $GLOBALS['querycount'],
                        $peak / 1024
                    );
                }
                if ($results['before'] !== $results['after']) {
                    throw new RuntimeException('Benchmark results differ: ' . $label . ' ' . $case);
                }
            }
        }
        return true;
    } finally {
        foreach ($saved as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        delete_resource_type_field($field);
    }
})();

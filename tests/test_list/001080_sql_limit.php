<?php

command_line_only();


$use_cases = array(
    array(
        'offset'          => null,
        'rows'            => null,
        'expected_output' => ''
    ),
    array(
        'offset'          => null,
        'rows'            => 15,
        'expected_output' => 'LIMIT 15'
    ),
    // You cannot offset without limiting number of rows
    array(
        'offset'          => 15,
        'rows'            => null,
        'expected_output' => ''
    ),
    array(
        'offset'          => 15,
        'rows'            => 15,
        'expected_output' => 'LIMIT 15,15'
    ),
);

$use_case_number = 1;
foreach ($use_cases as $use_case) {
    $limit = sql_limit($use_case['offset'], $use_case['rows']);

    if ($limit !== $use_case['expected_output']) {
        echo "Use case {$use_case_number} - ";
        return false;
    }

    $use_case_number++;
}

// Check exact totals and whether a separate count is needed, including cached counts.
$saved_count_globals = array_intersect_key($GLOBALS, array_flip([
    'config_show_performance_footer', 'query_cache_enabled', 'cache_search_count',
    'query_cache_already_completed_this_time', 'querycount', 'querytime', 'querylog',
]));
try {
    $config_show_performance_footer = true;
    $query_cache_enabled = false;
    ps_query('CREATE TEMPORARY TABLE rs_count_page_fixture (ref INT)');
    ps_query('INSERT INTO rs_count_page_fixture VALUES (1), (2), (3)');
    $query = new PreparedStatementQuery('SELECT ref FROM rs_count_page_fixture ORDER BY ref');
    foreach ([[2, 0, 3, 2], [5, 0, 3, 1], [2, 2, 3, 1], [2, 5, 3, 2], [0, 0, 3, 2], [-1, 0, 3, 1]] as $case) {
        [$rows, $offset, $expected_total, $expected_queries] = $case;
        $before = $querycount;
        $page = sql_limit_with_total_count($query, $rows, $offset);
        if ($page['total'] !== $expected_total || $querycount - $before !== $expected_queries) {
            echo 'Incorrect total/count-query decision for ' . json_encode($case);
            return false;
        }
    }

    $query_cache_enabled = $cache_search_count = true;
    $query_cache_already_completed_this_time = [];
    sql_limit_with_total_count($query, 1, 0, true);
    $before = $querycount;
    $page = sql_limit_with_total_count($query, 1, 0, true);
    if ($page['total'] !== 3 || $querycount - $before !== 1) {
        echo 'Warm count cache was not reused';
        return false;
    }
    ps_query('INSERT INTO rs_count_page_fixture VALUES (4)');
    $before = $querycount;
    $page = sql_limit_with_total_count($query, 3, 3, true);
    if ($page['total'] !== 4 || $querycount - $before !== 1) {
        echo 'Final page did not override stale cached total without a count';
        return false;
    }
    ps_query('DELETE FROM rs_count_page_fixture');
    $before = $querycount;
    $page = sql_limit_with_total_count($query, 5, 0, true);
    if ($page['total'] !== 0 || $querycount - $before !== 1) {
        echo 'Empty first page performed a count or retained a stale cached total';
        return false;
    }
} finally {
    ps_query('DROP TEMPORARY TABLE IF EXISTS rs_count_page_fixture');
    foreach ($saved_count_globals as $name => $value) {
        $GLOBALS[$name] = $value;
    }
}

return true;

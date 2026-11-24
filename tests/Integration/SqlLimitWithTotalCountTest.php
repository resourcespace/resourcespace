<?php

declare(strict_types=1);

$sql_count_settings = [
    'config_show_performance_footer',
    'query_cache_enabled',
    'cache_search_count',
    'query_cache_already_completed_this_time',
    'querycount',
    'querytime',
    'querylog',
];
$sql_count_fixture = new class () {
    public array $saved_config = [];
    public ?string $table = null;
    public PreparedStatementQuery $query;
};

beforeEach(function () use ($sql_count_fixture, $sql_count_settings): void {
    $sql_count_fixture->saved_config = array_intersect_key($GLOBALS, array_fill_keys($sql_count_settings, true));
    $GLOBALS['config_show_performance_footer'] = true;
    $GLOBALS['query_cache_enabled'] = false;
    $GLOBALS['querycount'] = 0;
    $GLOBALS['querytime'] = 0;
    $GLOBALS['querylog'] = [];

    // A regular table is visible to both sessions when a read-only connection is configured.
    $sql_count_fixture->table = 'rs_count_page_fixture_' . generateRandomTestID(12);
    ps_query("CREATE TABLE {$sql_count_fixture->table} (ref INT)");
    ps_query("INSERT INTO {$sql_count_fixture->table} VALUES (1), (2), (3)");
    $sql_count_fixture->query = new PreparedStatementQuery(
        "SELECT ref FROM {$sql_count_fixture->table} ORDER BY ref"
    );
});

afterEach(function () use ($sql_count_fixture, $sql_count_settings): void {
    if (isset($sql_count_fixture->table)) {
        ps_query("DROP TABLE IF EXISTS {$sql_count_fixture->table}");
        $sql_count_fixture->table = null;
    }
    foreach ($sql_count_settings as $name) {
        unset($GLOBALS[$name]);
    }
    foreach ($sql_count_fixture->saved_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('runs a count only when the page cannot establish the total', function (
    int $rows,
    int $offset,
    int $total,
    int $queries
) use ($sql_count_fixture): void {
    $before = $GLOBALS['querycount'];
    $page = sql_limit_with_total_count($sql_count_fixture->query, $rows, $offset);

    expect($page['total'])->toBe($total)
        ->and($GLOBALS['querycount'] - $before)->toBe($queries);
})->with([
    'full first page' => [2, 0, 3, 2],
    'short first page' => [5, 0, 3, 1],
    'short final page' => [2, 2, 3, 1],
    'page after the final result' => [2, 5, 3, 2],
    'zero rows requested' => [0, 0, 3, 2],
    'unbounded result' => [-1, 0, 3, 1],
]);

describe('cached totals', function () use ($sql_count_fixture): void {
    beforeEach(function () use ($sql_count_fixture): void {
        $GLOBALS['query_cache_enabled'] = $GLOBALS['cache_search_count'] = true;
        $GLOBALS['query_cache_already_completed_this_time'] = [];
        sql_limit_with_total_count($sql_count_fixture->query, 1, 0, true);
    });

    it('reuses the cached count for a full page', function () use ($sql_count_fixture): void {
        $before = $GLOBALS['querycount'];
        $page = sql_limit_with_total_count($sql_count_fixture->query, 1, 0, true);

        expect($page['total'])->toBe(3)
            ->and($GLOBALS['querycount'] - $before)->toBe(1);
    });

    it('uses a final page to override a stale cached count', function () use ($sql_count_fixture): void {
        ps_query("INSERT INTO {$sql_count_fixture->table} VALUES (4)");
        $before = $GLOBALS['querycount'];
        $page = sql_limit_with_total_count($sql_count_fixture->query, 3, 3, true);

        expect($page['total'])->toBe(4)
            ->and($GLOBALS['querycount'] - $before)->toBe(1);
    });

    it('uses an empty first page to override a stale cached count', function () use ($sql_count_fixture): void {
        ps_query("DELETE FROM {$sql_count_fixture->table}");
        $before = $GLOBALS['querycount'];
        $page = sql_limit_with_total_count($sql_count_fixture->query, 5, 0, true);

        expect($page['total'])->toBe(0)
            ->and($GLOBALS['querycount'] - $before)->toBe(1);
    });
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('copies an editable report and downloads CSV scoped to the current search results', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $admin = $fixtures->user('reporteditor' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $admin]);
    $report_name = 'Catalogue report ' . $suffix;
    $original_sql = "SELECT 'Earlier version' AS fixture_name";
    ps_query('INSERT INTO report (name, query) VALUES (?, ?)', ['s', $report_name, 's', $original_sql]);
    $ref = sql_insert_id();
    $fixtures->onCleanup(static fn () => ps_query('DELETE FROM report WHERE name LIKE ?', ['s', '%' . $suffix]));

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $list = $server->request('/pages/admin/admin_report_management.php?find=' . $suffix, headers: $headers);
    expect($list['status'])->toBe(200)->and($list['body'])->toContain($report_name);
    $copy = LocalTestServer::hiddenFields($list['body'], '//form[@id="copy_report"]');
    expect($copy)->toHaveKeys([$GLOBALS['CSRF_token_identifier'], 'copyreport', 'ref']);
    $edit_path = '/pages/admin/admin_report_management_edit.php';
    $copy['ref'] = $ref;
    expect($server->request($edit_path, $copy, $headers)['status'])->toBe(200);
    $copies = ps_query('SELECT name, query FROM report WHERE name LIKE ? AND ref <> ?', ['s', '%' . $suffix, 'i', $ref]);
    expect($copies)->toHaveCount(1);
    expect($copies[0]['name'])->toBe($GLOBALS['lang']['copy_of'] . ' ' . $report_name)
        ->and($copies[0]['query'])->toBe($original_sql);

    $edit_path .= '?ref=' . $ref;
    $edit = $server->request($edit_path, headers: $headers);
    expect($edit['status'])->toBe(200);
    $fields = LocalTestServer::hiddenFields($edit['body'], '//form[@id="mainform"]');
    expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $new_sql = "SELECT ref AS fixture_ref, 'Selected, with \"quotes\"' AS fixture_name FROM resource"
        . ' WHERE ref IN ' . REPORT_PLACEHOLDER_NON_CORRELATED_SQL . ' ORDER BY ref';
    $saved = $server->request($edit_path, $fields + ['name' => $report_name, 'query' => $new_sql], $headers);
    expect($saved['status'])->toBe(302);
    expect(ps_query('SELECT query, support_non_correlated_sql FROM report WHERE ref = ?', ['i', $ref]))
        ->toBe([['query' => $new_sql, 'support_non_correlated_sql' => 1]]);

    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $fixtures->resource(1, 0);
    $back = '/pages/search.php?' . http_build_query([
        'search' => '!list' . $first . ':' . $second, 'restypes' => '1', 'order_by' => 'resourceid',
        'archive' => '0', 'sort' => 'ASC', 'recentdaylimit' => '',
    ]);
    $download = $server->request('/pages/team/team_report.php?' . http_build_query([
        'report' => $ref, 'download' => 'yes', 'backurl' => $back,
    ]), headers: $headers);
    expect($download['status'])->toBe(200)
        ->and($download['headers']['content-disposition'] ?? '')->toContain('attachment;', '.csv');
    $csv = array_map(static fn (string $row): array => str_getcsv($row, escape: ''), explode("\n", trim($download['body'])));
    expect($csv)->toBe([
        ['fixture_ref', 'fixture_name'],
        [(string) $first, 'Selected, with "quotes"'],
        [(string) $second, 'Selected, with "quotes"'],
    ]);
});

it('filters date ranges and maintains a unique email schedule', function () use (&$fixtures): void {
    include_once dirname(__DIR__, 2) . '/include/reporting_functions.php';
    ps_query('INSERT INTO report (name,query) VALUES (?,?)', ['s', 'Date filter fixture', 's',
        "SELECT name FROM sysvars WHERE name = 'periodic_fixture' AND value >= '[from-y]-[from-m]-[from-d]' AND value <= '[to-y]-[to-m]-[to-d]'"]);
    $report = sql_insert_id();
    $fixtures->onCleanup(static function () use ($report): void {
        foreach (ps_array('SELECT ref AS value FROM report_periodic_emails WHERE report = ?', ['i', $report]) as $id) {
            delete_periodic_report($id);
        }
        ps_query('DELETE FROM report WHERE ref = ?', ['i', $report]);
        ps_query('DELETE FROM sysvars WHERE name = ?', ['s', 'periodic_fixture']);
    });
    ps_query('INSERT INTO sysvars (name,value) VALUES (?,?)', ['s', 'periodic_fixture', 's', '2026-05-15']);
    $rows = do_report($report, '2026', '05', '01', '2026', '05', '31', false, false, false, [], true);
    expect(is_array($rows) && count($rows) === 1 && $rows[0]['name'] === 'periodic_fixture')->toBeTrue('Report date placeholders failed');
    expect(do_report($report, '2026', '06', '01', '2026', '06', '30', false, false, false, [], true) === [])->toBeTrue('Report date filtering retained out-of-range data');
    $GLOBALS['userpermissions'] = [];
    create_periodic_email($GLOBALS['userref'], $report, 1, 0, [3], ['search' => 'café']);
    $row = ps_query('SELECT * FROM report_periodic_emails WHERE report = ?', ['i', $report])[0];
    expect($row['email_days'] == 1 && empty($row['user_groups']))->toBeTrue('Frequency minimum or group-recipient permission failed');
    expect(json_decode($row['search_params'], true) === ['search' => 'café'])->toBeTrue('Scheduled search parameters lost');
    $GLOBALS['userpermissions'] = ['m'];
    create_periodic_email($GLOBALS['userref'], $report, 1, 7, [1, 2], []);
    $rows = ps_query('SELECT * FROM report_periodic_emails WHERE report = ?', ['i', $report]);
    expect(count($rows) === 1 && $rows[0]['email_days'] == 7 && $rows[0]['user_groups'] === '1,2')->toBeTrue('Schedule replacement duplicated entry or lost recipients');
    $schedule = $rows[0]['ref'];
    unsubscribe_user_from_periodic_report($GLOBALS['userref'], $schedule);
    expect(ps_value('SELECT COUNT(*) value FROM report_periodic_emails_unsubscribe WHERE user_id = ? AND periodic_email_id = ?', ['i', $GLOBALS['userref'], 'i', $schedule], 0) == 1)->toBeTrue('Unsubscribe not persisted');
    delete_periodic_report($schedule);
    expect(ps_value('SELECT COUNT(*) value FROM report_periodic_emails WHERE ref = ?', ['i', $schedule], -1) == 0
        && ps_value('SELECT COUNT(*) value FROM report_periodic_emails_unsubscribe WHERE periodic_email_id = ?', ['i', $schedule], -1) == 0)->toBeTrue('Schedule deletion left dependent records');
});

it('finds the permitted report’s resource thumbnails and applies the requested resource type filter', function () use (&$fixtures): void {
    $photo = $fixtures->resource(1, 0);
    $document = $fixtures->resource(2, 0);
    $pending = $fixtures->resource(1, -2);
    $refs = implode(',', [$photo, $document, $pending]);
    $reports = [];
    $fixtures->onCleanup(static function () use (&$reports): void {
        ps_query(
            'DELETE FROM report WHERE ref IN (' . ps_param_insert(count($reports)) . ')',
            ps_param_fill($reports, 'i')
        );
    });
    foreach ([
        ["SELECT ref AS 'thumbnail' FROM resource WHERE ref IN ({$refs})", 0],
        ["SELECT ref AS 'thumbnail' FROM resource WHERE ref IN ({$refs})", 1],
        ["SELECT ref FROM resource WHERE ref IN ({$refs})", 0],
    ] as [$sql, $non_correlated]) {
        ps_query(
            'INSERT INTO report (name, query, support_non_correlated_sql) VALUES (?, ?, ?)',
            ['s', 'Report materialisation ' . generateRandomTestID(12), 's', $sql, 'i', $non_correlated]
        );
        $reports[] = sql_insert_id();
    }
    [$usable, $inapplicable, $missing_thumbnail] = $reports;
    $missing = (int) ps_value('SELECT COALESCE(MAX(ref), 0) + 1 AS value FROM report', [], 0);
    $find = static fn (int $report, string $type = ''): array =>
        array_column((array) do_search("!report{$report}", $type), 'ref');

    expect($find($missing))->toBeEmpty()
        ->and($find($inapplicable))->toBeEmpty()
        ->and($find($missing_thumbnail))->toBeEmpty()
        ->and($find($usable))->toEqualCanonicalizing([$photo, $document])
        ->and($find($usable, '2'))->toBe([$document]);
    $GLOBALS['userpermissions'] = array_values(array_diff($GLOBALS['userpermissions'], ['t']));
    expect($find($usable))->toBeEmpty();
});

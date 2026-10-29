<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/reporting_functions.php';
    $saved = $GLOBALS['userpermissions'];
    $report = 0;
    try {
        ps_query('INSERT INTO report (name,query) VALUES (?,?)', ['s', 'Date filter fixture', 's',
            "SELECT name FROM sysvars WHERE name = 'periodic_fixture' AND value >= '[from-y]-[from-m]-[from-d]' AND value <= '[to-y]-[to-m]-[to-d]'"]);
        $report = sql_insert_id();
        ps_query('INSERT INTO sysvars (name,value) VALUES (?,?)', ['s', 'periodic_fixture', 's', '2026-05-15']);
        $rows = do_report($report, '2026', '05', '01', '2026', '05', '31', false, false, false, [], true);
        $check(is_array($rows) && count($rows) === 1 && $rows[0]['name'] === 'periodic_fixture', 'Report date placeholders failed');
        $check(do_report($report, '2026', '06', '01', '2026', '06', '30', false, false, false, [], true) === [], 'Report date filtering retained out-of-range data');
        $GLOBALS['userpermissions'] = [];
        create_periodic_email($GLOBALS['userref'], $report, 1, 0, [3], ['search' => 'café']);
        $row = ps_query('SELECT * FROM report_periodic_emails WHERE report = ?', ['i', $report])[0];
        $check($row['email_days'] == 1 && empty($row['user_groups']), 'Frequency minimum or group-recipient permission failed');
        $check(json_decode($row['search_params'], true) === ['search' => 'café'], 'Scheduled search parameters lost');
        $GLOBALS['userpermissions'] = ['m'];
        create_periodic_email($GLOBALS['userref'], $report, 1, 7, [1, 2], []);
        $rows = ps_query('SELECT * FROM report_periodic_emails WHERE report = ?', ['i', $report]);
        $check(count($rows) === 1 && $rows[0]['email_days'] == 7 && $rows[0]['user_groups'] === '1,2', 'Schedule replacement duplicated entry or lost recipients');
        $schedule = $rows[0]['ref'];
        unsubscribe_user_from_periodic_report($GLOBALS['userref'], $schedule);
        $check(ps_value('SELECT COUNT(*) value FROM report_periodic_emails_unsubscribe WHERE user_id = ? AND periodic_email_id = ?', ['i', $GLOBALS['userref'], 'i', $schedule], 0) == 1, 'Unsubscribe not persisted');
        delete_periodic_report($schedule);
        $check(ps_value('SELECT COUNT(*) value FROM report_periodic_emails WHERE ref = ?', ['i', $schedule], -1) == 0
            && ps_value('SELECT COUNT(*) value FROM report_periodic_emails_unsubscribe WHERE periodic_email_id = ?', ['i', $schedule], -1) == 0, 'Schedule deletion left dependent records');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $saved;
        ps_query('DELETE FROM report_periodic_emails WHERE report = ?', ['i', $report]);
        ps_query('DELETE FROM report WHERE ref = ?', ['i', $report]);
        ps_query('DELETE FROM sysvars WHERE name = ?', ['s', 'periodic_fixture']);
    }
})();

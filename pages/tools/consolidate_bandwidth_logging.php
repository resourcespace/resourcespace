<?php

include '../../include/boot.php';

command_line_only();

$start_date = $argv[1] ?? null;
$end_date   = $argv[2] ?? null;

$start_valid = isset($start_date) && validateDatetime($start_date, 'Y-m-d');
$end_valid   = isset($end_date) && validateDatetime($end_date, 'Y-m-d');

if (!$start_valid || !$end_valid) {
    exit('Invalid start or end date please enter dates in a YYYY-MM-DD format');
}

$query_params  = [];
$condition_sql = '';

if ($start_valid) {
    $query_params = ['s', $start_date];
    $condition_sql = ' AND DATE(CONCAT(year, "-", LPAD(month, 2, 0), "-", LPAD(day, 2, 0))) > DATE(?)';
    if ($end_valid) {
        $query_params = array_merge($query_params, ['s', $end_date]);  
        $condition_sql .= ' AND DATE(CONCAT(year, "-", LPAD(month, 2, 0), "-", LPAD(day, 2, 0))) < DATE(?)';
    }
}

$unconsolidated_rows = ps_query('SELECT DISTINCT year, month, day FROM daily_stat WHERE object_ref != 0 AND activity_type = "Downloaded KB"' . $condition_sql, $query_params);
if (count($unconsolidated_rows) > 0) {
    foreach($unconsolidated_rows as $date) {
        $query_params = [
            'i', $date['year'],
            'i', $date['month'],
            'i', $date['day']
        ];
        ps_query('INSERT INTO daily_stat (`year`, `month`, `day`, usergroup, activity_type, object_ref, count, `external`)
                    SELECT ?, ?, ?, usergroup, "Downloaded KB", 0, SUM(count), `external` FROM daily_stat 
                    WHERE `year` = ? AND `month` = ? AND `day` = ? AND activity_type = "Downloaded KB" AND object_ref != 0 GROUP BY usergroup', array_merge($query_params, $query_params));

        ps_query('DELETE FROM daily_stat WHERE `year` = ? AND `month` = ? AND `day` = ? AND activity_type = "Downloaded KB" AND object_ref != 0', $query_params);
    }
}
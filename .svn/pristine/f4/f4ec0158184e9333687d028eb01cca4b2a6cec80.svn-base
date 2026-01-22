<?php

command_line_only();



$system_status = get_system_status();
if (isset($system_status['results']['required_php_modules']) && $system_status['results']['required_php_modules']['status'] === 'FAIL') {
    echo "SEVERITY: {$system_status['results']['required_php_modules']['severity']} INFO: {$system_status['results']['required_php_modules']['info']}; - ";
}


$mysql_log_transactions = true;
$mysql_log_location = '/var/some_incorrect_location';
$system_status = get_system_status();
unset($mysql_log_transactions, $mysql_log_location);
if (!isset($system_status['results']['mysql_log_location']) && $system_status['status'] === 'OK') {
    echo 'Bad mysql_log_location - ';
    return false;
}
if (isset($system_status['results']['mysql_log_location']) && !isset($system_status['results']['mysql_log_location']['severity'])) {
    echo 'Severity missing for $mysql_log_location - ';
    return false;
}


$debug_log_location = '/var/some_incorrect_location';
$debug_log = true;
$system_status = get_system_status();
unset($debug_log, $debug_log_location);
if (isset($system_status['results']['debug_log_location']) && $system_status['results']['debug_log_location']['status'] !== 'FAIL' && $system_status['results']['debug_log_location']['severity'] !== SEVERITY_CRITICAL) {
    echo 'Bad debug_log_location as a FAIL error - ';
    return false;
}


set_sysvar('last_cron', '2021-01-01');
$system_status = get_system_status();
set_sysvar('last_cron', date('Y-m-d'));
if (!isset($system_status['results']['cron_process']) && $system_status['status'] === 'OK') {
    echo 'Cron not executing - ';
    return false;
}
if (isset($system_status['results']['cron_process']) && !isset($system_status['results']['cron_process']['severity'])) {
    echo 'Severity missing for cron - ';
    return false;
}

return true;

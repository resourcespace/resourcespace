<?php

command_line_only();


$date_time = date('Y-m-d H:i:s', strtotime('2019-04-05 12:00:00'));
$use_cases = array(
    // Server is on UTC
    array(
        'server_tz'       => 'UTC',
        'user_local_tz'   => 'UTC',
        'expected_output' => '2019-04-05 12:00:00',
    ),
    array(
        'server_tz'       => 'UTC',
        'user_local_tz'   => 'Europe/Bucharest',
        'expected_output' => '2019-04-05 15:00:00',
    ),
    array(
        'server_tz'       => 'UTC',
        'user_local_tz'   => 'America/Chicago',
        'expected_output' => '2019-04-05 07:00:00',
    ),

    // Server is on Europe/Bucharest (UTC+3) => test non UTC server time zone setting
    array(
        'server_tz'       => 'Europe/Bucharest',
        'user_local_tz'   => 'UTC',
        'expected_output' => '2019-04-05 09:00:00',
    ),
    array(
        'server_tz'       => 'Europe/Bucharest',
        'user_local_tz'   => 'Europe/Bucharest',
        'expected_output' => '2019-04-05 12:00:00',
    ),
    array(
        'server_tz'       => 'Europe/Bucharest',
        'user_local_tz'   => 'Asia/Hong_Kong',
        'expected_output' => '2019-04-05 17:00:00',
    ),
);

$use_case_number = 1;
foreach ($use_cases as $use_case) {
    // Set php time zone
    date_default_timezone_set($use_case['server_tz']);

    // Set user local time zone
    $user_local_timezone = $use_case['user_local_tz'];

    if (offset_user_local_timezone($date_time, 'Y-m-d H:i:s') != $use_case['expected_output']) {
        echo "Use case {$use_case_number} - ";
        return false;
    }

    $use_case_number++;
}

return true;

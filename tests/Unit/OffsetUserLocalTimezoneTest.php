<?php

declare(strict_types=1);

$saved_user_timezone = [];
$saved_server_timezone = '';
beforeEach(function () use (&$saved_user_timezone, &$saved_server_timezone): void {
    $saved_user_timezone = array_intersect_key($GLOBALS, ['user_local_timezone' => true]);
    $saved_server_timezone = date_default_timezone_get();
});

afterEach(function () use (&$saved_user_timezone, &$saved_server_timezone): void {
    unset($GLOBALS['user_local_timezone']);
    if (array_key_exists('user_local_timezone', $saved_user_timezone)) {
        $GLOBALS['user_local_timezone'] = $saved_user_timezone['user_local_timezone'];
    }
    date_default_timezone_set($saved_server_timezone);
});

it('converts the server time to the user timezone', function (string $server, string $user, string $expected): void {
    date_default_timezone_set($server);
    $GLOBALS['user_local_timezone'] = $user;

    expect(offset_user_local_timezone('2019-04-05 12:00:00', 'Y-m-d H:i:s'))->toBe($expected);
})->with([
    'UTC to UTC' => ['UTC', 'UTC', '2019-04-05 12:00:00'],
    'UTC to Bucharest' => ['UTC', 'Europe/Bucharest', '2019-04-05 15:00:00'],
    'UTC to Chicago' => ['UTC', 'America/Chicago', '2019-04-05 07:00:00'],
    'Bucharest to UTC' => ['Europe/Bucharest', 'UTC', '2019-04-05 09:00:00'],
    'Bucharest to Bucharest' => ['Europe/Bucharest', 'Europe/Bucharest', '2019-04-05 12:00:00'],
    'Bucharest to Hong Kong' => ['Europe/Bucharest', 'Asia/Hong_Kong', '2019-04-05 17:00:00'],
]);

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'mysql_log_transactions', 'mysql_log_location', 'debug_log', 'debug_log_location', 'use_error_exception', 'storageurl',
    ]);
    $GLOBALS['storageurl'] = 'http://127.0.0.1:1/filestore';
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('reports an unusable path for an enabled system log', function (string $flag, string $path) use (&$fixtures): void {
    $parent = tmpfile();
    expect($parent)->toBeResource();
    $fixtures->onCleanup(static fn () => fclose($parent));
    $GLOBALS[$flag] = true;
    $GLOBALS[$path] = stream_get_meta_data($parent)['uri'] . '/unusable/log.txt';
    $GLOBALS['use_error_exception'] = true;
    $result = get_system_status()['results'][$path];
    expect($result['status'])->toBe('FAIL')
        ->and($result['severity'])->toBe(SEVERITY_CRITICAL);
})->with([
    'SQL transaction logging' => ['mysql_log_transactions', 'mysql_log_location'],
    'debug logging' => ['debug_log', 'debug_log_location'],
]);

it('reports cron that has not run recently', function () use (&$fixtures): void {
    $previous = get_sysvar('last_cron', null);
    $fixtures->onCleanup(static fn () => set_sysvar('last_cron', $previous));
    set_sysvar('last_cron', '2021-01-01');
    $GLOBALS['use_error_exception'] = true;
    $cron = get_system_status()['results']['cron_process'];
    expect($cron['status'])->toBe('FAIL')
        ->and($cron['severity'])->toBe(SEVERITY_WARNING);
});

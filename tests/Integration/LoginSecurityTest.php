<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/login_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/api_functions.php';

$fixtures = null;
$ip = '';
$user = null;
$name = '';
$password = 'Fixture only password 39';
beforeEach(function () use (&$fixtures, &$ip, &$user, &$name, $password): void {
    $fixtures = new IntegrationFixtures([
        'username', 'password', 'password_hash', 'session_hash', 'usergroup', 'udata_cache',
        'max_login_attempts_per_username', 'max_login_attempts_per_ip', 'max_login_attempts_wait_minutes',
        'password_brute_force_delay', 'ip_forwarded_for', 'password_hash_info',
    ]);
    $seed = generateRandomTestID(8);
    $name = 'security_' . $seed;
    $ip = '2001:db8::' . substr($seed, 0, 4) . ':' . substr($seed, 4, 4);
    $previous = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SERVER['REMOTE_ADDR'] = $ip;
    $GLOBALS['ip_forwarded_for'] = false;
    $GLOBALS['max_login_attempts_per_username'] = 3;
    $GLOBALS['max_login_attempts_per_ip'] = 5;
    $GLOBALS['max_login_attempts_wait_minutes'] = 10;
    $GLOBALS['password_brute_force_delay'] = 0;
    $GLOBALS['password_hash_info'] = ['algo' => PASSWORD_BCRYPT, 'options' => ['cost' => 4]];
    $user = $fixtures->user($name);
    ps_query(
        'UPDATE user SET password = ?, email = ?, approved = 1, login_tries = 0, session = ? WHERE ref = ?',
        ['s', rs_password_hash('RS' . $name . $password), 's', $name . '@example.invalid',
            's', generateSecureKey(32), 'i', $user]
    );
    unset($GLOBALS['udata_cache'][$user]);
    $fixtures->onCleanup(static function () use ($previous, $ip): void {
        if ($previous === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $previous;
        }
        ps_query('DELETE FROM ip_lockout WHERE ip = ?', ['s', $ip]);
        ps_query('DELETE FROM activity_log WHERE log_code = ? AND value_new = ?', ['s', LOG_CODE_FAILED_LOGIN_ATTEMPT, 's', $ip]);
    });
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('clears the account and IP failure counters after a successful API login below the threshold', function () use (&$name, &$ip, &$user, $password): void {
    expect(api_login($name, 'Incorrect password'))->toBeFalse()
        ->and(api_login($name, 'Incorrect password'))->toBeFalse();
    expect(api_login($name, $password))->toBeString()->not->toBeEmpty();
    expect(ps_value('SELECT login_tries AS value FROM user WHERE ref = ?', ['i', $user], -1))->toBe(0)
        ->and(ps_value('SELECT COUNT(*) AS value FROM ip_lockout WHERE ip = ?', ['s', $ip], -1))->toBe(0);
});

it('shares account failures between API and form login until the configured lockout expires', function (
    bool $api_attempts,
    int $threshold,
) use (&$name, &$ip, &$user, $password): void {
    $GLOBALS['max_login_attempts_per_username'] = $threshold;
    for ($i = 0; $i < $threshold; $i++) {
        if ($api_attempts) {
            expect(api_login($name . '@example.invalid', 'Incorrect password'))->toBeFalse();
        } else {
            expect(perform_login($name, 'Incorrect password')['valid'])->toBeFalse();
        }
    }
    ps_query('DELETE FROM ip_lockout WHERE ip = ?', ['s', $ip]);
    $read = static fn (): array => ps_query(
        'SELECT session, login_tries, login_last_try FROM user WHERE ref = ?',
        ['i', $user]
    )[0];
    $locked = $read();
    expect($locked['login_tries'])->toBe($threshold);
    expect(api_login($name, $password))->toBeFalse()
        ->and(api_login($name . '@example.invalid', $password))->toBeFalse()
        ->and(api_login($name, 'Incorrect password'))->toBeFalse()
        ->and(perform_login($name, $password)['valid'])->toBeFalse()
        ->and($read())->toBe($locked);

    ps_query('UPDATE user SET login_last_try = DATE_SUB(NOW(), INTERVAL 11 MINUTE) WHERE ref = ?', ['i', $user]);
    expect(api_login($name, 'Incorrect password'))->toBeFalse()
        ->and($read()['login_tries'])->toBe(1);
    expect(api_login($name, $password))->toBeString()->not->toBeEmpty();
    expect($read()['login_tries'])->toBe(0);
})->with([
    'failed API sign-ins' => [true, 3],
    'failed UI sign-ins with a different limit' => [false, 2],
]);

it('blocks both login paths by IP and starts a new failure count after expiry', function () use (&$name, &$ip, $password): void {
    for ($i = 0; $i < 5; $i++) {
        expect(api_login($name . '_missing', 'Incorrect password'))->toBeFalse();
    }
    $read = static fn (): array => ps_query('SELECT tries, last_try FROM ip_lockout WHERE ip = ?', ['s', $ip]);
    $blocked = $read();
    expect($blocked[0]['tries'])->toBe(5)
        ->and(api_login($name, $password))->toBeFalse()
        ->and(perform_login($name, $password)['valid'])->toBeFalse()
        ->and($read())->toBe($blocked);
    ps_query('UPDATE ip_lockout SET last_try = DATE_SUB(NOW(), INTERVAL 11 MINUTE) WHERE ip = ?', ['s', $ip]);
    expect(api_login($name . '_missing', 'Incorrect password'))->toBeFalse()
        ->and($read()[0]['tries'])->toBe(1);
    expect(api_login($name, $password))->toBeString();
    expect($read())->toBeEmpty();
});

it('delays and audits failed authentication for ordinary, disabled and expired accounts', function (
    string $case,
    ?string $note,
) use (&$name, &$user, &$ip, $password): void {
    $GLOBALS['password_brute_force_delay'] = 1;
    if ($case === 'disabled') {
        ps_query('UPDATE user SET approved = 2 WHERE ref = ?', ['i', $user]);
    }
    if ($case === 'expired') {
        ps_query('UPDATE user SET account_expires = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE ref = ?', ['i', $user]);
    }
    unset($GLOBALS['udata_cache'][$user]);
    $attempt = $case === 'unknown' ? $name . '_missing' : $name;
    $pass = in_array($case, ['disabled', 'expired'], true) ? $password : 'Incorrect password';
    $start = microtime(true);
    $valid = $case === 'web' ? perform_login($attempt, $pass)['valid'] : api_login($attempt, $pass);
    expect($valid)->toBeFalse()->and(microtime(true) - $start)->toBeGreaterThanOrEqual(0.9);
    $audit = ps_query(
        'SELECT note, user, remote_ref FROM activity_log WHERE log_code = ? AND value_new = ? ORDER BY ref DESC LIMIT 1',
        ['s', LOG_CODE_FAILED_LOGIN_ATTEMPT, 's', $ip]
    );
    expect($audit)->toHaveCount(1);
    if ($note !== null) {
        expect($audit[0]['note'])->toBe($note);
    }
    if ($case !== 'unknown') {
        expect((int) $audit[0]['user'])->toBe($user);
    }
})->with([
    'incorrect API password' => ['api', null],
    'incorrect form password' => ['web', null],
    'unknown account' => ['unknown', null],
    'disabled account' => ['disabled', 'Account Disabled'],
    'expired account' => ['expired', 'Account Expired'],
]);

it('does not apply the ordinary failure delay to successful or already blocked requests', function (
    string $state,
) use (&$name, &$user, &$ip, $password): void {
    $GLOBALS['password_brute_force_delay'] = 3;
    if ($state === 'account') {
        ps_query('UPDATE user SET login_tries = 3, login_last_try = NOW() WHERE ref = ?', ['i', $user]);
    }
    if ($state === 'IP') {
        ps_query('INSERT INTO ip_lockout (ip, tries, last_try) VALUES (?, 5, NOW())', ['s', $ip]);
    }
    $start = microtime(true);
    $result = api_login($name, $password);
    expect(microtime(true) - $start)->toBeLessThan(3.0);
    if ($state === 'success') {
        expect($result)->toBeString()->not->toBeEmpty();
    } else {
        expect($result)->toBeFalse();
        $note = ps_value(
            'SELECT note AS value FROM activity_log WHERE log_code = ? AND value_new = ? ORDER BY ref DESC LIMIT 1',
            ['s', LOG_CODE_FAILED_LOGIN_ATTEMPT, 's', $ip],
            ''
        );
        expect($note)->toBe($state === 'IP' ? 'IP address locked' : 'Account locked');
    }
})->with(['success', 'account', 'IP']);

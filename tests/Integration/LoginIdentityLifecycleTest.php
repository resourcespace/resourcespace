<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'username', 'password', 'password_hash', 'session_hash', 'usergroup', 'randomised_session_hash',
        'udata_cache', 'password_brute_force_delay', 'password_hash_info',
    ]);
    $GLOBALS['password_hash_info'] = ['algo' => PASSWORD_BCRYPT, 'options' => ['cost' => 4]];
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('creates fresh sessions and rejects wrong passwords or inactive accounts', function () use (&$fixtures): void {
    include_once dirname(__DIR__, 2) . '/include/login_functions.php';
    $name = 'login_identity_' . generateRandomTestID(6);
    $user = $fixtures->user($name);
    $hash = rs_password_hash('RS' . $name . 'LoginPassword1');
    ps_query('UPDATE user SET password = ?, approved = 1, login_tries = 0 WHERE ref = ?', ['s', $hash, 'i', $user]);
    unset($GLOBALS['udata_cache']);
    $GLOBALS['randomised_session_hash'] = true;
    $GLOBALS['password_brute_force_delay'] = 0;
    $login = perform_login($name, 'LoginPassword1');
    expect($login['valid'] && $login['ref'] == $user)->toBeTrue('Login did not identify fixture user');
    $session = ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '');
    expect($session !== '' && $session === $login['session_hash'])->toBeTrue('Login session not persisted');
    $second = perform_login($name, 'LoginPassword1');
    expect($second['valid'] && $second['session_hash'] !== $session)->toBeTrue('New randomized login reused old session');
    $before = ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '');
    expect(!perform_login($name, 'WrongPassword2')['valid'])->toBeTrue('Wrong password logged in');
    expect(ps_value('SELECT session value FROM user WHERE ref = ?', ['i', $user], '') === $before)->toBeTrue('Rejected login changed authenticated session');
    ps_query('UPDATE user SET approved = 2 WHERE ref = ?', ['i', $user]);
    unset($GLOBALS['udata_cache']);
    expect(!perform_login($name, 'LoginPassword1')['valid'])->toBeTrue('Disabled account logged in');
    ps_query('UPDATE user SET approved = 1, account_expires = NOW() - INTERVAL 1 DAY WHERE ref = ?', ['i', $user]);
    unset($GLOBALS['udata_cache']);
    expect(!perform_login($name, 'LoginPassword1')['valid'])->toBeTrue('Expired account logged in');
});

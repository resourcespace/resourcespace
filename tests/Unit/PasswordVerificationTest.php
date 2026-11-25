<?php

declare(strict_types=1);

require_once RESOURCESPACE_BASE_PATH . '/include/login_functions.php';

$saved_password_settings = [];
beforeEach(function () use (&$saved_password_settings): void {
    $saved_password_settings = array_intersect_key($GLOBALS, ['password_hash_info' => true, 'scramble_key' => true]);
    $GLOBALS['password_hash_info'] = ['algo' => PASSWORD_BCRYPT, 'options' => ['cost' => 5]];
    $GLOBALS['scramble_key'] = 'unit-test-password-key';
});
afterEach(function () use (&$saved_password_settings): void {
    unset($GLOBALS['password_hash_info']);
    unset($GLOBALS['scramble_key']);
    foreach ($saved_password_settings as $key => $value) {
        $GLOBALS[$key] = $value;
    }
});

it('hashes a password using the installation HMAC and configured algorithm', function (): void {
    $password = 'Fixture password';
    $hash = rs_password_hash($password);
    expect($hash)->toBeString()
        ->and(password_verify(hash_hmac('sha256', $password, $GLOBALS['scramble_key']), $hash))
        ->toBeTrue();
});

it('verifies current and historical stored password formats', function (int $version): void {
    $username = 'fixtureuser';
    $password = 'Fixture password';
    $prefixed = "RS{$username}{$password}";
    $hash = match ($version) {
        0 => $password,
        1 => md5($prefixed),
        2 => hash('sha256', md5($prefixed)),
        3 => rs_password_hash($prefixed),
    };

    expect(rs_password_verify($password, $hash, ['username' => $username]))->toBeTrue();
})->with(['plain text' => 0, 'MD5' => 1, 'SHA-256' => 2, 'configured algorithm' => 3]);

it('rejects a wrong password or supplied hash outside the impersonation context', function (): void {
    $username = 'fixtureuser';
    $password = 'Fixture password';
    $current = rs_password_hash("RS{$username}{$password}");
    $legacy = hash('sha256', md5("RS{$username}{$password}"));
    $user = ['username' => $username];

    expect(rs_password_verify('Wrong password', $current, $user))->toBeFalse()
        ->and(rs_password_verify($current, $current, $user))->toBeFalse()
        ->and(rs_password_verify($legacy, $legacy, $user))->toBeFalse();
    $user['impersonate_user'] = true;
    expect(rs_password_verify($current, $current, $user))->toBeTrue()
        ->and(rs_password_verify($password, $password, $user))->toBeTrue();
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userref', 'username', 'userpassword', 'password_min_length', 'password_min_alpha', 'password_min_numeric', 'password_min_uppercase', 'password_min_special']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('validates password changes and consumes reset credentials', function () use (&$fixtures): void {
    $username = 'password_lifecycle_' . generateRandomTestID(6);
    $user = $fixtures->user($username);
    $old = rs_password_hash('RS' . $username . 'OriginalPassword1');
    ps_query('UPDATE user SET password = ?, login_tries = 4 WHERE ref = ?', ['s', $old, 'i', $user]);
    $GLOBALS['userref'] = $user;
    $GLOBALS['username'] = $username;
    $GLOBALS['userpassword'] = $old;
    foreach (
        ['password_min_length' => 12, 'password_min_alpha' => 1, 'password_min_numeric' => 1,
        'password_min_uppercase' => 1, 'password_min_special' => 1] as $name => $value
    ) {
        $GLOBALS[$name] = $value;
    }
    $reset = create_password_reset_key($username);
    $row = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user])[0];
    expect(strlen($reset) === 15 && $row['password_reset_hash'] !== '')->toBeTrue('Reset key not issued');
    expect($reset === substr(hash('sha256', date('Ymd') . $row['password_reset_hash'] . $username . $GLOBALS['scramble_key']), 0, 15))->toBeTrue('Reset key does not match stored credential');
    $replacement = create_password_reset_key($username);
    expect($reset !== $replacement)->toBeTrue('Reset issuance reused previous key');
    foreach (['short', 'onlylowercaseletters', 'NoNumericOrSpecial', 'NoSpecialCharacter1'] as $invalid) {
        $before = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]);
        expect(change_password($invalid) !== true)->toBeTrue('Password policy accepted invalid password');
        expect(ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user]) === $before)->toBeTrue('Rejected password changed account');
    }
    expect(change_password('ReplacementPassword2!') === true)->toBeTrue('Valid password change rejected');
    $row = ps_query('SELECT * FROM user WHERE ref = ?', ['i', $user])[0];
    expect(rs_password_verify('ReplacementPassword2!', $row['password'], ['username' => $username]))->toBeTrue('New password not persisted');
    expect(!rs_password_verify('OriginalPassword1', $row['password'], ['username' => $username]))->toBeTrue('Old password still verifies');
    expect($row['password_reset_hash'] === null && $row['login_tries'] == 0 && !empty($row['password_last_change']))->toBeTrue('Password change did not clear reset credential/lockout or set change date');
});

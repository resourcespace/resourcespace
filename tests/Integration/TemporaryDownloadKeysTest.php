<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['usersession']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('accepts unexpired download keys from current and earlier releases', function (string $format) use (&$fixtures): void {
    $ref = $fixtures->resource(1, 0);
    $user = (int) $GLOBALS['userref'];
    if ($format === 'current') {
        $key = generate_temp_download_key($user, $ref, '');
    } else {
        $mac = hash_hmac('sha256', 'user_pass_mac', get_user($user)['password']);
        $secret = hash_hmac('sha512', 'dld_key', $GLOBALS['api_scramble_key'] . $GLOBALS['scramble_key']);
        $payload = implode(':', [generateSecureKey(128), $user, $ref, '', time(), $mac]);
        $encrypted = rsEncrypt($payload, $secret);
        $key = $format === 'raw' ? $encrypted : base64_encode($encrypted);
    }

    expect(validate_temp_download_key($ref, $key, '', 3600, false))->toBeTrue();
})->with(['before 10.3' => 'raw', '10.3 and 10.4' => 'base64', 'current' => 'current']);

it('rejects temporary keys for another resource, size, or expired or deleted account', function () use (&$fixtures): void {
    $user = $fixtures->user('downloadkey' . generateRandomTestID(12));
    $ref = $fixtures->resource(1, 0);
    $other = $fixtures->resource(1, 0);
    $key = generate_temp_download_key($user, $ref, 'pre');

    expect(validate_temp_download_key($ref, $key, 'pre', 3600, false))->toBeTrue()
        ->and(validate_temp_download_key($other, $key, 'pre', 3600, false))->toBeFalse()
        ->and(validate_temp_download_key($ref, $key, 'thm', 3600, false))->toBeFalse()
        ->and(validate_temp_download_key($ref, 'tampered', 'pre', 3600, false))->toBeFalse();

    $secret = hash_hmac('sha256', 'dld_key', $GLOBALS['scramble_key']);
    $expired = base64_encode(rsEncrypt("{$user}:{$ref}:pre:" . (time() - 7200), $secret, 32));
    expect(validate_temp_download_key($ref, $expired, 'pre', 3600, false))->toBeFalse();

    $_POST = ['deleteme' => 'yes'];
    expect(save_user($user))->toBeTrue();
    unset($GLOBALS['udata_cache'][$user]);
    expect(validate_temp_download_key($ref, $key, 'pre', 3600, false))->toBeFalse();
});

it('binds download links to the original resource and current session', function () use (&$fixtures): void {
    $ref = $fixtures->resource(1, 0);
    $other = $fixtures->resource(1, 0);
    $GLOBALS['usersession'] = 'pest-session-one';
    $key = download_link_generate_key((int) $GLOBALS['userref'], $ref);
    expect(download_link_check_key($key, $ref))->toBeTrue()
        ->and(download_link_check_key($key, $other))->toBeFalse();

    $GLOBALS['usersession'] = 'pest-session-two';
    expect(download_link_check_key($key, $ref))->toBeFalse();
});

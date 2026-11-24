<?php

declare(strict_types=1);

$saved_csrf_config = [];
beforeEach(function () use (&$saved_csrf_config): void {
    $saved_csrf_config = array_intersect_key($GLOBALS, [
        'scramble_key' => true,
        'CSRF_enabled' => true,
    ]);
    $GLOBALS['scramble_key'] = 'resourcespace-csrf-test-key';
    $GLOBALS['CSRF_enabled'] = true;
});

afterEach(function () use (&$saved_csrf_config): void {
    unset($GLOBALS['scramble_key'], $GLOBALS['CSRF_enabled']);
    foreach ($saved_csrf_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('accepts valid tokens and generates independent tokens for the same form and session', function (): void {
    $session = bin2hex(random_bytes(32));
    $token = generateCSRFToken($session, 'test_mutation');

    expect(isValidCSRFToken($token, $session))->toBeTrue()
        ->and(generateCSRFToken($session, 'test_mutation'))->not->toBe($token);
});

it('rejects an invalid token', function (Closure $invalid_token): void {
    $session = bin2hex(random_bytes(32));
    $token = generateCSRFToken($session, 'test_mutation');

    expect(isValidCSRFToken($invalid_token($token), $session))->toBeFalse();
})->with([
    'missing' => [static fn (string $token): string => ''],
    'malformed' => [static fn (string $token): string => 'invalid'],
    'tampered' => [static fn (string $token): string => substr($token, 0, -8) . 'tampered'],
]);

it('rejects a token issued to a different session', function (): void {
    $token = generateCSRFToken(bin2hex(random_bytes(32)), 'test_mutation');

    expect(isValidCSRFToken($token, bin2hex(random_bytes(32))))->toBeFalse();
});

it('allows a missing token when CSRF protection is disabled', function (): void {
    $GLOBALS['CSRF_enabled'] = false;

    expect(isValidCSRFToken('', bin2hex(random_bytes(32))))->toBeTrue();
});

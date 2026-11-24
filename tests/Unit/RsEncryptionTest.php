<?php

declare(strict_types=1);

$saved_encryption_config = [];
beforeEach(function () use (&$saved_encryption_config): void {
    $saved_encryption_config = array_intersect_key($GLOBALS, ['scramble_key' => true]);
    $GLOBALS['scramble_key'] = 'resourcespace-unit-test-key';
});

afterEach(function () use (&$saved_encryption_config): void {
    unset($GLOBALS['scramble_key']);
    foreach ($saved_encryption_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('decrypts encrypted data using the same key', function (): void {
    $plaintext = 'Test encryption data';
    $key = 'resourcespace-encryption-test';
    $encrypted = rsEncrypt($plaintext, $key);

    expect($encrypted)->not->toBe($plaintext)
        ->and(rsDecrypt($encrypted, $key))->toBe($plaintext);
});

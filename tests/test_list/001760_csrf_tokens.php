<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $saved = $GLOBALS['CSRF_enabled'];
    try {
        $GLOBALS['CSRF_enabled'] = true;
        $session = bin2hex(random_bytes(32));
        $token = generateCSRFToken($session, 'test_mutation');
        $check(isValidCSRFToken($token, $session), 'Valid CSRF token rejected');
        $check($token !== generateCSRFToken($session, 'test_mutation'), 'Tokens must have independent nonces');
        foreach (['', 'invalid', substr($token, 0, -8) . 'tampered'] as $invalid) {
            $check(!isValidCSRFToken($invalid, $session), 'Invalid CSRF token accepted');
        }
        $check(!isValidCSRFToken($token, bin2hex(random_bytes(32))), 'Token accepted for another session');
        $GLOBALS['CSRF_enabled'] = false;
        $check(isValidCSRFToken('', $session), 'Disabled CSRF compatibility changed');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['CSRF_enabled'] = $saved;
    }
})();

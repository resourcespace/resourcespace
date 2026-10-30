<?php

/**
 * Resolve an allowlisted API credential destination and its delivery mode.
 *
 * @param string      $system Destination configuration key.
 * @param string|null $url    Selected callback; required for destinations with multiple URLs.
 * @return array|false Destination with a single resolved URL, or false for invalid configuration/input.
 */
function get_api_session_destination(string $system, ?string $url = null): array|false
{
    global $api_issue_valid_destinations;

    $destination = $api_issue_valid_destinations[$system] ?? false;
    if (!is_array($destination)) {
        return false;
    }

    $delivery = $destination['delivery'] ?? 'post';
    $urls = $destination['url'] ?? [];
    $callback = is_array($urls) ? $url : $urls;
    if (
        !in_array($delivery, ['post', 'redirect_code'], true)
        || !is_string($callback)
        || $callback === ''
        || (is_array($urls) && !in_array($callback, $urls, true))
    ) {
        return false;
    }

    if ($delivery === 'redirect_code') {
        $parts = parse_url($callback);
        $stateparam = $destination['stateparam'] ?? '';
        if (
            strlen($system) > 100
            || !filter_var($callback, FILTER_VALIDATE_URL)
            || preg_match('/[\r\n]/', $callback)
            || $parts === false
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (
                ($parts['scheme'] ?? '') !== 'https'
                && !(($parts['scheme'] ?? '') === 'http'
                    && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true))
            )
            || !is_string($stateparam)
            || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $stateparam)
            || in_array($stateparam, ['code', 'error', 'system', 'url', 'submit', 'cancel'], true)
        ) {
            return false;
        }
    }

    $destination['url'] = $callback;
    $destination['delivery'] = $delivery;
    return $destination;
}

/**
 * Read an eligible credential owner's current details from the primary database without caching.
 * Account approval, expiry, group membership and API download-limit restrictions must still hold.
 *
 * @param int $user User reference.
 * @return array|false Current user details, or false if session/API access is unavailable.
 */
function get_api_session_code_user(int $user): array|false
{
    $rows = ps_query(
        "SELECT u.ref, u.username, u.email, u.fullname, u.session
           FROM user u INNER JOIN usergroup g ON u.usergroup = g.ref
          WHERE u.ref = ? AND u.approved = 1
            AND (u.account_expires IS NULL OR u.account_expires = '0000-00-00 00:00:00'
                 OR u.account_expires > NOW())
            AND u.session IS NOT NULL AND u.session <> ''
            AND COALESCE(g.download_limit, 0) <= 0",
        ['i', $user],
        '',
        -1,
        true,
        0
    );
    return $rows[0] ?? false;
}

/**
 * Issue a 120-second, single-use code after browser authentication and CSRF-protected consent.
 * Only code/key hashes are persisted. Expired codes are removed on issuance.
 *
 * @param int    $user           Consenting user reference.
 * @param string $system         Allowlisted destination configuration key.
 * @param string $redirect_uri   Exact approved callback URL.
 * @param string $code_challenge Base64url-encoded SHA-256 PKCE challenge (S256 only).
 * @param string $session        Browser session that authorized issuance.
 * @return string|false Opaque code, or false if API access, destination, challenge or user is invalid.
 */
function create_api_session_code(
    int $user,
    string $system,
    string $redirect_uri,
    string $code_challenge,
    #[\SensitiveParameter] string $session
): string|false {
    global $enable_remote_apis;

    $destination = get_api_session_destination($system, $redirect_uri);
    if (
        !$enable_remote_apis
        || $destination === false
        || $destination['delivery'] !== 'redirect_code'
        || $destination['url'] !== $redirect_uri
        || !preg_match('/\A[A-Za-z0-9_-]{43}\z/', $code_challenge)
    ) {
        return false;
    }

    $key_hash = hash('sha256', get_session_api_key($user));
    $owner = get_api_session_code_user($user);
    if ($owner === false || !hash_equals($owner['session'], $session)) {
        return false;
    }

    $code = generateSecureKey(64);
    $querydata = array_values(array_intersect($destination['querydata'], ['username', 'email', 'fullname']));
    ps_query('DELETE FROM user_api_session_code WHERE expires <= UNIX_TIMESTAMP()');
    ps_query(
        'INSERT INTO user_api_session_code
            (code_hash, user, `system`, redirect_uri, code_challenge, key_hash, querydata, expires)
         VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP() + 120)',
        [
            's', hash('sha256', $code), 'i', $user, 's', $system, 's', $redirect_uri,
            's', $code_challenge, 's', $key_hash,
            's', json_encode($querydata),
        ]
    );
    return $code;
}

/**
 * Redeem an API session code using its original destination and PKCE verifier.
 * Invalid attempts return false without consuming a valid code. An atomic DELETE elects exactly
 * one successful redeemer even when transactions are disabled. Reads deliberately bypass caches/replicas.
 *
 * @param string $system        Destination configuration key.
 * @param string $code          Opaque code received through the browser redirect.
 * @param string $code_verifier Original RFC 7636 verifier (43-128 unreserved ASCII characters).
 * @param string $redirect_uri  Exact callback URL used when issuing the code.
 * @return array|false Session signing key and authorized identity fields, or false if validation fails.
 */
function redeem_api_session_code(
    string $system,
    #[\SensitiveParameter] string $code,
    #[\SensitiveParameter] string $code_verifier,
    string $redirect_uri
): array|false {
    global $enable_remote_apis;

    $destination = get_api_session_destination($system, $redirect_uri);
    if (
        !$enable_remote_apis
        || $destination === false
        || $destination['delivery'] !== 'redirect_code'
        || $destination['url'] !== $redirect_uri
        || !preg_match('/\A[a-f0-9]{64}\z/', $code)
        || !preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/', $code_verifier)
    ) {
        return false;
    }

    $code_hash = hash('sha256', $code);
    $rows = ps_query(
        'SELECT user, `system`, redirect_uri, code_challenge, key_hash, querydata FROM user_api_session_code
         WHERE code_hash = ? AND `system` = ? AND redirect_uri = ? AND expires > UNIX_TIMESTAMP()',
        ['s', $code_hash, 's', $system, 's', $redirect_uri],
        '',
        -1,
        true,
        0
    );
    if (count($rows) !== 1) {
        return false;
    }

    $record = $rows[0];
    // Database collations can compare URLs case-insensitively; redirect binding must be exact.
    if ($record['system'] !== $system || $record['redirect_uri'] !== $redirect_uri) {
        return false;
    }
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
    if (!hash_equals($record['code_challenge'], $challenge)) {
        return false;
    }

    $sessionkey = get_session_api_key($record['user']);
    $owner = get_api_session_code_user($record['user']);
    if ($owner === false) {
        return false;
    }

    if (!hash_equals($record['key_hash'], hash('sha256', $sessionkey))) {
        return false;
    }

    $querydata = json_decode($record['querydata'], true);
    $response = ['sessionkey' => $sessionkey];
    foreach (array_intersect($querydata, $destination['querydata'], ['username', 'email', 'fullname']) as $key) {
        $response[$key] = $owner[$key];
    }

    ps_query(
        'DELETE FROM user_api_session_code WHERE code_hash = ? AND expires > UNIX_TIMESTAMP()',
        ['s', $code_hash]
    );
    return sql_affected_rows() === 1 ? $response : false;
}

<?php

include_once dirname(__FILE__) . "/../lib/GoogleAuthenticator.php";

/**
 * Validates a TOTP code for the given user reference.
 *
 * @param string $code      The TOTP code to validate.
 * @param int    $user_ref  The user reference.
 *
 * @return bool  True if the code is valid, false otherwise.
 */
function TOTP_validate(string $code, int $user_ref): bool
{
    $g = new GoogleAuthenticator();

    # Work out the secret
    $secret = TOTP_get_secret($user_ref);

    # Check against provided code
    return $g->checkCode($secret, $code);
}

/**
 * Generates a daily cookie hash based on the user reference and scramble key.
 *
 * @param int $user_ref  The user reference.
 *
 * @return string  A SHA-256 hash used for TOTP cookie validation.
 */
function TOTP_cookie(int $user_ref): string
{
    global $scramble_key, $totp_date_binding;
    return hash('sha256', date($totp_date_binding) . $user_ref . $scramble_key);
}

/**
 * Checks whether TOTP is set up for the specified user.
 *
 * @param int $user_ref  The user reference.
 *
 * @return bool  True if TOTP is set up, false otherwise.
 */
function TOTP_is_user_set_up(int $user_ref): bool
{
    return ps_value("select totp value from user where ref=?", ["i",$user_ref], 0) == 1;
}

/**
 * Generates the TOTP secret for the given user.
 *
 * @param int $user_ref  The user reference.
 *
 * @return string  A base32-encoded TOTP secret.
 */
function TOTP_get_secret(int $user_ref): string
{
    global $scramble_key;
    $secret = substr(hash('sha256', $user_ref . "_TOTP_" . $scramble_key), 0, 10);
    $base32 = new FixedBitNotation(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', true, true);
    return $base32->encode($secret);
}

/**
 * Constructs the otpauth URL for the user's TOTP setup.
 *
 * @param int $user_ref  The user reference.
 *
 * @return string  The otpauth URL used to generate a QR code in authenticator apps.
 */
function TOTP_get_url(int $user_ref): string
{
    global $applicationname;
    return "otpauth://totp/" . urlencode($applicationname) . "?secret=" . TOTP_get_secret($user_ref) . "&issuer=ResourceSpace";
}

/**
 * Marks TOTP setup as complete for the specified user.
 *
 * @param int $user_ref  The user reference.
 *
 * @return void
 */
function TOTP_setup_complete(int $user_ref): void
{
    ps_query("update user set totp=1,totp_tries=0 where ref=?", ["i",$user_ref]);
}

/**
 * Retrieves the number of failed TOTP attempts for the user.
 *
 * @param int $user_ref  The user reference.
 *
 * @return int  The number of TOTP validation attempts.
 */
function TOTP_tries(int $user_ref): int
{
    return ps_value("select totp_tries value from user where ref=?", ["i",$user_ref], 0);
}

/**
 * Increments the TOTP attempt counter for the specified user.
 *
 * @param int $user_ref  The user reference.
 *
 * @return void
 */
function TOTP_increase_tries(int $user_ref): void
{
    ps_query("update user set totp_tries=totp_tries+1 where ref=?", ["i",$user_ref]);
}
 
/**
 * Checks to see if SAML is authenticated for the given user if TOTP can be skipped for SAML users.
 *
 * @return bool True if SAML is enabled and authenticates, false otherwise
 */
function TOTP_saml_authenticate(): bool
{
    global $totp_saml;
    $inst_version = ps_value("SELECT inst_version AS value FROM plugins WHERE name = ?", ["s", "simplesaml"], '');

    if ($totp_saml && is_numeric($inst_version)) {
        return simplesaml_is_authenticated();
    }
    return false;
}
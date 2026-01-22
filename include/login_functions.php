<?php

/**
 * Performs the login using the global $username, and $password. Since the "externalauth" hook
 * is allowed to change the credentials later on, the $password_hash needs to be global as well.
 *
 * @return array Containing the login details ('valid' determines whether or not the login succeeded).
 */
function perform_login($loginuser = "", $loginpass = "")
{
    global $scramble_key, $lang, $max_login_attempts_wait_minutes, $max_login_attempts_per_ip, $max_login_attempts_per_username,
    $username, $password, $password_hash, $session_hash, $usergroup;

    $result = [];
    $result['valid'] = $valid = false;

    if (trim($loginpass) != "") {
        $password = trim($loginpass);
    }
    if (trim($loginuser) != "") {
        $username = trim($loginuser);
    }

    $username_as_entered = $username; # The username supplied at login before update below via get_user_by_username();

    // If a special key is sent, which is a hash based on the username and scramble key, then allow a login
    // using this hash as the password. This is for the 'log in as this user' feature.
    $impersonate_user = hash_equals(getval('userkey', ''), hash_hmac("sha256", "login_as_user" . $loginuser . date("Ymd"), $scramble_key, true));

    // Get user record
    $user_ref = get_user_by_username($username);
    $found_user_record = ($user_ref !== false);
    if ($found_user_record) {
        $user_data = get_user($user_ref);
        // Did the user log in using their email address? If so update username variable
        if (
            mb_strtolower($user_data['username']) !== mb_strtolower($username)
            && mb_strtolower($user_data['email']) === mb_strtolower($username)
        ) {
            $username = $user_data['username'];
        }
    }

    // User logs in
    if (
        $found_user_record
        && rs_password_verify($password, (string) $user_data['password'], ['username' => $username])
        && $password != ""
    ) {
        $password_hash_info = get_password_hash_info();
        $algo = $password_hash_info['algo'];
        $options = $password_hash_info['options'];

        if (password_needs_rehash($user_data['password'], $algo, $options)) {
            $password_hash = rs_password_hash("RS{$username}{$password}");
            if ($password_hash === false) {
                trigger_error('Failed to rehash password!');
            }

            ps_query("UPDATE user SET `password` = ? WHERE ref = ?", array("s",$password_hash,"i",$user_ref));
        } else {
            $password_hash = $user_data['password'];
        }
        $valid = true;
    } elseif (
        // An admin logs in as this user
        $found_user_record
        && $impersonate_user
        && rs_password_verify($password, (string) $user_data['password'], ['username' => $username, 'impersonate_user' => true])
    ) {
        $password_hash = $user_data['password'];
        $valid = true;
    }

    $ip = get_ip();

    # This may change the $username, $password, and $password_hash
    $externalresult = hook("externalauth", "", array($username_as_entered, $password)); #Attempt external auth if configured
    if ($externalresult) {
        // Get user data as per old method
        $user_ref = get_user_by_username($username);
        $found_user_record = ($user_ref !== false);
        if ($found_user_record) {
            $user_data = get_user($user_ref);
            $valid = true;
        }
    }

    if ($valid) {
        $userref = $user_data['ref'];
        $usergroup = $user_data['usergroup'];
        $expires = $user_data['account_expires'];
        $approved = $user_data['approved'];

        if ($approved == 2) {
            $result['error'] = $lang["accountdisabled"];
            log_activity('Account Disabled', LOG_CODE_FAILED_LOGIN_ATTEMPT, $ip, "user", "last_ip", $userref, null, null, $user_ref);
            return $result;
        }

        if ($expires != "" && $expires != "0000-00-00 00:00:00" && strtotime($expires) <= time()) {
            $result['error'] = $lang["accountexpired"];
            log_activity('Account Expired', LOG_CODE_FAILED_LOGIN_ATTEMPT, $ip, "user", "last_ip", $userref, null, null, $user_ref);
            return $result;
        }

        $session_hash = generate_session_hash($password_hash);

        $result['valid'] = true;
        $result['session_hash'] = $session_hash;
        $result['password_hash'] = $password_hash;
        $result['ref'] = $userref;


        $language = getval("language", "");

        update_user_access(
            $userref,
            [
            "session" => $session_hash,
            "lang" => $language,
            "login_tries" => 0,
            "last_ip" => $ip,
            ]
        );

        // Update user local time zone (if provided)
        $get_user_local_timezone = getval('user_local_timezone', null);
        set_config_option($userref, 'user_local_timezone', $get_user_local_timezone);

        # Log this
        daily_stat("User session", $userref);
        log_activity(null, LOG_CODE_LOGGED_IN, $ip, "user", "last_ip", ($userref != "" ? $userref : "null"), null, '', ($userref != "" ? $userref : "null"));

        # Blank the IP address lockout counter for this IP
        ps_query("DELETE FROM ip_lockout WHERE ip = ?", array("s",$ip));

        return $result;
    }

    # Invalid login
    if (isset($externalresult["error"])) {
        $result['error'] = $externalresult["error"];
    } // We may have been given a better error to display
    else {
        $result['error'] = $lang["loginincorrect"];
    }

    hook("loginincorrect");

    # Add / increment a lockout value for this IP
    $lockouts = ps_value("select count(*) value from ip_lockout where ip=? and tries<?", array("s",$ip,"i",$max_login_attempts_per_ip), "");

    if ($lockouts > 0) {
        # Existing row with room to move
        $tries = ps_value("select tries value from ip_lockout where ip=?", array("s",$ip), 0);
        $tries++;
        if ($tries == $max_login_attempts_per_ip) {
            # Show locked out message.
            $result['error'] = str_replace("?", $max_login_attempts_wait_minutes, $lang["max_login_attempts_exceeded"]);
            $log_message = 'Max login attempts from IP exceeded - IP: ' . $ip;
            log_activity($log_message, LOG_CODE_FAILED_LOGIN_ATTEMPT, $tries, 'ip_lockout', 'ip', $ip, 'ip');
        }
        # Increment
        ps_query("update ip_lockout set last_try=now(),tries=tries+1 where ip=?", array("s",$ip));
    } else {
        # New row
        ps_query("delete from ip_lockout where ip=?", array("s",$ip));
        ps_query("insert into ip_lockout (ip,tries,last_try) values (?,1,now())", array("s",$ip));
    }

    # Increment a lockout value for any matching username.
    $ulocks = ps_query("select ref,login_tries,login_last_try from user where username=?", array("s",$username));
    if (count($ulocks) > 0) {
        $tries = $ulocks[0]["login_tries"];
        if ($tries == "") {
            $tries = 1;
        } else {
            $tries++;
        }
        if ($tries > $max_login_attempts_per_username) {
            $tries = 1;
        }
        if ($tries == $max_login_attempts_per_username) {
            # Show locked out message.
            $result['error'] = str_replace("?", $max_login_attempts_wait_minutes, $lang["max_login_attempts_exceeded"]);
            $log_message = 'Max login attempts exceeded';
            log_activity($log_message, LOG_CODE_FAILED_LOGIN_ATTEMPT, $ip, 'user', 'ref', ($user_ref != false ? $user_ref : null), null, null, ($user_ref != false ? $user_ref : null));
        }
        ps_query("update user set login_tries=?,login_last_try=now() where username=?", array("i",$tries,"s",$username));
    }

    if ($valid !== true && !isset($log_message)) {
        if (isset($result['error']) && $result['error'] != '') {
            $log_message = strip_tags($result['error']);
        } else {
            $log_message = 'Failed Login';
        }
        log_activity(
            $log_message,                           # Note
            LOG_CODE_FAILED_LOGIN_ATTEMPT,          # Log Code
            $ip,                                    # Value New
            ($user_ref != false ? 'user'    : null),  # Remote Table
            ($user_ref != false ? 'last_ip' : null),  # Remote Column
            ($user_ref != false ? $user_ref : null),  # Remote Ref
            null,                                   # Ref Column Override
            null,                                   # Value Old
            ($user_ref != false ? $user_ref : null)   # User Ref
        );
    }

    return $result;
}

/**
 * Generates a unique session hash for user authentication.
 *
 * This function creates a session hash based on either a completely randomized method or a method
 * that combines a password hash with the current date. It ensures that the generated hash is unique
 * by checking against existing sessions in the database.
 *
 * @param string $password_hash The hashed password of the user, used for generating the session hash.
 * @return string Returns a unique session hash.
 */

function generate_session_hash($password_hash)
{
    # Generates a unique session hash
    global $randomised_session_hash,$scramble_key;

    if ($randomised_session_hash) {
        # Completely randomised session hashes. May be more secure, but allows only one user at a time.
        while (true) {
            $session = md5(generateSecureKey(128));
            if (ps_value("select count(*) value from user where session=?", array("s",$session), 0) == 0) {
                return $session;
            } # Return a unique hash only.
        }
    } else {
        # Session hash is based on the password hash and the date, so there is one new session hash each day. Allows two users to use the same login.
        $suffix = "";
        while (true) {
            $session = md5($scramble_key . $password_hash . date("Ymd") . $suffix);
            if (ps_value("select count(*) value from user where session=? and password<>?", array("s",$session,"s",$password_hash), 0) == 0) {
                return $session;
            } # Return a unique hash only.
            $suffix .= "."; # Extremely unlikely case that this was not a unique session (hash collision) - alter the string slightly and try again.
        }
    }
}

/**
* Set login cookies
*
* @param integer $user             User ref
* @param string  $session_hash     User session hash
* @param string  $language         Language code (e.g en)
* @param boolean $user_preferences Set colour theme from user preferences
*
* @return void
*/
function set_login_cookies($user, $session_hash, $language = "", $user_preferences = true)
{
    global $baseurl, $baseurl_short, $allow_keep_logged_in, $default_res_types, $language;
    $expires = 0;
    if ((string)(int)$user != (string)$user || $user < 1) {
        debug("set_login_cookies() - invalid paramters passed : " . func_get_args());
        return false;
    }
    if ($allow_keep_logged_in && getval("remember", "") != "") {
        $expires = 100;
    } # remember login for 100 days

    if ($language != "") {
        # Store language cookie
        rs_setcookie("language", $language, 1000); # Only used if not global cookies
        rs_setcookie("language", $language, 1000, $baseurl_short . "pages/");
    }

    # Set the session cookie. Do this for all paths that nay set the cookie as otherwise we can end up with a valid cookie at e.g. pages/team or pages/ajax
    rs_setcookie("user", "", 0, $baseurl_short);
    rs_setcookie("user", "", 0, $baseurl_short . "pages");
    rs_setcookie("user", "", 0, $baseurl_short . "pages/team");
    rs_setcookie("user", "", 0, $baseurl_short . "pages/admin");
    rs_setcookie("user", "", 0, $baseurl_short . "pages/ajax");

    # Set user cookie, setting secure only flag if a HTTPS site, and also setting the HTTPOnly flag so this cookie cannot be probed by scripts (mitigating potential XSS vuln.)
    rs_setcookie("user", $session_hash, $expires, $baseurl_short, "", substr($baseurl, 0, 5) == "https", true);

    # Set default resource types
    rs_setcookie('restypes', $default_res_types);
}

/**
* ResourceSpace password hashing
*
* @uses password_hash - @see https://www.php.net/manual/en/function.password-hash.php
*
* @param string $password Password
*
* @return string|false Password hash or false on failure
*/
function rs_password_hash(string $password)
{
    $phi = get_password_hash_info();
    $algo = $phi['algo'];
    $options = $phi['options'];

    // Pepper password with a known (by the application) secret.
    $hmac = hash_hmac('sha256', $password, $GLOBALS['scramble_key']);

    return password_hash($hmac, $algo, $options);
}

/**
* ResourceSpace verify password
*
* @param string $password Password
* @param string $hash     Password hash
* @param array  $data     Extra data required for matching hash expectations (e.g username, impersonate_user). Key is the variable name,
*                         value is the actual value for that variable.
*
* @return boolean
*/
function rs_password_verify(string $password, string $hash, array $data)
{
    // Prevent hashes being entered directly while still supporting direct entry of plain text passwords (for systems that
    // were set up prior to MD5 password encryption was added). If a special key is sent, which is the MD5 hash of the
    // username and the secret scramble key, then allow a login using the MD5 password hash as the password. This is for
    // the 'log in as this user' feature.
    $impersonate_user = $data['impersonate_user'] ?? false;
    $hash_info = password_get_info($hash);
    $pass_info = password_get_info($password);
    $is_like_v1_hash = (mb_strlen($password) === 32);
    $is_like_v2_hash = (mb_strlen($password) === 64);
    $is_v3_hash = ($hash_info['algo'] === $pass_info['algo'] && $hash_info['algoName'] !== 'unknown');
    if (!$impersonate_user && ($is_v3_hash || $is_like_v2_hash || $is_like_v1_hash)) {
        return false;
    }

    $RS_madeup_pass = "RS{$data['username']}{$password}";
    $hash_v1 = md5($RS_madeup_pass);
    $hash_v2 = hash('sha256', $hash_v1);

    // Most common case: hash is at version 3 (ie. hash generated using password_hash from PHP)
    if (password_verify(hash_hmac('sha256', $RS_madeup_pass, $GLOBALS['scramble_key']), $hash)) {
        return true;
    } elseif ($hash_v2 === $hash) {
        return true;
    } elseif ($hash_v1 === $hash) {
        return true;
    }
    // Legacy: Plain text password - when passwords were not hashed at all (very old code - should really not be the
    // case anymore) -or- when someone resets it manually in the database
    elseif ($password === $hash) {
        return true;
    } elseif (
        isset($GLOBALS["scramble_key_old"]) && $GLOBALS["migrating_scrambled"]
        && password_verify(hash_hmac('sha256', $RS_madeup_pass, $GLOBALS['scramble_key_old']), $hash)
    ) {
        // Force user to change password if password_expiry is enabled
        ps_query("UPDATE user SET password_last_change = '1970-01-01' WHERE username = ?", array("s",$data['username']));
        return true;
    }

    return false;
}

/**
* Helper function to get the password hash information (algorithm and options) from the global scope.
*
* @return array
*/
function get_password_hash_info()
{
    return [
        'algo' => ($GLOBALS['password_hash_info']['algo'] ?? PASSWORD_BCRYPT),
        'options' => ($GLOBALS['password_hash_info']['options'] ?? ['cost' => 12])
    ];
}

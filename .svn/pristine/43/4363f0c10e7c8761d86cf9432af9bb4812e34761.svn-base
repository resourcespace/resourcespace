<?php

/**
 * Get the configured path to the root of the SimpleSAML library
 * If $simplesaml_lib_path is not set this will be the [webroot]/plugins/simplesaml/lib folder
 *
 * @return string
 */
function simplesaml_get_lib_path()
{
    global $simplesaml_lib_path, $simplesaml_rsconfig;

    $lib_path = __DIR__ . '/../lib';

    if ('' == $simplesaml_lib_path || $simplesaml_rsconfig) {
        return $lib_path;
    }

    $lib_path2 = $simplesaml_lib_path;

    if ('/' == substr($lib_path2, -1)) {
        $lib_path2 = rtrim($lib_path2, '/');
    }

    if (file_exists($lib_path2)) {
        $lib_path = $lib_path2;
    }

    return $lib_path;
}

/**
 * Authenticate user, redfirecting to IdP if necessary
 *
 * @return boolean
 */
function simplesaml_authenticate()
{
    global $as;
    if (!simplesaml_is_configured()) {
        debug("simplesaml: plugin not configured.");
        return false;
    }
    if (!isset($as)) {
        require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
        $spname = get_saml_sp_name();
        debug("simplesaml: Using SP name '{$spname}'");
        $as = new SimpleSAML\Auth\Simple($spname);
    }
    $as->requireAuth();
    return true;
}

/**
 * Get SAML attributes
 *
 * @return array
 */
function simplesaml_getattributes()
{
    global $as;
    if (!isset($as)) {
        require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
        $spname = get_saml_sp_name();
        $as = new SimpleSAML\Auth\Simple($spname);
    }
    $as->requireAuth();

    // Prevent queuing requests waiting on session (which simplesamlphp is using internally) lock to be released
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    return $as->getAttributes();
}

/**
 * Sign out of SAML SP
 *
 * @return void
 */
function simplesaml_signout()
{
    global $baseurl, $as;
    if (!simplesaml_is_configured()) {
        debug("simplesaml: plugin not configured.");
        return false;
    }
    if (!isset($as)) {
        require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
        $spname = get_saml_sp_name();
        $as = new SimpleSAML\Auth\Simple($spname);
    }
    if ($as->isAuthenticated()) {
        $as->logout($baseurl . "/login.php");
    }
}

/**
 * Check if user has been authenticated by SimpleSAMLPHP
 *
 * @return boolean
 */
function simplesaml_is_authenticated()
{
    global $as,$simplesaml_authenticated;
    if (!simplesaml_is_configured()) {
        debug("simplesaml: plugin not configured.");
        return false;
    }

    if (isset($simplesaml_authenticated)) {
        return $simplesaml_authenticated;
    }

    if (!isset($as)) {
        require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
        $spname = get_saml_sp_name();
        $as = new SimpleSAML\Auth\Simple($spname);
    }
    if (isset($as) && $as->isAuthenticated()) {
        $simplesaml_authenticated = true;
        return true;
    }
    return false;
}

function simplesaml_getauthdata($value)
{
    if (!simplesaml_is_configured()) {
        debug("simplesaml: plugin not configured.");
        return false;
    }
    global $as;
    if (!isset($as)) {
        require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
        $spname = get_saml_sp_name();
        $as = new SimpleSAML\Auth\Simple($spname);
    }
    $as->requireAuth();
    return $as->getAuthData($value)->getValue();
}

/**
 * Notify of a new SAML user with an email address that is already in use by an existing user
 *
 * @param  string   $username       Username
 * @param  int      $group          Usergroup
 * @param  string   $email          Email
 * @param  array    $email_matches  Array of existing users with matching email
 * @param  int      $newuserid      ID of new user if created
 * @return void
 */
function simplesaml_duplicate_notify($username, $group, $email, $email_matches, $newuserid = 0)
{
    global $lang, $baseurl, $baseurl_short, $simplesaml_multiple_email_notify, $user_pref_user_management_notifications,
        $email_user_notifications, $applicationname;
    debug("simplesaml - user authenticated with matching email for existing users: " . $email);
    $message = $lang['simplesaml_multiple_email_match_text'] . " " . $email . "<br /><br />";
    $messageurl = "";
    if ($newuserid > 0) {
        $messageurl = generateURL("{$baseurl}/", ['u' => $newuserid]);
    }

    $message .= "</a><table class=\"InfoTable\" style=\"width:100%\"border=1>";
    $message .= sprintf(
        '<tr><th>%s</th><th>%s</th><th>%s</th></tr>',
        escape($lang['property-name']),
        escape($lang['property-reference']),
        escape($lang['username'])
    );
    foreach ($email_matches as $email_match) {
        $message .= sprintf(
            '<tr>
                <td><a href="%1$s" target="_blank">%2$s</a></td>
                <td><a href="%1$s" target="_blank">%3$s</a></td>
                <td><a href="%1$s" target="_blank">%4$s</a></td>
            </tr>\n',
            generateURL("{$baseurl}/", ['u' => $email_match["ref"]]),
            escape($email_match['fullname']),
            escape($email_match['ref']),
            escape($email_match['username'])
        );
    }

    $message .= "</table><a>";
    $emailmessage = $message;
    if ($messageurl != "") {
        $emailmessage .= sprintf(
            '%s: <a href="%s">%s</a><br />',
            escape($lang['simplesaml_usercreated']),
            $messageurl,
            escape($username)
        );
    }

    $notify_users = ps_query("SELECT ref, email, usergroup FROM user WHERE email = ?", array("s", $simplesaml_multiple_email_notify));
    $message_users = array();
    foreach ($notify_users as $notify_user) {
        get_config_option(
            ['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']],
            'user_pref_user_management_notifications',
            $send_message,
            $user_pref_user_management_notifications
        );
        if (!$send_message) {
            continue;
        }

        get_config_option(['user' => $notify_user['ref'], 'usergroup' => $notify_user['usergroup']], 'email_user_notifications', $send_email, $email_user_notifications);
        if ($send_email && filter_var($notify_user["email"], FILTER_VALIDATE_EMAIL)) {
            send_mail(
                $notify_user['email'],
                "{$applicationname}: {$lang['simplesaml_multiple_email_match_subject']}",
                $emailmessage
            );
        } else {
            $message_users[] = $notify_user["ref"];
        }
    }
    if (count($message_users) > 0) {
        // Send a message with long timeout (30 days)
        message_add($message_users, str_replace($baseurl . "/", $baseurl_short, $message), $messageurl);
    }
}

/**
 * Check that the SimpleSAMLphp configuration is valid
 *
 * @return array{success: bool, error?: string}
 */
function simplesaml_config_check(): array
{
    global $simplesaml_version, $lang;

    $fail_due_to = static fn(string $err): array => ['success' => false, 'error' => $err];

    if (!simplesaml_is_configured()) {
        debug("simplesaml: plugin not configured.");
        return $fail_due_to(text('simplesaml_error_not_configured'));
    }
    require_once simplesaml_get_lib_path() . '/lib/_autoload.php';

    try {
        $config = \SimpleSAML\Configuration::getInstance();
        $version = $config->getVersion();

        // Try loading the authsource and the IdP metadata
        $auth = new \SimpleSAML\Auth\Simple(trim($GLOBALS['simplesaml_sp'] ?? '') ?: 'resourcespace-sp');
        $metadata_handler = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();
        $metadata_handler->getMetaDataConfig(
            $auth->getAuthSource()->getMetadata()->getValue('idp'),
            'saml20-idp-remote'
        );
    } catch (\SimpleSAML\Error\MetadataNotFound $e) {
        debug("[ERR] simplesaml: Simplesaml plugin is not fully configured (missing IdP metadata). Error details - {$e}");
        return $fail_due_to(text('simplesaml_error_no_idp_metadata'));
    } catch (\SimpleSAML\Error\Exception $e) {
        debug("[ERR] simplesaml: Simplesaml plugin is not fully configured (missing authsource). Error details - {$e}");
        return $fail_due_to(text('simplesaml_error_no_authsource'));
    } catch (Throwable $t) {
        debug("[ERR] simplesaml: {$t}");
        return $fail_due_to(text('error_generic'));
    }

    if ($version != $simplesaml_version) {
        if (get_sysvar("SAML_UPGRADE_REQUIRED", 0) != 1) {
            system_notification(
                $lang['simplesaml_authorisation_version_error'],
                "https://www.resourcespace.com/knowledge-base/plugins/simplesaml#saml_instructions_migrate"
            );
            // Set flag so this is not sent multiple times
            set_sysvar("SAML_UPGRADE_REQUIRED", 1);
        }
        return $fail_due_to(text('simplesaml_authorisation_version_error'));
    }

    return ['success' => true];
}

/** Check whether PHP version will cause an error with current SAML config
 *
 * @param bool with_config Include config checks too? Set to false when you only want to check the PHP version
 */
function simplesaml_php_check(bool $with_config = true): bool
{
    global $simplesaml_check_phpversion,$simplesaml_php_check;

    $cache_key = 'with_config-' . json_encode($with_config);

    if (!isset($simplesaml_php_check[$cache_key])) {
        $check_php_vers = version_compare(phpversion(), $simplesaml_check_phpversion, '>=');

        $simplesaml_php_check[$cache_key] = $with_config
            ? (simplesaml_config_check()['success'] && $check_php_vers)
            : $check_php_vers;
    }

    return $simplesaml_php_check[$cache_key];
}


/**
 * Check that the SimpleSAMLphp has been configured.
 * This is done by either:-
 * a) Adding config, authsources and metadata files manually to the configured lib folder ($simplesaml_lib_path) or
 * b) By setting the options in the $simplesamlconfig variable and then enabling the
 * plugin option 'Use ResourceSpace configuration to set SP configuration and metadata'
 *
 * @return boolean
 */
function simplesaml_is_configured()
{
    global $simplesamlconfig, $simplesaml_rsconfig;
    if (
        ($simplesaml_rsconfig && !isset($simplesamlconfig))
         ||
        (!$simplesaml_rsconfig && !(file_exists(simplesaml_get_lib_path() . '/config/config.php')))
    ) {
        debug("simplesaml: plugin not configured.");
        return false;
    }
    return true;
}


/**
 * Generate a key/certificate pair
 *
 * @param  array $dn    Array of certificate attributes with named indexes as below
 *                      - "countryName"
 *                      - "stateOrProvinceName"
 *                      - "localityName"
 *                      - "organizationName"
 *                      - "commonName"
 *                      - "emailAddress"
 *
 *
 * @return array      Array containing paths to private key (.pem) and certificate (.crt) files
 */
function simplesaml_generate_keypair($dn)
{
    global $storagedir;
    // Generate key pair
    $privkey = openssl_pkey_new(array(
        "private_key_bits" => 4096,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ));

    // Generate CSR and certificate
    $csr = openssl_csr_new($dn, $privkey, array('digest_alg' => 'AES-128-CBC'));
    $x509 = openssl_csr_sign($csr, null, $privkey, 3650, array('digest_alg' => 'AES-128-CBC'));

    // Save key and certificate
    $pempath = $storagedir . "/system/" . uniqid("saml_") . ".pem";
    $crtpath = $storagedir . "/system/" . uniqid("saml_") . ".crt";
    openssl_x509_export_to_file($x509, $crtpath);
    openssl_pkey_export_to_file($privkey, $pempath);

    return array(
        'privatekey' => $pempath,
        'certificate' => $crtpath
        );
}

/**
 * Get the name of the saml sp to use
 *
 * @return string
 */
function get_saml_sp_name()
{
    global $simplesaml_sp, $safe_sp, $simplesaml_rsconfig, $simplesamlconfig;
    if ($safe_sp != "") {
        return $safe_sp;
    }

    $default_sp_name = "resourcespace-sp";
    $safe_sp = "";
    if (
        !$simplesaml_rsconfig
        || (isset($simplesamlconfig["authsources"]) && is_array($simplesamlconfig["authsources"]))
    ) {
        // If SAML has been configured we need to ensure that defined SP is valid
        $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
        $GLOBALS["use_error_exception"] = true;
        try {
            require_once simplesaml_get_lib_path() . '/lib/_autoload.php';
            $as = new SimpleSAML\Auth\Simple($simplesaml_sp);
            $as->getAuthSource();
        } catch (exception $e) {
            // Invalid SP name, use default
            $simplesaml_sp = $default_sp_name;
        }
        $GLOBALS["use_error_exception"] = $use_error_exception_cache;
    } else {
        $simplesaml_sp = $default_sp_name;
    }
    return $simplesaml_sp;
}


/**
 * Get the latest expiration date for the given SAML Identity Provider's certificates
 *
 * @param string $entityid  EntityID of SAML IdP
 *
 * @return string           Expiration date. Empty string if no certificate or IdP is not found.
 *
 */
function get_saml_metadata_expiry($entityid): string
{
    $expiry = "";
    if (!isset($GLOBALS["simplesamlconfig"]["metadata"][$entityid])) {
        return $expiry;
    } else {
        if (isset($GLOBALS["simplesamlconfig"]["metadata"][$entityid]["keys"])) {
            // Each IdP may have multiple certificates
            foreach ($GLOBALS["simplesamlconfig"]["metadata"][$entityid]["keys"] as $idpkey) {
                if ($idpkey["type"] == 'X509Certificate') {
                    $keyexpiry = getCertificateExpiry(preg_replace('/\s+/', '', $idpkey["X509Certificate"]));
                    if ($expiry == "" || $keyexpiry > $expiry) {
                        $expiry = $keyexpiry;
                    }
                }
            }
        }
        return $expiry;
    }
}


/**
 * Get the latest metadata from the configured IdP Metadata URL
 * 
 * @return bool|string     True if successful, otherwise error message
 */
function simplesaml_update_metadata()
{
    if (($GLOBALS["simplesaml_metadata_url"]) === ''
        || !is_safe_url($GLOBALS["simplesaml_metadata_url"])
    ) {
        return "Invalid metadata URL configured";
    }
    debug("Updating SAML metadata from '" . $GLOBALS["simplesaml_metadata_url"] . "'");
    $GLOBALS['use_error_exception'] = true;
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $GLOBALS["simplesaml_metadata_url"]);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        $response = curl_exec($ch);
        $response_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } catch (Throwable $t) {
        $error = str_replace("%error%", $t->getMessage(), $GLOBALS['lang']['simplesaml_update_metadata_error']);
        debug("simplesaml - " . $error);
        set_sysvar("saml_idp_metadata_error", $error);
        return false;
    }
    unset($GLOBALS['use_error_exception']);
    if ($response_status_code !== 200) {
        $error = str_replace("%error%", $response_status_code, $GLOBALS['lang']['simplesaml_update_metadata_invalid_response']);
        debug("simplesaml - " . $error);
        set_sysvar("saml_idp_metadata_error", $error);
    }

    $metadata_xml =  $response;
    require_once simplesaml_get_lib_path() . '/lib/_autoload.php';

    try {
        $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($metadata_xml);
    } catch (Exception $e) {
        $error = str_replace("%error%", $e->getMessage(), $GLOBALS['lang']['simplesaml_update_metadata_parse_error']);
        debug("simplesaml - " . $error);
        set_sysvar("saml_idp_metadata_error", $error);
        return false;
    }
    
    set_sysvar("saml_idp_metadata_error", "");
    $idpindex = array_key_first($entities);
    $parsedmetadata = $entities[$idpindex]->getMetadata20IdP();
    if (!isset($simplesamlconfig['metadata']) || reset($simplesamlconfig['metadata']) !== $parsedmetadata) {
        $simplesamlconfig['metadata'] = [];
        $simplesamlconfig['metadata'][$idpindex] = $parsedmetadata;
        set_sysvar("saml_idp_metadata", json_encode($simplesamlconfig['metadata']));
    }
    set_sysvar("saml_idp_metadata_last_updated", time());
    return true;
}

/**
 * Get SAML IdP metadata from sysvars, updating if necessary
 * 
 * @param bool $retry       Retry if invalid. Default true
 * 
 * @return array|false      Metadata if successful, false if failed
 */
function get_saml_metadata($retry = true)
{
    $storedsamlconfig = get_sysvar("saml_idp_metadata");
    $metadata = json_decode($storedsamlconfig, true);
    if ((trim($storedsamlconfig) == "" || $metadata === false) && $retry) {
        // Invalid metadata
        debug("simplesaml - invalid metadata in sysvars. Updating");
        $success = simplesaml_update_metadata();
        if($success !== true) {
            debug("simplesaml - invalid metadata. Update failed");
            return false;
        }
        return get_saml_metadata(false);
    }
    return $metadata;
}

function simplesaml_use_idp_metadata_url_mode(): bool
{
    return $GLOBALS['simplesaml_rsconfig'] === 2
        && isset($GLOBALS['simplesaml_metadata_url'])
        && trim($GLOBALS['simplesaml_metadata_url']) !== '';
}

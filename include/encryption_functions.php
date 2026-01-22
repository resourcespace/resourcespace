<?php

/**
* Encrypts data
*
* @uses generateSecureKey()
*
* @param  string  $data         Data to be encypted
* @param  string  $key          Key to use
* @param  string  $keylength    Optional key length
* @todo Add a fourth parameter to use with custom metadata (NOT ResourceSpace metadata) for generating MAC. this should
* add extra security by making MAC harder to be forged
*
* @return  string  Encrypted data
*/
function rsEncrypt($data, $key, $keylength = 128)
{
    global $scramble_key;

    /*
    Encrypt-then-MAC (EtM)
    ======================
    PlainText
        |
    Encryption <-- Key
        |_________   |
        |         |  |
        |      HashFunction
        |           |
    --------------------
    | Ciphertext | MAC |
    --------------------
    The plaintext is first encrypted, then a MAC is produced based on the resulting ciphertext.  The ciphertext and its
    MAC are sent together.
    */
    $method  = "AES-128-CTR";
    $options = OPENSSL_RAW_DATA;
    $nonce   = generateSecureKey($keylength);

    // Get 2 derived subkeys, one for message authentication code (MAC) and the other one for encryption/ decryption.
    $mac_key = hash_hmac("sha256", "mac_key", $scramble_key, true);
    $enc_key = hash_hmac("sha256", "enc_key", $scramble_key, true);

    // Synthetic Initialization Vector (SIV)
    $siv = substr(hash_hmac("sha256", "{$nonce}{$scramble_key}{$key}", $mac_key, true), 0, 16);

    $cyphertext = bin2hex(openssl_encrypt($data, $method, $enc_key, $options, $siv));

    $mac = hash_hmac("sha256", "{$cyphertext}{$nonce}{$scramble_key}", $mac_key);

    return "{$nonce}@@{$cyphertext}@@{$mac}";
}

/**
* Decrypts data
*
* @param  string  $data  Data to be decrypted
* @param  string  $key
* @todo Add a third parameter to use with custom metadata (NOT ResourceSpace metadata) for generating MAC. this should
* add extra security by making MAC harder to be forged
*
* @return  false|string  Returns FALSE if MAC check failed, plaintext otherwise
*/
function rsDecrypt($data, $key)
{
    global $scramble_key;

    $method  = "AES-128-CTR";
    $options = OPENSSL_RAW_DATA;

    // Get 2 derived subkeys, one for message authentication code (MAC) and the other one for encryption/ decryption.
    $mac_key = hash_hmac("sha256", "mac_key", $scramble_key, true);
    $enc_key = hash_hmac("sha256", "enc_key", $scramble_key, true);
    if (count(explode("@@", $data)) < 3) {
        return false;
    }
    list($nonce, $cyphertext, $mac) = explode("@@", $data);

    // Check MAC
    if ($mac !== hash_hmac("sha256", "{$cyphertext}{$nonce}{$scramble_key}", $mac_key)) {
        debug("rsCrypt: MAC did not match!");
        return false;
    }
    // Synthetic Initialization Vector (SIV)
    $siv = substr(hash_hmac("sha256", "{$nonce}{$scramble_key}{$key}", $mac_key, true), 0, 16);

    return openssl_decrypt(hex2bin($cyphertext), $method, $enc_key, $options, $siv);
}

/**
* Prior to eval() checks to make sure the code has been signed first, by the offline script / migration script.
*
* @param  string  $code  The code to check
*
* @return  string  The code, if correctly signed, or an empty string if not.
*/
function eval_check_signed($code)
{
    // No need to sign empty string.
    if (trim($code) == "") {
        return "";
    }

    // Extract the signature from the code.
    $code_split = explode("\n", $code);
    if (count($code_split) < 2) {
        set_sysvar("code_sign_required", "YES");
        return "";
    } // Not enough lines to include a key, exit
    $signature = str_replace("//SIG", "", trim($code_split[0])); // Extract signature
    $code = trim(substr($code, strpos($code, "\n") + 1));

    // Code not signed correctly? Exit early.
    if ($signature != sign_code($code)) {
        set_sysvar("code_sign_required", "YES");
        debug("Invalid (eval) code signature ({$signature})");
        return "";
    }

    // All is as expected, return the code ready for execution.
    return $code;
}

/**
* Returns a signature for a given block of code.
*
* @param  string  $code  The code to sign
*
* @return  string  The signature
*/
function sign_code($code)
{
    global $scramble_key;
    debug(sprintf('sign_code will use the following $scramble_key (***%s)', mb_substr($scramble_key, -4)));
    return hash_hmac("sha256", trim($code), $scramble_key);
}

/**
* Returns a signature for a given block of code.
*
* @param   bool  $confirm                   Require user to approve code changes when resigning from the server side.
* @param   bool  $output                    Display output. $confirm will override this option to provide detail if approval needed.
* @param   bool  $output_changes_only       Output changes only - do not sign code.
*
* @return  void
*/
function resign_all_code($confirm = true, $output = true, $output_changes_only = false)
{
    debug_function_call(__FUNCTION__, func_get_args());
    if ($confirm) {
        $output = true;
    }

    $todo = array
        (
        array("resource_type_field",    "value_filter"),
        array("resource_type_field",    "onchange_macro"),
        array("resource_type_field",    "autocomplete_macro"),
        array("resource_type_field",    "exiftool_filter"),
        array("resource_type",          "config_options"),
        array("usergroup",              "config_options")
        );
    foreach ($todo as [$table, $column]) {
        $rows = ps_query("select ref,`$column` from `$table`");
        foreach ($rows as $row) {
            $code = $row[$column];
            $ref = $row["ref"];
            if (trim((string)$code) == "") {
                $code = "";
            }
            if ($output && !$output_changes_only) {
                echo $table . " -> " . $column . " -> " . $ref;
            }

            // Extract signature if already one present
            $purecode = $code;
            while (substr($purecode, 0, 5) == "//SIG") {
                $purecode = trim(substr($purecode, strpos($purecode, "\n") + 1));
            }

            if (trim(eval_check_signed($code)) !== trim($purecode)) {
                // Code is not signed.

                // Needs signing. Confirm it's safe.
                if ($confirm) {
                    if (!$output_changes_only) {
                        echo " needs signing\n-----------------------------\n";
                        echo $purecode;
                        echo "\n-----------------------------\nIs this code safe? (y/n)";
                        ob_flush();
                        $line = fgets(STDIN);
                        if (trim($line) != "y") {
                            exit();
                        }
                    } else {
                        echo $table . " -> " . $column . " -> " . $ref . "\n" . $code . "\n\n";
                    }
                }

                $code = trim($code);
                $sig = sign_code($code);
                $code = "//SIG" . $sig . "\n" . $purecode;
                if (!$output_changes_only) {
                    ps_query("update `$table` set `$column`=? where ref=?", array("s",$code,"i",$ref));
                    log_activity(
                        note: 'resign_all_code',
                        log_code: LOG_CODE_EDITED,
                        value_new: $code,
                        value_old: $row[$column],
                        remote_table: $table,
                        remote_column: $column,
                        remote_ref: $ref
                    );
                    debug("[WARN] resign_all_code for {$table} -> {$column} -> {$ref} FAIL (new SIG = {$sig})");
                }
            } else {
                if ($output && !$output_changes_only) {
                    echo " is OK\n";
                    debug("resign_all_code for {$table} -> {$column} -> {$ref} is OK");
                }
            }
        }
    }
    // Clear the cache so the code uses the updated signed code.
    if (!$output_changes_only) {
        clear_query_cache("schema");
        set_sysvar("code_sign_required", "");
    }
}

/**
 * Used to compare the user's provided token with the expected value derived from the given identifier
 *
 * Used by isValidCSRFToken()
 * Also used on upload_batch to validate an upload session when user cookie not available (i.e. companion uploads)
 *
 * @uses rsDecrypt()
 *
 * @param string $token_data    Encrypted token data
 * @param string $id            Identifier
 *
 * @return bool
 *
 */
function rs_validate_token($token_data, $id)
{
    if (trim($token_data) === "") {
        debug("rs_validate_token(): INVALID - no token data");
        return false;
    }

    $plaintext = rsDecrypt($token_data, $id);
    if ($plaintext === false) {
        debug("rs_validate_token(): INVALID - unable to decrypt token data");
        return false;
    }
    $csrf_data = json_decode($plaintext, true);
    if (is_null($csrf_data)) {
        debug("rs_validate_token(): INVALID - unable to decode token data");
        return false;
    }

    if ($csrf_data["session"] === $id) {
        return true;
    }

    debug("rs_validate_token(): INVALID - decoded value does not match");
    return false;
}

<?php

/*
 * API v2 functions
 *
 * Montala Limited, July 2016
 *
 * For documentation please see: http://www.resourcespace.com/knowledge-base/api/
 *
 */
global $iiif_enabled;
if ($iiif_enabled) {
    include_once __DIR__ . '/iiif_functions.php';
}

/**
 * Return a private scramble key for this user.
 *
 * @param  integer $user The user ID
 * @return string|false
 */
function get_api_key($user)
{
    global $api_scramble_key;
    return hash("sha256", $user . $api_scramble_key);
}

/**
 * Check a query is signed correctly.
 *
 * @param  string $username The username of the calling user
 * @param  string $querystring The query being passed to the API
 * @param  string $sign The signature to check
 * @param  string $authmode The type of key being provided (user key or session key)
 */
function check_api_key($username, $querystring, $sign, $authmode = "userkey"): bool
{
    // Fetch user ID and API key
    $user = get_user_by_username($username);
    if ($user === false) {
        return false;
    }
    $aj = strpos($querystring, "&ajax=");
    if ($aj !== false) {
        $querystring = substr($querystring, 0, $aj);
    }

    if ($authmode == "sessionkey") {
        $userkey = get_session_api_key($user);
    } else {
        $userkey = get_api_key($user);
    }

    # Calculate the expected signature and check it matches
    $expected = hash("sha256", $userkey . $querystring);
    if ($expected === $sign) {
        return true;
    }
    # Also try matching against the username - allows remote API use without knowing the user ID, e.g. in the event of managing multiple systems each with a common username but different ID.
    if (hash("sha256", get_api_key($username) . $querystring) === $sign) {
        return true;
    }
    return false;
}

/**
 * Execute the specified API function.
 *
 * @param  string $query The query string passed to the API
 * @param  boolean $pretty Should the JSON encoded result be 'pretty' i.e. formatted for reading?
 * @return bool|string
 */
function execute_api_call($query, $pretty = false)
{
    $params = [];
    parse_str($query, $params);
    if (!array_key_exists("function", $params)) {
        return false;
    }
    $function = $params["function"];
    if (!function_exists("api_" . $function)) {
        return false;
    }

    global $lang;

    // Check if this is a whitelisted function for browser use (native mode bypasses $enable_remote_apis=false;)
    if (
        defined("API_AUTHMODE_NATIVE")
        && !in_array($function, API_NATIVE_WHITELIST)
    ) {
        ajax_unauthorized();
    }
    // Construct an array of the real params, setting default values as necessary
    $setparams = [];
    $n = 0;
    $fct = new ReflectionFunction("api_" . $function);
    foreach ($fct->getParameters() as $fparam) {
        $paramkey = $n + 1;
        $param_name = $fparam->getName();
        debug("API: Checking for parameter " . $param_name . " (param" . $paramkey . ")");
        if (array_key_exists("param" . $paramkey, $params)) {
            debug("API: " . $param_name . " -   value has been passed : '" . $params["param" . $paramkey] . "'");
            $setparams[$n] = $params["param" . $paramkey];
        } elseif (array_key_exists($param_name, $params)) {
            debug("API: {$param_name} - value has been passed (by name): '" . json_encode($params[$param_name]) . "'");

            // Check if array;
            $type = $fparam->getType();
            if (gettype($type) == "object") {
                // type is an object
                $type = $type->getName();
            }
            if ($fparam->hasType() && gettype($type) == "string" && $type == "array" && !is_array($params[$param_name])) {
                // Decode as must be json encoded if array
                $GLOBALS["use_error_exception"] = true;
                try {
                    $decoded = json_decode($params[$param_name], JSON_OBJECT_AS_ARRAY);
                } catch (Exception $e) {
                    $error = str_replace(
                        array("%arg", "%expected-type", "%type"),
                        array($param_name, "array (json encoded)",$lang['unknown']),
                        $lang["error-type-mismatch"]
                    );
                    return json_encode($error);
                }
                unset($GLOBALS["use_error_exception"]);
                // Check passed data type after decode
                if (gettype($decoded) != "array") {
                    $error = str_replace(
                        array("%arg", "%expected-type", "%type"),
                        array($param_name, "array (json encoded)", $lang['unknown']),
                        $lang["error-type-mismatch"]
                    );
                    return json_encode($error);
                }
                $params[$param_name] = $decoded;
            }

            $setparams[$n] = $params[$param_name];
        } elseif ($fparam->isOptional()) {
            // Set default value if nothing passed e.g. from API test tool
            debug("API: " . $param_name . " -  setting default value = '" . $fparam->getDefaultValue() . "'");
            $setparams[$n] = $fparam->getDefaultValue();
        } else {
             // Set as empty
            debug("API: {$param_name} -  setting empty value");
            $setparams[$n] = "";
        }
        $n++;
    }

    debug("API: calling api_" . $function);
    $result = call_user_func_array("api_" . $function, $setparams);

    if ($pretty) {
            debug("API: json_encode() using JSON_PRETTY_PRINT");
            $json_encoded_result = json_encode($result, (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0));
    } else {
            debug("API: json_encode()");
            $json_encoded_result = json_encode($result);
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug("API: JSON error: " . json_last_error_msg());
        debug("API: JSON error when \$result = " . print_r($result, true));

        $json_encoded_result = json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    return $json_encoded_result;
}

/**
 * Return the session specific key for the given user.
 *
 * @param  integer $user The user ID
 * @return string
 */
function get_session_api_key($user)
{
    global $scramble_key;
    $private_key = get_api_key($user);
    $usersession = ps_value("SELECT session value FROM user where ref = ?", array("i",$user), "");
    return hash_hmac("sha256", "{$usersession}{$private_key}", $scramble_key);
}

/**
* API login function

*
 * @param  string $username         Username
 * @param  string $password         Password to validate
 * @return string|false             FALSE if invalid, session API key if valid
*/
function api_login($username, $password)
{
    global $session_hash, $scramble_key;
    $user = get_user_by_username($username);
    if ($user === false) {
        return false;
    }
    $result = perform_login($username, $password);
    $private_key = get_api_key($user);
    if ((bool)$result['valid']) {
        return hash_hmac("sha256", "{$session_hash}{$private_key}", $scramble_key);
    }

    return false;
}

/**
 * Validate URL supplied in APIs create resource or upload by URL. Requires the URL hostname to be added in config $api_upload_urls
 *
 * @param   string   $url   The full URL.
 *
 * @return  bool   Returns true if a valid URL is found.
 */
function api_validate_upload_url($url)
{
    $url = filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);
    if ($url === false) {
        return false;
    }

    $url_parts = parse_url($url);

    if (in_array($url_parts['scheme'], BLOCKED_STREAM_WRAPPERS)) {
        return false;
    }

    global $api_upload_urls;
    if (!isset($api_upload_urls)) {
        return true; // For systems prior to this config.
    }

    if (in_array($url_parts['host'], $api_upload_urls)) {
        return true;
    }

    return false;
}

/**
 * Assert API request is using POST method.
 *
 * @param bool $force Force the assertion
 *
 * @return array Returns JSend data back {@see ajax_functions.php} if not POST method
 */
function assert_post_request(bool $force): array
{
    // Legacy use cases we don't want to break backwards compatibility for (e.g JS api() makes only POST requests but
    // other clients might only use GET because it was allowed if not authenticating with native mode)
    if (!$force) {
        return [];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return [];
    } else {
        http_response_code(405);
        return ajax_response_fail(ajax_build_message($GLOBALS['lang']['error-method-not_allowed']));
    }
}

/**
 * Assert API sent the expected content type.
 *
 * @param string $expected MIME type
 * @param string $received_raw MIME type
 *
 * @return array Returns JSend data back {@see ajax_functions.php} if received Content-Type is unexpected
 */
function assert_content_type(string $expected, string $received_raw): array
{
    $expected = trim($expected);
    if ($expected === '') {
        trigger_error('Expected MIME type MUST not be a blank string', E_USER_ERROR);
    }

    $encoding = 'UTF-8';
    $received = mb_strcut($received_raw, 0, mb_strlen($expected, $encoding), $encoding);
    if ($expected === $received) {
        return [];
    }

    http_response_code(415);
    header("Accept: {$expected}");
    return ajax_response_fail([]);
}

/**
 * Return a summary of daily statistics
 *
 * @param  int $days The number of days - note max 365 days as only the current and previous year's data is accessed.
 */
function api_get_daily_stat_summary(int $days = 30)
{
    if (!checkperm("a")) {
        return false;
    } // Admin only
    return ps_query("SELECT activity_type,sum(count) `count`
        FROM daily_stat  
        WHERE 
            (`year`=year(NOW()) OR `year`=year(NOW())-1)
        AND
            concat(`year`,'-',`month`,'-',`day`,'-')>date_sub(NOW(), interval ? DAY)
        GROUP BY activity_type
            ", ["i",$days]);
}

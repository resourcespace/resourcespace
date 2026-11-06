<?php
$disable_browser_check = true;

include "../include/boot.php";
header('Content-Type: application/json');
include_once "../include/image_processing.php";
include_once "../include/api_functions.php";
include_once "../include/ajax_functions.php";
include_once "../include/api_bindings.php";
include_once "../include/login_functions.php";
include_once "../include/dash_functions.php";

# Get authentication mode (userkey, sessionkey or native)
$authmode = getval("authmode", "userkey");

# Native authmode always required
if (!$enable_remote_apis && $authmode !== "native") {
    http_response_code(403);
    exit("API not enabled.");
}


define("API_CALL", true);

# Get parameters
$user = getval("user", "");
$sign = getval("sign", "");
$query = $_SERVER["QUERY_STRING"] ?? '';
$pretty = filter_var(getval('pretty', ''), FILTER_VALIDATE_BOOLEAN); # Should response be prettyfied?

# Support POST request where 'query' is POSTed and is the full query string.
if (getval("query", "") != "") {
    $query = getval("query", "");
}

// Authentication and dispatch must use exactly the same arguments.
$query = prepare_api_query($query);
if ($query === false) {
    http_response_code(400);
    exit('Ambiguous or malformed query parameters');
}
parse_str($query, $query_params);

$validauthmodes = array("userkey", "native", "sessionkey");
if (!in_array($authmode, $validauthmodes)) {
    $authmode = "userkey";
}

// Check API binding function (i.e. endpoint)
$getval_fct = trim(getval('function', ''));
$query_param_fct = trim($query_params['function'] ?? '');
if ($getval_fct !== '' && $query_param_fct !== '' && $getval_fct !== $query_param_fct) {
    http_response_code(400);
    debug(
        sprintf(
            'API: Conflicting function binding requested: %s vs %s',
            encode_js_value($getval_fct),
            encode_js_value($query_param_fct)
        )
    );
    exit('Conflicting function binding requested');
}

// Prefer function to be based on the query (consistent with execute_api_call())
$function = $query_param_fct !== '' ? $query_param_fct : $getval_fct;

// Log all API requests using userkey authentication, including failed attempts.
if ($authmode === "userkey") {
    log_api_request($user, $function);
}

if ($function != "login") {
    if ($authmode == "native") {
        debug("API: Native authmode, authenticating");
        define('API_AUTHMODE_NATIVE', true);
        include __DIR__ . "/../include/authenticate.php";
        debug("API: Native authmode, authenticated OK");
    } else {
        # Authenticate based on the provided signature.
        if (!check_api_key($user, $query, $sign, $authmode)) {
            debug("API: Invalid signature");
            http_response_code(401);
            exit("Invalid signature");
        }

        # Log user in (if permitted)
        $user_data = get_user(get_user_by_username($user));
        $validuser = $user_data !== false && is_approved_user($user_data) && setup_user($user_data);
        if (!$validuser) {
            ajax_send_response(
                401,
                ['error' => [
                    'status' => 401,
                    'title'  => $GLOBALS['lang']['unauthorized'],
                    'detail' => $GLOBALS['lang']['error-permissiondenied']
                ]]
            );
        }
        update_user_access(0, ["last_browser" => isset($_SERVER["HTTP_USER_AGENT"]) ? substr($_SERVER["HTTP_USER_AGENT"], 0, 250) : "API"]);
    }
}

debug("API: Executing API call");
echo execute_api_call($query, $pretty);
debug("API: finished execute_api_call();");

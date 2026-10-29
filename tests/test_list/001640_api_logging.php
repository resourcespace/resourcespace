<?php

command_line_only();

include_once dirname(__DIR__, 2) . '/include/api_functions.php';

// ---------------------------------------------------------------------------------------------------------------------
// Set up a temporary HTTP endpoint.
//
// The normal test runner uses rs_test_db in the CLI process, but a separate HTTP request (eg. to the api) would
// otherwise load the database and API scramble key from include/config.php.
//
// This temporary endpoint defines RS_TEST_MODE and passes the test database/API key configuration through to boot.php,
// then includes the real API entry point. This means the test exercises the genuine API HTTP/authentication path while
// still operating against the isolated test database.
//
// Tests will not only test the api logging functionality, but the API flow / Authentication itself in general.
// ---------------------------------------------------------------------------------------------------------------------

$test_endpoint_filename = 'test_api_endpoint.php';
$test_endpoint_path = get_temp_dir(false) . "/{$test_endpoint_filename}";
$test_endpoint_url = get_temp_dir(true) . "/{$test_endpoint_filename}";

$test_endpoint_content = <<<EOT
<?php

define("RS_TEST_MODE", 1);
\$rs_test_mysql_db = "rs_test_db";
\$rs_test_api_scramble_key = "{$api_scramble_key}";

\$api_dir = dirname(__DIR__, 3) . '/api';
chdir(\$api_dir);
include \$api_dir . '/index.php';
EOT;

if (!file_put_contents($test_endpoint_path, $test_endpoint_content)) {
    echo "[ENV] Unable to create API test endpoint - ";
    return false;
}

// ---------------------------------------------------------------------------------------------------------------------
// Helper: make a real HTTP API request.
//
// Builds the exact query string to be sent, signs it using the supplied API key, then calls the temporary endpoint.
// A signature override can be supplied to deliberately test invalid authentication.
// ---------------------------------------------------------------------------------------------------------------------

$api_request = function (
    string $username,
    string $api_key,
    string $function,
    array $params = [],
    ?string $sign_override = null
) use ($test_endpoint_url): array {
    $query = http_build_query(
        array_merge(
            [
                "user" => $username,
                "function" => $function,
            ],
            $params
        )
    );

    $sign = $sign_override ?? hash("sha256", $api_key . $query);

    $ch = curl_init("{$test_endpoint_url}?{$query}&sign={$sign}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    return [
        "status" => $status,
        "response" => $response,
        "error" => $error,
    ];
};

// ---------------------------------------------------------------------------------------------------------------------
// Helper: check the latest API log entry for a username.
//
// The tests are to verify that API requests are logged, so checking the latest entry lets each test confirm the exact
// function which was most recently attempted by that user.
// ---------------------------------------------------------------------------------------------------------------------

$api_log_matches = function (string $username, string $function): bool {
    $log = ps_query(
        "SELECT `user`, `function`
           FROM api_log
          WHERE `user` = ?
          ORDER BY ref DESC
          LIMIT 1",
        ["s", $username]
    );

    return count($log) === 1
        && $log[0]["user"] === $username
        && $log[0]["function"] === $function;
};

// Keep track of any resource created by the test so it can be removed even if a later assertion fails.
$resource_ref = null;

try {
    // -----------------------------------------------------------------------------------------------------------------
    // Set up API user in the test database - user group 3 (Super admin) to ensure no permission errors in the tests.
    // -----------------------------------------------------------------------------------------------------------------

    $api_username = "api_test_" . test_generate_random_ID(4);
    $api_user_ref = new_user($api_username, 3);
    $api_key = get_api_key($api_user_ref);

    // -----------------------------------------------------------------------------------------------------------------
    // Test 1: valid username with an invalid API signature.
    //
    // Authentication must fail with HTTP 401, but the attempted request must still be written to api_log because the
    // logging occurs before signature validation.
    // -----------------------------------------------------------------------------------------------------------------

    $request = $api_request(
        $api_username,
        $api_key,
        "get_resource_types",
        [],
        str_repeat("0", 64)
    );

    if ($request["response"] === false || $request["status"] !== 401) {
        echo "Invalid API signature did not return HTTP 401 - ";
        return false;
    }

    if (!$api_log_matches($api_username, "get_resource_types")) {
        echo "Invalid API signature attempt was not logged - ";
        return false;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Test 2: invalid/non-existent username.
    //
    // The request must fail authentication with HTTP 401 but the attempted username and API function must still be
    // recorded in api_log.
    // -----------------------------------------------------------------------------------------------------------------

    $invalid_username = "invalid_api_user_" . test_generate_random_ID(4);

    $request = $api_request(
        $invalid_username,
        str_repeat("x", 64),
        "get_resource_types"
    );

    if ($request["response"] === false || $request["status"] !== 401) {
        echo "Invalid API username did not return HTTP 401 - ";
        return false;
    }

    if (!$api_log_matches($invalid_username, "get_resource_types")) {
        echo "Invalid API username attempt was not logged - ";
        return false;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Test 3: create a resource through the API.
    //
    // Create a resource in archive state 0 so the subsequent API search (defaults to archive state 0) can find it.
    // -----------------------------------------------------------------------------------------------------------------

    $request = $api_request(
        $api_username,
        $api_key,
        "create_resource",
        [
            "resource_type" => 1,
            "archive" => 0,
        ]
    );

    if ($request["response"] === false || $request["status"] !== 200) {
        echo "API create_resource request failed. HTTP {$request['status']}: {$request['error']} Response: {$request['response']} - ";
        return false;
    }

    $resource_ref = json_decode($request["response"], true);

    if (!is_int($resource_ref) || get_resource_data($resource_ref) === false) {
        echo "API create_resource did not return a valid resource - ";
        return false;
    }

    if (!$api_log_matches($api_username, "create_resource")) {
        echo "API create_resource request was not logged correctly - ";
        return false;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Test 4: search for the newly created resource through the API.
    //
    // This gives us a second genuine API request and confirms that the API can return data created by the previous
    // request. The latest API log entry should now be do_search.
    // -----------------------------------------------------------------------------------------------------------------

    $request = $api_request(
        $api_username,
        $api_key,
        "do_search",
        // Explicit ID lookup avoids dependence on the HTTP process's numeric-search configuration.
        ["search" => '!resource' . $resource_ref]
    );

    if ($request["response"] === false || $request["status"] !== 200) {
        echo "API do_search request failed. HTTP {$request['status']}: {$request['error']} Response: {$request['response']} - ";
        return false;
    }

    $search_results = json_decode($request["response"], true);

    if (
        !is_array($search_results)
        || !in_array($resource_ref, array_column($search_results, "ref"))
    ) {
        echo "API do_search did not return the newly created resource - ";
        return false;
    }

    if (!$api_log_matches($api_username, "do_search")) {
        echo "API do_search request was not logged correctly - ";
        return false;
    }

    return true;

} finally {
    // -----------------------------------------------------------------------------------------------------------------
    // Tear down.
    //
    // Clean up Temporary test data/files (temporary HTTP endpoint and created resource record)
    // -----------------------------------------------------------------------------------------------------------------

    if (is_int($resource_ref)) {
        delete_resource($resource_ref);
    }

    try_unlink($test_endpoint_path);
}

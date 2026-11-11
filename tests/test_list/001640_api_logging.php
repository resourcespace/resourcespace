<?php

command_line_only();

include_once dirname(__DIR__, 2) . '/include/api_functions.php';

// Test query preparation independently of API authentication, dispatch and HTTP setup.
$prepare_query_use_cases = [
    [
        'name' => 'Empty query',
        'input' => '',
        'expected' => '',
    ],
    [
        'name' => 'Empty query components retain their separators',
        'input' => '&function=get_resource_data&&param1=1&',
        'expected' => '&function=get_resource_data&&param1=1&',
    ],
    [
        'name' => 'Argument order and original encoding are preserved',
        'input' => 'param2=a+b%20c%2bd%2F&funct%69on=get_resource_data&param1=%26%3D',
        'expected' => 'param2=a+b%20c%2bd%2F&funct%69on=get_resource_data&param1=%26%3D',
    ],
    [
        'name' => 'Empty and valueless arguments are preserved',
        'input' => 'param1=&param2&param3=0',
        'expected' => 'param1=&param2&param3=0',
    ],
    [
        'name' => 'Transport fields are removed at the start, middle and end',
        'input' => 'sign=hash&user=test&authmode=userkey&function=get_resource_data&pretty=true&param1=1&ajax=true',
        'expected' => 'user=test&function=get_resource_data&param1=1',
    ],
    [
        'name' => 'Query containing only transport fields becomes empty',
        'input' => 'ajax=true&sign=hash&pretty=true&authmode=sessionkey',
        'expected' => '',
    ],
    [
        'name' => 'Empty and valueless transport fields are removed',
        'input' => 'sign=&authmode&pretty=&ajax&param1=1',
        'expected' => 'param1=1',
    ],
    [
        'name' => 'Encoded transport names are recognised',
        'input' => 's%69gn=hash&auth%6Dode=userkey&pr%65tty=true&a%6Aax=true&param1=1',
        'expected' => 'param1=1',
    ],
    [
        'name' => 'Removing transport fields preserves empty query components',
        'input' => '&sign=hash&&param1=1&ajax=true&',
        'expected' => '&&param1=1&',
    ],
    [
        'name' => 'Transport-like text and equals signs in values are preserved',
        'input' => 'param1=sign=value%26pretty%3Dtrue%2Bajax%3D1&param2=authmode=userkey',
        'expected' => 'param1=sign=value%26pretty%3Dtrue%2Bajax%3D1&param2=authmode=userkey',
    ],
    [
        'name' => 'Transport names are case sensitive and require an exact match',
        'input' => 'Sign=hash&AJAX=true&signature=hash&pretty_print=true',
        'expected' => 'Sign=hash&AJAX=true&signature=hash&pretty_print=true',
    ],
    [
        'name' => 'Transport names within ordinary array arguments are preserved',
        'input' => 'data[sign]=hash&data[authmode]=userkey&data[pretty]=true&data[ajax]=true',
        'expected' => 'data[sign]=hash&data[authmode]=userkey&data[pretty]=true&data[ajax]=true',
    ],
    [
        'name' => 'Distinct associative array entries',
        'input' => 'function=put_resource_data&data[access]=1&data[archive]=0',
        'expected' => 'function=put_resource_data&data[access]=1&data[archive]=0',
    ],
    [
        'name' => 'Appended array entries',
        'input' => 'function=toggle_active_state_for_nodes&refs[]=1&refs[]=2',
        'expected' => 'function=toggle_active_state_for_nodes&refs[]=1&refs[]=2',
    ],
    [
        'name' => 'Interleaved nested array entries',
        'input' => 'data[a][]=1&data[b]=2&data[a][]=3',
        'expected' => 'data[a][]=1&data[b]=2&data[a][]=3',
    ],
    [
        'name' => 'Encoded array brackets and distinct nested keys',
        'input' => 'data%5Ba%5D%5Bb%5D=1&data%5Ba%5D%5Bc%5D=2',
        'expected' => 'data%5Ba%5D%5Bb%5D=1&data%5Ba%5D%5Bc%5D=2',
    ],
    [
        'name' => 'Appended entry follows the highest explicit numeric index',
        'input' => 'refs[2]=1&refs[]=2&refs[0]=3',
        'expected' => 'refs[2]=1&refs[]=2&refs[0]=3',
    ],
    [
        'name' => 'Each appended nested array is a distinct entry',
        'input' => 'data[][name]=first&data[][name]=second',
        'expected' => 'data[][name]=first&data[][name]=second',
    ],
    [
        'name' => 'Dots and spaces within array keys remain distinct',
        'input' => 'data[a.b]=1&data[a+b]=2&data[a_b]=3',
        'expected' => 'data[a.b]=1&data[a+b]=2&data[a_b]=3',
    ],
    [
        'name' => 'Duplicate scalar values are rejected even when identical',
        'input' => 'param1=1&param1=1',
        'expected' => false,
    ],
    [
        'name' => 'Duplicate scalar names separated by a transport field',
        'input' => 'param1=1&ajax=true&param1=2',
        'expected' => false,
    ],
    [
        'name' => 'Encoded and literal parameter names collide',
        'input' => 'param1=1&param%31=2',
        'expected' => false,
    ],
    [
        'name' => 'Dots in root names normalise to underscores',
        'input' => 'param.1=1&param_1=2',
        'expected' => false,
    ],
    [
        'name' => 'Plus signs in root names normalise to underscores',
        'input' => 'param+1=1&param_1=2',
        'expected' => false,
    ],
    [
        'name' => 'Encoded spaces in root names normalise to underscores',
        'input' => 'param%201=1&param_1=2',
        'expected' => false,
    ],
    [
        'name' => 'Scalar followed by array',
        'input' => 'data=1&data[a]=2',
        'expected' => false,
    ],
    [
        'name' => 'Array followed by scalar',
        'input' => 'data[a]=1&data=2',
        'expected' => false,
    ],
    [
        'name' => 'Duplicate associative array key',
        'input' => 'data[access]=0&data[access]=1',
        'expected' => false,
    ],
    [
        'name' => 'Nested scalar followed by array',
        'input' => 'data[a]=1&data[a][b]=2',
        'expected' => false,
    ],
    [
        'name' => 'Nested array followed by scalar',
        'input' => 'data[a][b]=1&data[a]=2',
        'expected' => false,
    ],
    [
        'name' => 'Explicit index collides with appended entry',
        'input' => 'refs[]=1&refs[0]=2',
        'expected' => false,
    ],
    [
        'name' => 'Appended entry uses the next index after an explicit index',
        'input' => 'refs[2]=1&refs[]=2&refs[3]=3',
        'expected' => false,
    ],
    [
        'name' => 'Encoded and literal array keys collide',
        'input' => 'data[a]=1&data%5B%61%5D=2',
        'expected' => false,
    ],
    [
        'name' => 'Missing parameter name',
        'input' => '=value',
        'expected' => false,
    ],
    [
        'name' => 'Missing array root',
        'input' => '[key]=value',
        'expected' => false,
    ],
    [
        'name' => 'Unclosed array bracket',
        'input' => 'data[key=value',
        'expected' => false,
    ],
    [
        'name' => 'Unmatched closing bracket',
        'input' => 'data]=value',
        'expected' => false,
    ],
    [
        'name' => 'Trailing text after array key',
        'input' => 'data[key]suffix=value',
        'expected' => false,
    ],
    [
        'name' => 'Nested opening bracket without closing the parent',
        'input' => 'data[[key]]=value',
        'expected' => false,
    ],
    [
        'name' => 'Null byte in parameter root',
        'input' => 'param%00suffix=value',
        'expected' => false,
    ],
    [
        'name' => 'Null byte in array key',
        'input' => 'data[key%00suffix]=value',
        'expected' => false,
    ],
];

// Reserved fields must be unique scalars, including fields removed from the query.
foreach (['sign', 'authmode', 'pretty', 'ajax', 'function', 'user'] as $reserved_field) {
    $prepare_query_use_cases[] = [
        'name' => "Duplicate reserved field: {$reserved_field}",
        'input' => "{$reserved_field}=first&{$reserved_field}=second",
        'expected' => false,
    ];
    $prepare_query_use_cases[] = [
        'name' => "Appended array in reserved field: {$reserved_field}",
        'input' => "{$reserved_field}[]=value",
        'expected' => false,
    ];
    $prepare_query_use_cases[] = [
        'name' => "Associative array in reserved field: {$reserved_field}",
        'input' => "{$reserved_field}[key]=value",
        'expected' => false,
    ];
}

foreach ($prepare_query_use_cases as $use_case) {
    $prepared_query = prepare_api_query($use_case['input']);
    if ($prepared_query !== $use_case['expected']) {
        echo "API query preparation use case: {$use_case['name']} - ";
        test_log('- expected: ' . var_export($use_case['expected'], true));
        test_log('- result: ' . var_export($prepared_query, true));
        return false;
    }
}
unset($prepare_query_use_cases, $reserved_field, $use_case, $prepared_query);

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

$test_directory = sys_get_temp_dir() . '/rs_api_logging_' . test_generate_random_ID(8);
mkdir($test_directory, 0700);
$test_endpoint_path = $test_directory . '/router.php';
$api_directory = var_export(dirname(__DIR__, 2) . '/api', true);
$fixture_key = var_export($api_scramble_key, true);

$test_endpoint_content = <<<EOT
<?php

define("RS_TEST_MODE", 1);
\$rs_test_mysql_db = "rs_test_db";
\$rs_test_api_scramble_key = {$fixture_key};

\$api_dir = {$api_directory};
chdir(\$api_dir);
include \$api_dir . '/index.php';
EOT;

if (!file_put_contents($test_endpoint_path, $test_endpoint_content)) {
    rcRmdir($test_directory);
    echo "[ENV] Unable to create API test endpoint - ";
    return false;
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    rcRmdir($test_directory);
    echo '[ENV] Unable to reserve API HTTP port - ';
    return false;
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$test_endpoint_url = 'http://' . $address;
$server_pipes = [];
$server = proc_open(
    [PHP_BINARY, '-S', $address, $test_endpoint_path],
    [['pipe', 'r'], ['file', $test_directory . '/server.log', 'a'], ['file', $test_directory . '/server.log', 'a']],
    $server_pipes
);

// ---------------------------------------------------------------------------------------------------------------------
// Helper: make a real HTTP API request.
//
// Builds the exact query string to be sent, signs it using the supplied API key, then calls the temporary endpoint.
// Overrides allow tampered raw queries to be tested through GET and the documented POST envelope.
// ---------------------------------------------------------------------------------------------------------------------

$api_request = function (
    string $username,
    string $api_key,
    string $function,
    array $params = [],
    ?string $sign_override = null,
    ?string $query_override = null,
    bool $post = false
) use ($test_endpoint_url): array {
    $query = $query_override ?? http_build_query(
        array_merge(
            [
                "user" => $username,
                "function" => $function,
            ],
            $params
        )
    );

    $sign = $sign_override ?? hash("sha256", $api_key . $query);

    $ch = curl_init($post ? $test_endpoint_url : "{$test_endpoint_url}?{$query}&sign={$sign}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'user' => $username,
            'sign' => $sign,
            'query' => $query . '&sign=' . $sign,
        ]));
    }

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
    if (!is_resource($server)) {
        echo '[ENV] Unable to start API HTTP server - ';
        return false;
    }
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = $api_request('api_server_probe', '', 'get_resource_types');
        if ($probe['status'] !== 0) {
            $ready = true;
            break;
        }
        usleep(20000);
    }
    if (!$ready) {
        echo '[ENV] API HTTP server did not become ready - ';
        return false;
    }

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
        echo "Invalid API signature did not return HTTP 401 ({$request['status']}: {$request['error']}) - ";
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

    // Sign and execute the same arguments, including those following unsigned transport fields.
    $prefix = http_build_query(['user' => $api_username, 'function' => 'get_resource_data']);
    foreach ([false, true] as $post) {
        foreach (['param1', 'resource'] as $argument) {
            $query = $prefix . '&' . $argument . '=' . $resource_ref;
            $signature = hash('sha256', $api_key . $query);
            foreach (
                [$query, $query . '&ajax=true&pretty=true&authmode=userkey',
                'ajax=true&' . $query, $prefix . '&ajax=true&' . $argument . '=' . $resource_ref] as $valid_query
            ) {
                $request = $api_request($api_username, $api_key, '', [], $signature, $valid_query, $post);
                $result = json_decode($request['response'], true);
                if ($request['status'] !== 200 || (int) ($result['ref'] ?? 0) !== $resource_ref) {
                    echo 'Valid signed API arguments or transport fields were changed - ';
                    return false;
                }
            }

            // An unsigned new argument must not acquire the signature of the prefix before ajax.
            $request = $api_request(
                $api_username,
                $api_key,
                '',
                [],
                hash('sha256', $api_key . $prefix),
                $prefix . '&ajax=true&' . $argument . '=' . $resource_ref,
                $post
            );
            if ($request['status'] !== 401) {
                echo 'API accepted an unsigned argument after ajax - ';
                return false;
            }

            foreach (
                [$query . '&ajax=true&' . $argument . '=0',
                $query . '&ajax=true&' . rawurlencode($argument . '[]') . '=0',
                $query . '&ajax=true&function=login',
                $query . '&funct%69on=get_resource_data',
                $query . '&function%5B%5D=login',
                $query . '&pretty=true&pretty=false'] as $invalid_query
            ) {
                // Even a correctly signed ambiguous query must be rejected before dispatch.
                $request = $api_request(
                    $api_username,
                    $api_key,
                    '',
                    [],
                    hash('sha256', $api_key . $invalid_query),
                    $invalid_query,
                    $post
                );
                if ($request['status'] !== 400) {
                    echo 'API did not reject ambiguous query parameters - ';
                    return false;
                }
            }
        }

        // Exercise an array argument through the real binding and verify its stored result.
        $access = $post ? 0 : 1;
        $query = http_build_query([
            'user' => $api_username,
            'function' => 'put_resource_data',
            'resource' => $resource_ref,
            'data' => ['access' => $access, 'archive' => 0],
        ]);
        $request = $api_request($api_username, $api_key, '', [], null, $query, $post);
        if (
            $request['status'] !== 200
            || json_decode($request['response'], true) !== true
            || (int) get_resource_data($resource_ref, false)['access'] !== $access
        ) {
            echo 'API array argument was not applied correctly - ';
            return false;
        }
    }

    foreach (
        ['function=get_resource_data&param1=1&ajax=true&param1=2',
        'function=put_resource_data&data[access]=0&data[access]=1',
        'function=put_resource_data&data[a]=1&data[a][b]=2',
        'function=put_resource_data&data[a][b]=1&data[a]=2',
        'function=toggle_active_state_for_nodes&refs[]=1&refs[0]=2',
        'function=get_resource_data&param.1=1&param_1=2'] as $query
    ) {
        if (
            check_api_key($api_username, $query, hash('sha256', $api_key . $query))
            || execute_api_call($query) !== false
        ) {
            echo 'Direct API authentication or dispatch accepted ambiguous arguments - ';
            return false;
        }
    }
    foreach (['userkey' => $api_key, 'sessionkey' => get_session_api_key($api_user_ref)] as $mode => $signing_key) {
        $query = $prefix . '&resource=' . $resource_ref;
        $signature = hash('sha256', $signing_key . $query);
        if (
            !check_api_key($api_username, $query . '&ajax=true', $signature, $mode)
            || check_api_key(
                $api_username,
                $prefix . '&ajax=true&resource=' . $resource_ref,
                hash('sha256', $signing_key . $prefix),
                $mode
            )
        ) {
            echo 'API signature coverage changed between authentication modes - ';
            return false;
        }
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

    if (is_resource($server)) {
        proc_terminate($server);
        foreach ($server_pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($server);
    }
    rcRmdir($test_directory);
}

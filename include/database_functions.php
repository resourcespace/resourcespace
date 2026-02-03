<?php

/**
 * database_functions.php
 *
 * Functions required for interacting with the database.
 */

/**
 * Simple class to use when required to obtain/build SQL (sub) statements from various functions.
 *
 * @internal
 */
final class PreparedStatementQuery
{
    /**
     * @var string $sql SQL prepared (sub) statement with placeholders in place
     */
    public $sql;

    /**
     * @var array $parameters Bind parameters
     */
    public $parameters;

    /**
     * Create a new PreparedStatementQuery
     *
     * @param string $sql        SQL prepared (sub) statement with placeholders in place
     * @param array  $parameters Bind parameters
     */
    public function __construct(string $sql = '', array $parameters = [])
    {
        $this->sql = $sql;
        $this->parameters = $parameters;
    }
}

/**
 * Centralised error handler. Display friendly error messages.
 *
 * @param  integer $errno
 * @param  string $errstr
 * @param  string $errfile
 * @param  integer $errline
 * @return void
 */
function errorhandler($errno, $errstr, $errfile, $errline)
{
    global $baseurl, $pagename, $show_report_bug_link, $show_error_messages,$show_detailed_errors, $use_error_exception,$log_error_messages_url, $username, $plugins;

    $suppress = !(error_reporting() && ($errno & $GLOBALS["config_error_reporting"]));

    if (strlen($errstr) > 1024) {
        // MySQL errors may be very long. Trim the middle
        $errstr = mb_substr($errstr, 0, 500) . "...(TRUNCATED TEXT)..." . mb_substr($errstr, -500);
    }
    $error_note = "Sorry, an error has occurred. ";
    $error_info  = "$errfile line $errline: $errstr";

    if (!$suppress) {
        if ($use_error_exception === true) {
            throw new ErrorException($error_info, 0, E_ALL, $errfile, $errline);
        } elseif (substr(PHP_SAPI, 0, 3) == 'cli') {
            // Always show errors when running on the command line.
            echo "\n\n\n" . $error_note;
            echo $error_info . "\n\n";
            // Dump additional trace information to help with diagnosis.
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            echo PHP_EOL;
        } elseif (defined("API_CALL")) {
            // If an API call, return a standardised error format.
            $response["error"] = true;
            $response["error_note"] = $error_note;
            if ($show_detailed_errors) {
                $response["error_info"] = $error_info;
            }
            echo json_encode($response);
        } else {
            ?>
            </select></table></table></table>
            <div style="box-shadow: 3px 3px 20px #666;font-family:ubuntu,arial,helvetica,sans-serif;position:absolute;top:150px;left:150px; background-color:white;width:450px;padding:20px;font-size:15px;color:#fff;border-radius:5px;z-index:9999;">
                <div style="font-size:30px;background-color:red;border-radius:50%;min-width:35px;float:left;text-align:center;font-weight:bold;">!</div>
                <span style="font-size:30px;color:black;padding:14px;"><?php echo $error_note; ?></span>
                <p style="font-size:14px;color:black;margin-top:20px;">Please <a href="#" onClick="history.go(-1)">go back</a> and try something else.</p>
                <?php
                if ($show_error_messages) {
                    if (checkperm('a')) {
                        //Only show check installtion if you have permissions for that page.
                        ?>
                        <p style="font-size:14px;color:black;">You can <a href="<?php echo $baseurl?>/pages/check.php">check</a> your installation configuration.</p>
                        <?php
                    } ?>
                    <hr style="margin-top:20px;">
                    <?php
                    if ($show_detailed_errors) { ?>
                        <p style="font-size:11px;color:black;"><?php debug_print_backtrace(); echo  PHP_EOL . PHP_EOL . escape($error_info) . PHP_EOL . PHP_EOL  ?></p>
                        <?php
                    }
                } ?>
            </div>
            <?php
        }
    }

    // Optionally log errors to a central server.
    if (isset($log_error_messages_url)) {
        $exception = new ErrorException($error_info, 0, E_ALL, $errfile, $errline);
        // Remove the actual errorhandler from the stack trace. This will remove other global data which otherwise could leak sensitive information
        $backtrace = json_encode(
            array_filter($exception->getTrace(), function (array $val) {
                return $val["function"] !== "errorhandler";
            }),
            JSON_PRETTY_PRINT
        );

        // Prepare the post data.
        $postdata = http_build_query(array(
            'baseurl' => $baseurl,
            'referer' => (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            'pagename' => (isset($pagename) ? $pagename : ''),
            'error' => $error_info,
            'username' => (isset($username) ? $username : ''),
            'ip' => (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : ''),
            'user_agent' => (isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : ''),
            'plugins' => (isset($plugins) ? join(",", $plugins) : '?'),
            'query_string' => (isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : ''),
            'backtrace' => $backtrace
            ));

        // Create a stream context with a low timeout.
        $ctx = stream_context_create(array('http' => array('method' => 'POST', 'timeout' => 2, 'header' => "Content-type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postdata),'content' => $postdata)));

        // Attempt to POST but suppress errors; we don't want any errors here and the attempt must be aborted quickly.
        echo @file_get_contents($log_error_messages_url, 0, $ctx);
    }

    hook('after_error_handler', '', array($errno, $errstr, $errfile, $errline));
    if ($suppress) {
        return;
    }
    exit();
}

/**
* Check if ResourceSpace has been configured to run with differnt users (read-write and/or read-only)
*
* @return boolean
*/
function db_use_multiple_connection_modes()
{
    if (
        isset($GLOBALS["read_only_db_username"]) && isset($GLOBALS["read_only_db_password"])
        && is_string($GLOBALS["read_only_db_username"]) && is_string($GLOBALS["read_only_db_password"])
        && trim($GLOBALS["read_only_db_username"]) !== ""
    ) {
        return true;
    }

    return false;
}

/**
* Used to force the database connection mode before running a particular SQL query
*
* NOTE: this will generate a global variable that can be used to determine which mode is currently set.
*
* IMPORTANT: It is the responsibility of each function to clear the current db mode once it finished running the query
* as the variable is not meant to persist between queries.
*
* @param string $name The name of the connection mode
*
* @return void
*/
function db_set_connection_mode(string $name)
{
    if (db_use_multiple_connection_modes() && isset($GLOBALS['db'][$name]) && !isset($GLOBALS['sql_transaction_in_progress'])) {
        $GLOBALS['db_connection_mode'] = $name;
    }
}

/**
* Return the current DB connection mode
*
* @return string
*/
function db_get_connection_mode()
{
    if (db_use_multiple_connection_modes() && isset($GLOBALS['db_connection_mode'])) {
        return trim($GLOBALS['db_connection_mode']);
    }

    return '';
}

/**
* Clear the current DB connection mode that is in use to override the current SQL queries. @see db_set_connection_mode()
* for more details.
*
* @return void
*/
function db_clear_connection_mode()
{
    if (db_use_multiple_connection_modes() && isset($GLOBALS['db_connection_mode']) && !isset($GLOBALS['sql_transaction_in_progress'])) {
        unset($GLOBALS['db_connection_mode']);
    }
}

/**
* @var  array  Holds database connections for different users (e.g read-write and/or read-only). NULL if no connection
*              has been registered.
*/
$db = null;
/**
 * Connect to the database using the configured settings.
 *
 * @return void
 */
function sql_connect()
{
    global $db,$mysql_server,$mysql_username,$mysql_password,$mysql_db,$mysql_charset, $mysql_sort_buffer_size,
           $mysql_server_port, $use_mysqli_ssl, $mysqli_ssl_server_cert, $mysqli_ssl_ca_cert, $mysqli_ssl_ca_path,
           $mysqli_ssl_verify_server_cert;

    $init_connection = function (
        $mysql_server,
        $mysql_server_port,
        $mysql_username,
        $mysql_password,
        $mysql_db
    ) use (
        $mysql_charset,
        $use_mysqli_ssl,
        $mysqli_ssl_server_cert,
        $mysqli_ssl_ca_cert,
        $mysqli_ssl_ca_path,
        $mysqli_ssl_verify_server_cert
) {
        $db_connection = mysqli_init();

        if ($use_mysqli_ssl) {
            mysqli_ssl_set($db_connection, null, $mysqli_ssl_server_cert ?? null, $mysqli_ssl_ca_cert ?? null, $mysqli_ssl_ca_path ?? null, null);
        }
        $connectflags = $mysqli_ssl_verify_server_cert ? 0 : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        mysqli_real_connect($db_connection, $mysql_server, $mysql_username, $mysql_password, $mysql_db, $mysql_server_port, null, $connectflags);
        if (isset($mysql_charset) && is_string($mysql_charset) && trim($mysql_charset) !== "") {
            mysqli_set_charset($db_connection, $mysql_charset);
        }

        return $db_connection;
    };

    $db["read_write"] = $init_connection($mysql_server, $mysql_server_port, $mysql_username, $mysql_password, $mysql_db);

    if (db_use_multiple_connection_modes()) {
        $db["read_only"] = $init_connection(
            $mysql_server,
            $mysql_server_port,
            $GLOBALS["read_only_db_username"],
            $GLOBALS["read_only_db_password"],
            $mysql_db
        );
    }

    foreach ($db as $db_connection_mode => $db_connection) {
        db_set_connection_mode($db_connection_mode);
        if (
            is_int($mysql_sort_buffer_size)
            && $mysql_sort_buffer_size > 32768
            && $mysql_sort_buffer_size < 4294967295
        ) {
            ps_query('SET SESSION sort_buffer_size = ?', ['i', $mysql_sort_buffer_size], '', -1, false);
        }

        db_set_connection_mode($db_connection_mode);
        db_set_connection_mode($db_connection_mode);
        $sql_mode_current = ps_query('select @@SESSION.sql_mode');
        $sql_mode_string = implode(" ", $sql_mode_current[0]);
        $sql_mode_array_new = array_diff(explode(",", $sql_mode_string), array("ONLY_FULL_GROUP_BY", "NO_ZERO_IN_DATE", "NO_ZERO_DATE"));
        $sql_mode_string_new = implode(",", $sql_mode_array_new);

        db_set_connection_mode($db_connection_mode);
        ps_query("SET SESSION sql_mode = '$sql_mode_string_new'", [], '', -1, false, 0);
    }

    db_clear_connection_mode();
}

/**
* Indicate that from now on we want to group together DML statements into one transaction.
*
* @param string $name Savepoint name for the transaction.
*
* @return boolean Returns TRUE on success or FALSE on failure.
*/
function db_begin_transaction($name)
{
    global $db;

    if (!is_string($name)) {
        $name = null;
    }

    if (function_exists('mysqli_begin_transaction')) {
        db_set_connection_mode('read_write');
        $GLOBALS['sql_transaction_in_progress'] = true;

        debug("SQL: begin transaction '{$name}'");
        return mysqli_begin_transaction($db["read_write"], 0, $name);
    }

    return false;
}


/**
* Tell the database to commit the current transaction.
*
* @param string $name Savepoint name for the transaction.
*
* @return boolean Returns TRUE on success or FALSE on failure.
*/
function db_end_transaction($name)
{
    global $db;

    if (!is_string($name)) {
        $name = null;
    }

    if (function_exists('mysqli_commit')) {
        unset($GLOBALS['sql_transaction_in_progress']);
        db_clear_connection_mode();

        debug("SQL: commit transaction '{$name}'");
        return mysqli_commit($db["read_write"], 0, $name);
    }

    return false;
}

/**
* Tell the database to rollback the current transaction.
*
* @param string $name Savepoint name for the transaction.
*
* @return boolean Returns TRUE on success or FALSE on failure.
*/
function db_rollback_transaction($name)
{
    global $db;

    if (!is_string($name)) {
        $name = null;
    }

    if (function_exists('mysqli_rollback')) {
        unset($GLOBALS['sql_transaction_in_progress']);
        db_clear_connection_mode();

        debug("SQL: rollback transaction '{$name}'");
        return mysqli_rollback($db["read_write"], 0, $name);
    }

    return false;
}

/**
 * Execute a prepared statement and return the results as an array.
 *
 * @param  string $sql                      The SQL to execute
 * @param  array $parameters               An array of parameters used in the SQL in the order: type, value, type, value... and so on. Types are as follows: i - integer, d - double, s - string, b - BLOB. Example: array("s","This is the first SQL parameter and is a string","d",3.14)
 * @param  string $cache                        Disk based caching - cache the results on disk, if a cache group is specified. The group allows selected parts of the cache to be cleared by certain operations, for example clearing all cached site content whenever site text is edited.
 * @param  integer $fetchrows                   set we don't have to loop through all the returned rows. We just fetch $fetchrows row but pad the array to the full result set size with empty values.
 * @param  boolean $dbstruct                    Set to false to prevent the dbstruct being checked on an error - only set by operations doing exactly that to prevent an infinite loop
 * @param  integer $logthis                 No longer used
 * @param  boolean $reconnect
 * @param  mixed $fetch_specific_columns
 * @return array
 */
function ps_query($sql, array $parameters = array(), $cache = "", $fetchrows = -1, $dbstruct = true, $logthis = 2, $reconnect = true, $fetch_specific_columns = false)
{
    global $db, $config_show_performance_footer, $debug_log, $debug_log_override,
    $storagedir, $scramble_key, $query_cache_expires_minutes, $query_cache_enabled,
    $query_cache_already_completed_this_time,$prepared_statement_cache;

    $error = null;

    // Check cache for this query
    $cache_write = false;
    $serialised_query = $sql . ":" . serialize($parameters); // Serialised query needed to differentiate between different queries.
    // Caching active and this cache group has not been cleared by a previous operation this run
    if (
        $query_cache_enabled
        && $cache !== ""
        && (!isset($query_cache_already_completed_this_time) || !in_array($cache, $query_cache_already_completed_this_time))
    ) {
        $cache_write = true;
        $cache_location = get_query_cache_location();
        $cache_file = $cache_location . "/" . $cache . "_" . md5($serialised_query) . "_" . md5($scramble_key . $serialised_query) . ".json"; // Scrambled path to cache
        if (file_exists($cache_file)) {
            $GLOBALS["use_error_exception"] = true;
            try {
                $cachedata = json_decode(file_get_contents($cache_file), true);
            } catch (Exception $e) {
                $cachedata = null;
                debug("ps_query(): " . $e->getMessage());
            }
            unset($GLOBALS["use_error_exception"]);
            if (
                !is_null($cachedata)  // JSON decode success
                && $sql == $cachedata["query"] // Query matches so not a (highly unlikely) hash collision
                && time() - $cachedata["time"] < (60 * $query_cache_expires_minutes)  // Less than 30 mins old?
            ) {
                    debug("[ps_query] returning cached data (source: {$cache_file})");
                    db_clear_connection_mode();
                    return $cachedata["results"];
            }
        }
    }

    if (!isset($debug_log_override)) {
        $original_con_mode = db_get_connection_mode();
        db_clear_connection_mode();
        check_debug_log_override();
        db_set_connection_mode($original_con_mode);
    }

    if ($config_show_performance_footer) {
        # Stats
        # Start measuring query time
        $time_start = microtime(true);
        global $querycount;
        $querycount++;
    }

    if ($debug_log || $debug_log_override) {
        debug("SQL: " . $sql . "  Parameters: " . json_encode($parameters));
    }
    if (trim($sql) == "") {
        debug("Error - empty SQL query passed");
        return [];
    }

    // Establish DB connection required for this query. Note that developers can force the use of read-only mode if
    // available using db_set_connection_mode(). An example use case for this can be reports.
    $db_connection_mode = 'read_write';
    $db_connection = $db['read_write'];
    if (
        db_use_multiple_connection_modes()
        && !isset($GLOBALS['sql_transaction_in_progress'])
        && (db_get_connection_mode() === 'read_only' || ($logthis == 2 && strtoupper(substr(trim($sql), 0, 6)) === 'SELECT'))
    ) {
        $db_connection_mode = 'read_only';
        $db_connection = $db['read_only'];
        db_clear_connection_mode();
    }

    if (count($parameters) > 0) {
        // Execute prepared statement
        if (!isset($prepared_statement_cache[$sql])) {
            if (!isset($prepared_statement_cache)) {
                $prepared_statement_cache = array();
            }

            $use_error_exception_cache = $GLOBALS['use_error_exception'] ?? false;
            try {
                $prepared_statement_cache[$sql] = $db_connection->prepare($sql);
            } catch (Throwable $t) {
                $prepared_statement_cache[$sql] = false;
                debug("Failed to prepare the database statement. {$t}");
            }
            $GLOBALS['use_error_exception'] = $use_error_exception_cache;

            if ($prepared_statement_cache[$sql] === false) {
                if ($dbstruct) {
                    // Clear out the cache for this query before running check_db_structs()
                    unset($prepared_statement_cache[$sql]);
                    db_clear_connection_mode();
                    check_db_structs();
                    db_set_connection_mode($db_connection_mode);
                    # Try again (no dbstruct this time to prevent an endless loop)
                    return ps_query($sql, $parameters, $cache, $fetchrows, false, $logthis, $reconnect, $fetch_specific_columns);
                }
                $error = "Bad prepared SQL statement: " . $sql . "  Parameters: " . json_encode($parameters) . " - " . $db_connection->error;

                // Get the details of the problematic query. It is useful to find the first call that was not
                // from this file so as to avoid CheckDBStruct() confusing matters
                $backtrace = debug_backtrace();
                foreach ($backtrace as $backtracedetail) {
                        $errorfile = $backtracedetail["file"];
                        $errorline = $backtracedetail["line"];
                    if ($backtracedetail["file"] != __FILE__) {
                        break;
                    }
                }
                errorhandler(E_ERROR, $error, $errorfile, $errorline);
                exit();
            }
        }
        $params_array = array();
        $types = "";
        for ($n = 0; $n < count($parameters); $n += 2) {
            $types .= $parameters[$n];
            if (!array_key_exists($n + 1, $parameters)) {
                trigger_error("Count of \$parameters array must be even (ensure types specified) for query: $sql" . print_r($parameters, true));
            }
            $params_array[] = $parameters[$n + 1];
        }

        if ($error == "") {
            // Results section
            $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
            $GLOBALS["use_error_exception"] = true;
            try {
                mysqli_stmt_bind_param($prepared_statement_cache[$sql], $types, ...$params_array); // splat operator
                mysqli_stmt_execute($prepared_statement_cache[$sql]);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            $GLOBALS["use_error_exception"] = $use_error_exception_cache;

            $error = $error ?? mysqli_stmt_error($prepared_statement_cache[$sql]);
        }

        if ($error == "") {
            // Results section

            // Buffering of result set
            $prepared_statement_cache[$sql]->store_result();

            // Fetch result set
            $metadata = $prepared_statement_cache[$sql]->result_metadata();
            if ($metadata === false) {
                // Did not return a result set, execution of an update/insert etc.
                $result = true;
            } else {
                // Bind results -> standard associative array
                $fields = $metadata->fetch_fields();
                $args = array();
                foreach ($fields as $field) {
                    $key = str_replace(' ', '_', $field->name);
                    $args[$key] = &$field->name;
                }
                call_user_func_array(array($prepared_statement_cache[$sql], "bind_result"), array_values($args));
                $result = array();
                $count = 0;
                while ($prepared_statement_cache[$sql]->fetch() && ($fetchrows == -1 || $count < $fetchrows)) { // Return requested no. of rows
                    $count++;
                    $result[] = array_map("copy_value", $args);
                }
                $prepared_statement_cache[$sql]->free_result();
            }
        }
    } else {
        $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
        $GLOBALS["use_error_exception"] = true;
        try {
            // No parameters, this cannot be executed as a prepared statement. Execute in the standard way.
            $result = $result_set = mysqli_query($db_connection, $sql);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $GLOBALS["use_error_exception"] = $use_error_exception_cache;
        $return_row_count = 0;
        $error = $error ?? mysqli_error($db_connection);
        if ($error == "" && $result_set instanceof mysqli_result) {
            $result = [];
            while (($fetchrows == -1 || $return_row_count < $fetchrows) && $result_row = mysqli_fetch_assoc($result_set)) {
                $return_row_count++;
                $result[] = $result_row;
            }
            mysqli_free_result($result_set);
        }
    }

    if ($config_show_performance_footer) {
        # Stats
        # Log performance data
        global $querytime,$querylog;

        $time_total = (microtime(true) - $time_start);
        if (isset($querylog[$sql])) {
            $querylog[$sql]['dupe'] = $querylog[$sql]['dupe'] + 1;
            $querylog[$sql]['time'] = $querylog[$sql]['time'] + $time_total;
        } else {
            $querylog[$sql]['dupe'] = 1;
            $querylog[$sql]['time'] = $time_total;
            $querylog[$sql]['params'] = $parameters;
        }
        $querytime += $time_total;
    }

    if ($error != "") {
        static $retries = [];
        $error_retry_idx = md5($error);
        $retries[$error_retry_idx] ??= 0;

        if ($error == "Server shutdown in progress") {
            echo "<span class=error>Sorry, but this query would return too many results. Please try refining your query by adding addition keywords or search parameters.<!-- " . escape($sql) . " --></span>";
        } elseif (substr($error, 0, 15) == "Too many tables") {
            echo "<span class=error>Sorry, but this query contained too many keywords. Please try refining your query by removing any surplus keywords or search parameters.<!-- " . escape($sql) . " --></span>";
        } elseif (strpos($error, "has gone away") !== false && $reconnect) {
            // SQL server connection has timed out or been killed. Try to reconnect and run query again.
            // Unset the cache for this no longer valid
            unset($prepared_statement_cache[$sql]);
            sql_connect();
            db_set_connection_mode($db_connection_mode);
            return ps_query($sql, $parameters, $cache, $fetchrows, $dbstruct, $logthis, false, $fetch_specific_columns);
        } elseif (
            (
                strpos($error, 'Deadlock found when trying to get lock') !== false
                || strpos($error, 'Lock wait timeout exceeded') !== false
            )
            && $retries[$error_retry_idx] <= SYSTEM_DATABASE_MAX_RETRIES
        ) {
            ++$retries[$error_retry_idx];
            return ps_query($sql, $parameters, $cache, $fetchrows, $dbstruct, $logthis, $reconnect, $fetch_specific_columns);
        } else {
            # Check that all database tables and columns exist using the files in the 'dbstruct' folder.
            if ($dbstruct) { # should we do this?
                db_clear_connection_mode();
                check_db_structs();
                db_set_connection_mode($db_connection_mode);

                # Try again (no dbstruct this time to prevent an endless loop)
                return ps_query($sql, $parameters, $cache, $fetchrows, false, $logthis, $reconnect, $fetch_specific_columns);
            }

            // Get the details of the problematic query. It is useful to find the first call that was not
            // from this file so as to avoid CheckDBStruct() confusing matters
            $backtrace = debug_backtrace();
            foreach ($backtrace as $backtracedetail) {
                    $errorfile = $backtracedetail["file"];
                    $errorline = $backtracedetail["line"];
                if ($backtracedetail["file"] != __FILE__) {
                    break;
                }
            }
            errorhandler(E_ERROR, $error, $errorfile, $errorline);
        }

        exit();
    } elseif ($result === true) {
        return array();     // no result set, (query was insert, update etc.) - simply return empty array.
    }

    if ($cache_write) {
        $cachedata = array();
        $cachedata["query"] = $sql;
        $cachedata["time"] = time();
        $cachedata["results"] = $result;

        $GLOBALS["use_error_exception"] = true;
        try {
            if (!file_exists($storagedir . "/tmp")) {
                mkdir($storagedir . "/tmp", 0777, true);
            }

            if (!file_exists($cache_location)) {
                mkdir($cache_location, 0777);
            }

            file_put_contents($cache_file, json_encode($cachedata));
        } catch (Exception $e) {
            debug("SQL_CACHE: {$e->getMessage()}");
        }
        unset($GLOBALS["use_error_exception"]);
    }

    if ($fetchrows == -1) {
        return $result;
    }

    /*
    COMMENTED - this should no longer be needed; it was added for search results however in that situation a separate count() query
    should be executed first.

    # If we haven't returned all the rows ($fetchrows isn't -1) then we need to fill the array so the count
    # is still correct (even though these rows won't be shown).
    if(count($result) < $query_returned_row_count)
        {
        // array_pad has a hardcoded limit of 1,692,439 elements. If we need to pad the results more than that, we do it in
        // 1,000,000 elements batches.
        while(count($result) < $query_returned_row_count)
            {
            $padding_required = $query_returned_row_count - count($result);
            $pad_by = ($padding_required > 1000000 ? 1000000 : $query_returned_row_count);
            $result = array_pad($result, $pad_by, 0);
            }
       }
    */

    return $result;
}

/**
* Copy value as value (flatten / no references)
*/
function copy_value($v)
{
    return $v;
}

/**
* Return a single value from a database query, or the default if no rows
*
* NOTE: The value returned must have the column name aliased to 'value'
*
* @uses ps_query()
*
* @param string $query      SQL query
* @param array  $parameters SQL parameters with types, as for ps_query()
* @param mixed  $default    Default value to return if no rows returned
* @param string $cache      Cache category (optional)
*
* @return string
*/
function ps_value($query, $parameters, $default, $cache = "")
{
    db_set_connection_mode("read_only");
    $result = ps_query($query, $parameters, $cache, -1, true, 0, true, false);

    if (count($result) == 0) {
        return $default;
    }

    return $result[0]["value"];
}

/**
* Like ps_value() but returns an array of all values found
*
* NOTE: The value returned must have the column name aliased to 'value'
*
* @uses ps_query()
*
* @param string $query      SQL query
* @param array  $parameters SQL parameters with types, as for ps_query()
* @param string  $cache      Cache category (optional)
*
* @return array
*/
function ps_array($query, $parameters = array(), $cache = "")
{
    $return = array();

    db_set_connection_mode("read_only");
    $result = ps_query($query, $parameters, $cache, -1, true, 0, true, false);

    for ($n = 0; $n < count($result); $n++) {
        $return[] = $result[$n]["value"];
    }

    return $return;
}

/**
 * Return the ID of the previously inserted row.
 *
 * @return integer
 */
function sql_insert_id()
{
    global $db;
    return mysqli_insert_id($db["read_write"]);
}

/**
 * Returns the location of the query cache files
 *
 * @return string
 */
function get_query_cache_location()
{
    global $storagedir,$tempdir;
    if (!is_null($tempdir)) {
        return $tempdir . "/querycache";
    } else {
        return $storagedir . "/tmp/querycache";
    }
}

/**
 * Clear all cached queries for cache group $cache
 *
 * If we've already done this on this page load, don't do it again as it will only add to the load in the case of batch operations.
 *
 * @param  string $cache
 * @return boolean
 */
function clear_query_cache($cache)
{
    global $query_cache_already_completed_this_time;
    if (!isset($query_cache_already_completed_this_time)) {
        $query_cache_already_completed_this_time = array();
    }
    if (in_array($cache, $query_cache_already_completed_this_time)) {
        return false;
    }

    $cache_location = get_query_cache_location();
    if (!file_exists($cache_location)) {
        return false;
    } // Cache has not been used yet.
    $cache_files = scandir($cache_location);

    foreach ($cache_files as $file) {
        if (
            substr($file, 0, strlen($cache) + 1) == $cache . "_"
            && file_exists($cache_location . "/" . $file)
        ) {
                try_unlink($cache_location . "/" . $file);
        }
    }

    $query_cache_already_completed_this_time[] = $cache;
    return true;
}

/**
 * Check the database structure conforms to that describe in the /dbstruct folder. Usually only happens after a SQL error after which the SQL is retried, thus the database is automatically upgraded.
 *
 * This function calls CheckDBStruct() for all plugin paths and the core project.
 *
 * @param  boolean $verbose
 * @return void
 */
function check_db_structs($verbose = false)
{
    global $lang;
    // Ensure two processes are not being executed at the same time (e.g. during an upgrade)
    if (is_process_lock('database_update_in_progress')) {
        show_upgrade_in_progress(true);
        exit();
    }
    set_process_lock('database_update_in_progress');

    // Check the structure of the core tables.
    CheckDBStruct("dbstruct", $verbose);

    // Check the structure of all active plugins.
    global $plugins;
    foreach ($plugins as $plugin) {
        CheckDBStruct("plugins/" . $plugin . "/dbstruct");
    }
    hook("checkdbstruct");

    clear_process_lock('database_update_in_progress');
}

/**
 * Check the database structure against the text files stored in $path.
 * Add tables / columns / data / indices as necessary.
 *
 * @param  string $path
 * @param  boolean $verbose
 * @return void
 */
function CheckDBStruct($path, $verbose = false)
{
    global $mysql_db, $resource_field_column_limit;
    if (!file_exists($path)) {
        # Check for path
        $path = __DIR__ . "/../" . $path; # Make sure this works when called from non-root files..
        if (!file_exists($path)) {
            return false;
        }
    }

    db_begin_transaction("CheckDBStruct");

    # Tables first.
    # Load existing tables list
    $ts = ps_query("show tables", [], '', -1, false);
    $tables = array();
    for ($n = 0; $n < count($ts); $n++) {
        $tables[] = $ts[$n]["Tables_in_" . $mysql_db];
    }
    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if (substr($file, 0, 6) == "table_") {
            $table = str_replace(".txt", "", substr($file, 6));

            # Check table exists
            if (!in_array($table, $tables)) {
                # Create Table
                $sql = "";
                $f = fopen($path . "/" . $file, "r");
                $hasPrimaryKey = false;
                $pk_sql = "PRIMARY KEY (";
                $n = 0;
                while (($col = fgetcsv($f, 5000)) !== false) {
                    if ($sql .= "") {
                        $sql .= ", ";
                    }
                    $sql .= "`{$col[0]}` " . str_replace("§", ",", $col[1]);

                    if (
                        strtolower(substr($col[1], 0, 3)) == "int"
                        || strtolower(substr($col[1], 0, 6)) == "bigint"
                        || strtolower(substr($col[1], 0, 7)) == "tinyint"
                        || strtolower(substr($col[1], 0, 8)) == "smallint"
                    ) {
                        # Integer
                        $column_types[$n] = "i";
                    } elseif (
                        strtolower(substr($col[1], 0, 5)) == "float"
                        || strtolower(substr($col[1], 0, 7)) == "decimal"
                        || strtolower(substr($col[1], 0, 6)) == "double"
                    ) {
                        # Double
                        $column_types[$n] = "d";
                    } elseif (
                        strtolower(substr($col[1], 0, 8)) == "tinyblob"
                        || strtolower(substr($col[1], 0, 4)) == "blob"
                        || strtolower(substr($col[1], 0, 10)) == "mediumblob"
                        || strtolower(substr($col[1], 0, 8)) == "longblob"
                    ) {
                        # Blob
                        $column_types[$n] = "b";
                    } else {
                        # String
                        $column_types[$n] = "s";
                    }

                    $n++;

                    if ($col[4] != "") {
                        $sql .= " default " . $col[4];
                    }
                    if ($col[3] == "PRI") {
                        if ($hasPrimaryKey) {
                            $pk_sql .= ",";
                        }
                        $pk_sql .= $col[0];
                        $hasPrimaryKey = true;
                    }
                    if ($col[5] == "auto_increment") {
                        $sql .= " auto_increment ";
                    }
                }
                $pk_sql .= ")";
                if ($hasPrimaryKey) {
                    $sql .= "," . $pk_sql;
                }
                debug($sql);

                # Verbose mode, used for better output from the test script.
                if ($verbose) {
                    echo "$table ";
                    ob_flush();
                }

                ps_query("create table $table ($sql)", [], '', -1, false);

                # Add initial data
                $data = str_replace("table_", "data_", $file);
                if (file_exists($path . "/" . $data)) {
                    $f = fopen($path . "/" . $data, "r");
                    while (($row = fgetcsv($f, 5000)) !== false) {
                        $sql_params = [];
                        for ($n = 0; $n < count($row); $n++) {
                            // Get type from table file
                            $sql_params[] = $column_types[$n];
                            // dbstruct/data_*.txt files normally have nothing if the column value was null when using
                            // the pages/tools/dbstruct_create.php script.
                            if ($row[$n] === '') {
                                $sql_params[] = null;
                            }
                            // Legacy? I couldn't find any dbstruct/data_*.txt file containing '' for a column value
                            elseif ($row[$n] == "''") {
                                $sql_params[] = null;
                            } else {
                                $sql_params[] = $row[$n];
                            }
                        }

                        ps_query(
                            "insert into `$table` values (" . ps_param_insert(count($row)) . ")",
                            $sql_params,
                            '',
                            -1,
                            false
                        );
                    }
                }
            } else {
                # Table already exists, so check all columns exist

                # Load existing table definition
                $existing = ps_query("describe $table", [], '', -1, false);

                ##########
                # Copy needed resource_data into resource for search displays
                if ($table == "resource") {
                    $joins = get_resource_table_joins();
                    for ($m = 0; $m < count($joins); $m++) {
                        # Look for this column in the existing columns.
                        $found = false;

                        for ($n = 0; $n < count($existing); $n++) {
                            if ("field" . $joins[$m] == $existing[$n]["Field"]) {
                                $found = true;
                            }
                        }

                        if (!$found) {
                            # Add this column.
                            $sql = "alter table $table add column ";
                            $sql .= "field" . $joins[$m] . " VARCHAR(" . $resource_field_column_limit . ")";
                            ps_query($sql, [], '', -1, false);
                        }
                    }
                }
                ##########

                if (file_exists($path . "/" . $file)) {
                    $f = fopen($path . "/" . $file, "r");
                    while (($col = fgetcsv($f, 5000)) !== false) {
                        if (count($col) > 1) {
                            # Look for this column in the existing columns.
                            $found = false;
                            for ($n = 0; $n < count($existing); $n++) {
                                if ($existing[$n]["Field"] == $col[0]) {
                                    $found = true;
                                    $existingcoltype = strtoupper($existing[$n]["Type"]);
                                    $basecoltype = strtoupper(str_replace("§", ",", $col[1]));
                                    # Check the column is of the correct type
                                    preg_match('/\s*(\w+)\s*\((\d+)\)/i', $basecoltype, $matchbase);
                                    preg_match('/\s*(\w+)\s*\((\d+)\)/i', $existingcoltype, $matchexisting);

                                    // Checks added so that we don't trim off data if a varchar size has been increased manually or by a plugin.
                                    // - If column is of same type but smaller number, update
                                    // - If target column is of type text, update
                                    // - If target column is of type varchar and currently int, update (e.g. the 'archive' column in collection_savedsearch moved from a single state to a multiple)
                                    // - If target column is of type mediumtext and currently is text, update
                                    // - If target column is of type longtext and currently is text
                                    if (
                                        (count($matchbase) == 3 && count($matchexisting) == 3 && $matchbase[1] == $matchexisting[1] && $matchbase[2] > $matchexisting[2])
                                        || (stripos($basecoltype, "text") !== false && stripos($existingcoltype, "text") === false)
                                        || (strtoupper(substr($basecoltype, 0, 6)) == "BIGINT" && strtoupper(substr($existingcoltype, 0, 3) == "INT"))
                                        || (
                                        strtoupper(substr($basecoltype, 0, 3)) == "INT"
                                        && (strtoupper(substr($existingcoltype, 0, 7)) == "TINYINT" || strtoupper(substr($existingcoltype, 0, 8)) == "SMALLINT")
                                        )
                                        || (strtoupper(substr($basecoltype, 0, 7)) == "VARCHAR" && strtoupper(substr($existingcoltype, 0, 3) == "INT"))
                                        || (strtoupper(substr($basecoltype, 0, 10)) == "MEDIUMTEXT" && strtoupper(substr($existingcoltype, 0, 4) == "TEXT"))
                                        || (strtoupper(substr($basecoltype, 0, 8)) == "LONGTEXT" && strtoupper(substr($existingcoltype, 0, 4) == "TEXT"))
                                    ) {
                                        debug("DBSTRUCT - updating column " . $col[0] . " in table " . $table . " from " . $existing[$n]["Type"] . " to " . str_replace("§", ",", $col[1]));
                                        // Update the column type
                                        ps_query("alter table $table modify `" . $col[0] . "` " .  $col[1]);
                                    }
                                }
                            }
                            if (!$found) {
                                # Add this column.
                                $sql = "alter table `$table` add column ";
                                $sql .= $col[0] . " " . str_replace("§", ",", $col[1]); # Allow commas to be entered using '§', necessary for a type such as decimal(2,10)
                                if ($col[4] != "") {
                                    $sql .= " default " . $col[4];
                                }
                                if ($col[3] == "PRI") {
                                    $sql .= " primary key";
                                }
                                if ($col[5] == "auto_increment") {
                                    $sql .= " auto_increment ";
                                }
                                ps_query($sql, [], '', -1, false);
                            }
                        }
                    }
                }
            }

            # Check all indices exist
            # Load existing indexes
            $existing = ps_query("show index from $table", [], '', -1, false);

            $file = str_replace("table_", "index_", $file);
            if (file_exists($path . "/" . $file)) {
                $done = array(); # List of indices already processed.
                $f = fopen($path . "/" . $file, "r");
                while (($col = fgetcsv($f, 5000)) !== false) {
                    # Look for this index in the existing indices.
                    $found = false;
                    for ($n = 0; $n < count($existing); $n++) {
                        if ($existing[$n]["Key_name"] == $col[2]) {
                            $found = true;
                        }
                    }
                    if (!$found && !in_array($col[2], $done)) {
                        # Add this index.

                        # Fetch list of columns for this index
                        $cols = array();
                        $f2 = fopen($path . "/" . $file, "r");
                        while (($col2 = fgetcsv($f2, 5000)) !== false) {
                            if ($col2[2] == $col[2]) { # Matching column
                                # Add an index size if present, for indexing text fields
                                $indexsize = "";
                                if (trim($col2[7]) != "") {
                                    $indexsize = "(" . $col2[7] . ")";
                                }

                                $cols[] = $col2[4] . $indexsize;
                            }
                        }

                        $sql = "CREATE " . ($col[10] == "FULLTEXT" ? "FULLTEXT" : "") . " INDEX " . $col[2] . " ON $table (" . join(",", $cols) . ")";
                        ps_query($sql, [], '', -1, false);
                        $done[] = $col[2];
                    }
                }
            }
        }
    }
    db_end_transaction("CheckDBStruct");
}

/**
* Generate the LIMIT statement for a SQL query
*
* @param  integer  $offset  Specifies the offset of the first row to return
* @param  integer  $rows    Specifies the maximum number of rows to return
*
* @return string
*/
function sql_limit($offset, $rows)
{
    $offset_true = !is_null($offset) && is_int_loose($offset) && $offset > 0;
    $rows_true   = !is_null($rows) && is_int_loose($rows) && $rows >= 0;

    $limit = ($offset_true || $rows_true ? 'LIMIT ' : '');

    if ($offset_true && !$rows_true) {
        return '';
    }

    if ($offset_true) {
        $limit .= abs($offset);
    }

    if ($rows_true) {
        $rows = abs($rows);
        $limit .= ($offset_true ? ",{$rows}" : $rows);
    }

    return $limit;
}

/**
 * Utility function to obtain the total found rows while paginating the results.
 *
 * IMPORTANT: the input query MUST have a deterministic order so it can help with performance and not have an undefined behaviour
 *
 * @param PreparedStatementQuery        $query          SQL query
 * @param null|int                      $rows           Specifies the maximum number of rows to return. Usually set by a global
 *                                                      configuration option (e.g $default_perpage, $default_perpage_list).
 * @param null|int                      $offset         Specifies the offset of the first row to return. Use NULL to not offset.
 * @param bool                          $cachecount     Use previously cached count if available?
 * @param null|PreparedStatementQuery   $countquery     Optional separate query to obtain count, usually without ORDER BY
 *
 * @return array Returns a:
 *               - total: int - count of total found records (before paging)
 *               - data: array - paged result set
 */
function sql_limit_with_total_count(PreparedStatementQuery $query, int $rows, int $offset, bool $cachecount = false, ?PreparedStatementQuery $countquery = null)
{
    global $cache_search_count;
    $limit = sql_limit($offset, $rows);
    $data = ps_query("{$query->sql} {$limit}", $query->parameters);
    $total_query = is_a($countquery, "PreparedStatementQuery") ? $countquery : $query;
    $total = (int) ps_value("SELECT COUNT(*) AS `value` FROM ({$total_query->sql}) AS count_select", $total_query->parameters, 0, ($cachecount && $cache_search_count) ? "schema" : "");
    $datacount = count($data);

    // Check if cached total will cause errors
    if ($datacount ==  0 && $rows > 0) {
        // No data returned. Either beyond the last page of results or there were no results at all
        $total = min($total, $offset);
    } elseif ($datacount < $rows) {
        // Some data but not as many rows returned as expected, set to actual value
        $total = $offset + $datacount;
    } elseif ($offset + $datacount > $total) {
        // More rows returned than expected.
        // Set total to the actual number of results
        $total = $offset + $datacount;
    }
    return ['total' => $total, 'data' => $data];
}

/**
* Query helper to ensure code honours the database schema constraints on text columns.
* IMPORTANT: please use where appropriate! In some cases, truncating may mean losing useful information (e.g contextual data),
*            in which case changing the column type may be a better option.
*
* @param string  $v   String value that may require truncating
* @param integer $len Desired length (limit as imposed by the database schema). {@see https://www.resourcespace.com/knowledge-base/developers/database_schema}
*/
function sql_truncate_text_val(string $v, int $len): string
{
    return mb_strlen($v) > $len ? mb_strcut($v, 0, $len) : $v;
}

/**
* When constructing prepared statements and using e.g. ref in (some list of values), assists in outputting the correct number of parameters.
*
* @param integer $count How many parameters to insert, e.g. 3 returns "?,?,?"
*
* @return string
*/
function ps_param_insert($count)
{
    return join(",", array_fill(0, $count, "?"));
}

/**
* When constructing prepared statements and using e.g. ref in (some list of values), assists in preparing the parameter array.
*
* @param array $array The input array, to prepare for output. Will return this array but with type entry inserted before each value.
* @param string $type The column type as per ps_query
*/
function ps_param_fill(array $array, string $type): array
{
    $parameters = array();
    foreach ($array as $a) {
        $parameters[] = $type;
        $parameters[] = $a;
    }
    return $parameters;
}

/**
 * Assists in generating parameter arrays where all of the parameters for a given section of sql are the same.
 *
 * @param string $string A portion of sql that contains one or more placeholders
 * @param string $value The value that should be used to generate the array of parameters
 * @param string $type The column type of $value as per ps_query
 *
 * @return array
 */
function ps_fill_param_array($string, $value, $type)
{
    $placeholder_count = substr_count($string, "?");
    return ps_param_fill(array_fill(0, $placeholder_count, $value), $type);
}

/**
 * Re-order rows in the table
 *
 * @param string $table Table name. MUST have an "order_by" column.
 * @param array  $refs  List of record IDs in the new desired order
 *
 * @return void
 */
function sql_reorder_records(string $table, array $refs)
{
    $GLOBALS['use_error_exception'] = true;
    try {
        $cols = columns_in($table, null, null, true);
    } catch (Throwable $t) {
        $cols = [];
    }
    $GLOBALS['use_error_exception'] = false;
    if (!in_array('order_by', $cols)) {
        return;
    }

    $refs_chunked = db_chunk_id_list($refs);
    $order_by = 0;

    foreach ($refs_chunked as $refs) {
        $cases_params = [];
        $cases = '';

        foreach ($refs as $ref) {
            $order_by += 10;
            $cases .= ' WHEN ? THEN ?';
            $cases_params = array_merge($cases_params, ['i', $ref, 'i', $order_by]);
        }

        $sql = sprintf(
            'UPDATE %s SET order_by = (CASE ref %s END) WHERE ref IN (%s)',
            $table,
            $cases,
            ps_param_insert(count($refs))
        );
        ps_query($sql, array_merge($cases_params, ps_param_fill($refs, 'i')));
    }
}

/**
* Returns a comma separated list of table columns from the given table. Optionally, will use an alias instead of the table name to prefix the columns. For inclusion in SQL to replace "select *" which is not supported when using prepared statements.
*
* @param string $table The source table
* @param string $alias Optionally, a different alias to use
* @param string $plugin [DEPRECATED] Specifies that this table is defined in a plugin with the supplied name
* @param bool   $return_list Set to true to return a list of column names. Note: the alias is ignored in this mode.
*
* @return string|array
*/
function columns_in($table, $alias = null, $plugin = null, bool $return_list = false)
{
    global $plugins;
    if (is_null($alias)) {
        $alias = $table;
    }

    $table_file = dirname(__DIR__) . "/dbstruct/table_" . safe_file_name($table) . ".txt";
    $structure = file_exists($table_file) ? explode("\n", trim(file_get_contents($table_file))) : [];
    $columns = array();
    foreach ($structure as $column) {
        $columns[] = explode(",", $column)[0];
    }

    // Work through all enabled plugins and add any extended columns also (plugins can extend core tables in addition to defining their own)

    foreach ($plugins as $plugin_entry) {
        $plugin_file = get_plugin_path($plugin_entry) . "/dbstruct/table_" . safe_file_name($table) . ".txt";
        if (file_exists($plugin_file)) {
            $plugin_file_contents = trim(file_get_contents($plugin_file));
            if ($plugin_file_contents !== "") {
                $structure = explode("\n", $plugin_file_contents);
                foreach ($structure as $column) {
                    $columns[] = explode(",", $column)[0];
                }
            }
        }
    }

    if ($columns === []) {
        trigger_error("Unable to find the structure for database table '{$table}'", E_USER_ERROR);
    }

    if ($return_list) {
        return $columns;
    }

    return "`" . $alias . "`.`" . join("`, `" . $alias . "`.`", $columns) . "`";
}

/**
 * Database helper to chunk a list of IDs
 * @param list<int> $refs
 * @return list<list<int>>
 */
function db_chunk_id_list(array $refs): array
{
    $valid_ids = array_values(array_unique(array_filter($refs, 'is_int_loose')));
    return array_filter(
        count($valid_ids) <= SYSTEM_DATABASE_IDS_CHUNK_SIZE
            ? [$valid_ids]
            : array_chunk($valid_ids, SYSTEM_DATABASE_IDS_CHUNK_SIZE)
    );
}

/**
 * Delete database table records from a list of IDs
 *
 * ```php
 * return db_delete_table_records(
 *     'brand_guidelines_content',
 *     $refs,
 *     fn($ref) => log_activity(null, LOG_CODE_DELETED, null, 'brand_guidelines_content', 'content', $ref)
 * );
 * ```
 *
 * Example how to not log it:
 * ```php
 * return db_delete_table_records('brand_guidelines_content', $refs, fn() => null);
 * ```
 * @param string $table Database table name
 * @param list<int> $refs List of database IDs
 * @return bool True if it executed the query, false otherwise
 */
function db_delete_table_records(string $table, array $refs, callable $logger): bool
{
    if (!in_array('ref', columns_in($table, null, null, true))) {
        return false;
    }

    $refs_chunked = db_chunk_id_list($refs);
    foreach ($refs_chunked as $refs_list) {
        $done = ps_query(
            sprintf(
                'DELETE FROM %s WHERE ref IN (%s)',
                $table,
                ps_param_insert(count($refs_list))
            ),
            ps_param_fill($refs_list, 'i')
        );
        array_walk($refs_list, $logger);
    }

    return isset($done);
}

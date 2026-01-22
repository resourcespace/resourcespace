<?php

/**
 * Check and set the debug log override status for the current user.
 *
 * This function determines if debug logging should be enabled based on system variables
 * and the user's ID. If a debug override is set for a specific user or globally, and the
 * override has not expired, debug logging will be activated. Expired overrides are removed.
 *
 * @return void
 */
function check_debug_log_override()
{
    global $debug_log_override, $userref;

    if (isset($debug_log_override) || !isset($userref)) {
        return;
    }

    $debug_log_override = false;

    $debug_user = get_sysvar('debug_override_user', '');
    $debug_expires = get_sysvar('debug_override_expires', '');

    if ($debug_user == "" || $debug_expires == "") {
        return;
    }

    if ($debug_expires < time()) {
        ps_query("DELETE FROM sysvars WHERE name='debug_override_user' OR name='debug_override_expires'", array());
        return;
    }

    if ($debug_user == -1 || $debug_user == $userref) {
        $debug_log_override = true;
    }
}

 /**
 * Create a debug log override for a specified user or globally.
 *
 * This function sets a debug override that enables debug logging for a specified user
 * or all users if `$debug_user` is -1. The override is set to expire after a specified
 * duration in seconds. Any existing override settings are removed before the new values
 * are inserted.
 *
 * @param int $debug_user The user ID for whom to enable debug logging (-1 for all users). Default is -1.
 * @param int $debug_expires The time in seconds until the debug override expires, starting from the current time. Default is 60 seconds.
 * @return void
 */
function create_debug_log_override($debug_user = -1, $debug_expires = 60)
{
    ps_query("DELETE FROM sysvars WHERE name='debug_override_user' OR name='debug_override_expires'", array());
    $debug_expires += time();
    ps_query(
        "INSERT INTO sysvars VALUES ('debug_override_user',?), ('debug_override_expires',?)",
        array("s",$debug_user,"s",$debug_expires)
    );
    clear_query_cache("sysvars");
}

/**
* Debug called function and its arguments
*
* The best way to use this function is to call it on the first line of a function definition:
*
* function some_test($required, $num, $optional_bool = false)
*     {
*     debug_function_call(__FUNCTION__, func_get_args());
*
*     echo "called some_test" . PHP_EOL;
*
*     return;
*     }
*
* @param string $name The function name
* @param array $args The "runtime" args
*
* @return boolean|void @see debug()
*/
function debug_function_call($name, array $args)
{
    global $debug_log, $debug_log_override;
    if (!$debug_log && !$debug_log_override) {
        return false;
    }

    $args_str = "";
    $fct = new ReflectionFunction($name);
    foreach ($fct->getParameters() as $param) {
        $value = null;

        if (!$param->isOptional() && isset($args[$param->getPosition()])) {
            $value = $args[$param->getPosition()];
        } elseif ($param->isOptional() && isset($args[$param->getPosition()])) {
            $value = $args[$param->getPosition()];
        } elseif ($param->isOptional() && $param->isDefaultValueAvailable()) {
            $value = $param->getDefaultValue();
        }

        $args_str .= sprintf("\$%s = %s, ", $param->getName(), debug_stringify($value));
    }
    $args_str = rtrim($args_str, ", ");

    return debug("{$name}( {$args_str} );");
}

/**
* Stringify variables for use in the debug log. This is used more as fallback to json_encode() failing to maintain quick
* readability of the logs.
*
* @param mixed $value Any value that needs stringified
*
* @return string
*/
function debug_stringify($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return trim(preg_replace('/\s+/m', ' ', print_r($value, true)));
}

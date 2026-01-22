<?php

/**
* @package ResourceSpace
* @subpackage AJAX
*
* The functions available in this file will help developers provide a consistent response to AJAX requests.
*
* All functions (with the exception of ajax_permission_denied) will follow the JSEnd specification (@see https://github.com/omniti-labs/jsend)
*
*/

/**
* Returns a standard AJAX response for unauthorised access
*
* The function will return a 401 HTTP status code.
*
* @deprecated
*
* @return void
*/
function ajax_permission_denied()
{
    $return['error'] = array(
        'status' => 401,
        'title'  => $GLOBALS["lang"]["unauthorized"],
        'detail' => $GLOBALS["lang"]['error-permissiondenied']);

    http_response_code(401);
    echo json_encode($return);
    exit();
}

/**
* Send AJAX response back to the client together with the appropriate HTTP status code
*
* @param  integer  $code      HTTP status code for this response
* @param  array    $response  Response data (@see other ajax_response_* functions for expected structure)
*
* @return void
*/
function ajax_send_response($code, array $response)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

/**
* Send AJAX text/html response back to the client together with the appropriate HTTP status code
*
* @param  integer  $code      HTTP status code for this response
* @param  string   $response  Response data (text/html)
*
* @return void
*/
function ajax_send_text_response($code, $response)
{
    http_response_code($code);
    echo $response;
    exit();
}

/**
* Builds the correct response expected for a success request where there is data to return (e.g getting search results)
*
* @param array $data Data to be returned back to the client
*
* @return array
*/
function ajax_response_ok(array $data)
{
    return array(
        "status" => "success",
        "data" => $data);
}

/**
* Builds the correct response expected for failures.
*
* When a call is rejected due to invalid data or call conditions, the response data key contains an object explaining
* what went wrong, typically a hash of validation errors.
*
* @param array $data  Provides details of why the request failed. If the reasons for failure correspond to POST values,
*                     the response objects' keys SHOULD correspond to those POST values. If generic, use message key
*                     instead (@see ajax_build_message() ).
*
* @return array
*/
function ajax_response_fail(array $data)
{
    return array(
        "status" => "fail",
        "data" => $data);
}

/**
* Builds the correct response expected for a success request where there is no data to return (e.g when deleting a record)
*
* @return array
*/
function ajax_response_ok_no_data()
{
    return array(
        "status" => "success",
        "data" => null);
}

/**
* Returns a standard AJAX response for unauthorised access with a 401 HTTP status code
*
* @return void
*/
function ajax_unauthorized()
{
    global $lang;
    ajax_send_response(401, ajax_response_fail(ajax_build_message($lang['error-permissiondenied'])));
}

/**
* Builds a message to be used in an AJAX response
*
* @param string $msg An end-user message explaining what happened (as a generic message for fails or part of errors)
*
* @return array  Returns a message
*/
function ajax_build_message(string $msg)
{
    $msg = trim($msg);
    if ($msg == "") {
        trigger_error("\$msg variable must not be empty.");
    }

    return array("message" => $msg);
}

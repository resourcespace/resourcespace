<?php

include __DIR__ . '/../../include/boot.php';
include __DIR__ . '/../../include/authenticate.php';
include_once __DIR__ . '/../../include/node_functions.php';
header('Content-Type: application/json');

/*
This allows Asynchronous searches for nodes, either by: node ID or simply by searching for a name (fuzzy search)

Expected functionality:
If we search by node ID, then if found we get its details back
Otherwise, we get all results back based on the name we've searched for.
*/
$node                = getval('node', 0, true);
$resource_type_field = getval('resource_type_field', 0, true);
$name                = trim(getval('name', ''));
$rows                = getval('rows', 10, true);

// Prevent access to fields to which user does not have access to
// Prevent anon users from accessing this page altogether
// Prevent users from using fields other than ones configured for annotations
if (
    !metadata_field_view_access($resource_type_field) 
    || is_anonymous_user() 
    || !in_array($resource_type_field, get_annotate_fields())
) {
    header('HTTP/1.1 401 Unauthorized');
    $return['error'] = array(
        'status' => 401,
        'title'  => 'Unauthorized',
        'detail' => $lang['error-permissiondenied']);

    echo json_encode($return);
    exit();
}

$return               = array();
$found_node_by_ref    = array();
$current_node_pointer = 0;


if ($node > 0 && get_node($node, $found_node_by_ref)) {
    $found_node_by_ref['name'] = i18n_get_translated($found_node_by_ref['name']);

    $return['data'] = $found_node_by_ref;

    echo json_encode($return);
    exit();
}

// Fuzzy search by node name:
// Translate (i18l) all options and return those that have a match for what client code searched (fuzzy searching still applies)
if ($name != "") {
    // Set $keywords_remove_diacritics so as to only add versions with diacritics to return array if none are in the submitted string
    $keywords_remove_diacritics = mb_strlen($name) === strlen($name);
    $name = normalize_keyword($name);

    foreach (get_nodes($resource_type_field, null, true, null, $rows, $name) as $node) {
        if ($rows == $current_node_pointer) {
            break;
        }

        $i18l_name = i18n_get_translated($node['name']);
        $compare = normalize_keyword($i18l_name);
        // Skip any translated (i18l) names that don't contain what client code searched for
        if (false === mb_strpos(mb_strtolower($compare), mb_strtolower($name))) {
            continue;
        }

        $node['name'] = $i18l_name;

        $return['data'][] = $node;

        // Increment only when valid nodes have been added to the result set
        $current_node_pointer++;
    }
}

// Search did not return any results back. This is still considered a successful request!
if (($node > 0 || $name != "") && !isset($return['data']) && 0 === count($return)) {
    $return['data'] = array();
}

// Only resource type field specified? That means client code is querying for all options of this field
if ($resource_type_field > 0 && $name == "") {
    foreach (get_nodes($resource_type_field, null, true) as $node) {
        $node['name']     = i18n_get_translated($node["name"]);
        $return['data'][] = $node;
    }
}

// If by this point we still don't have a response for the request,
// create one now telling client code this is a bad request
if (0 === count($return)) {
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'The request could not be handled by get_nodes.php!');
}

echo json_encode($return);
exit();

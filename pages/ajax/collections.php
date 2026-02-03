<?php

$rsroot = dirname(dirname(__DIR__));
include "{$rsroot}/include/boot.php";
include "{$rsroot}/include/authenticate.php";
include_once "{$rsroot}/include/ajax_functions.php";

$collection = getval("collection", 0, true);

if (checkperm("b") && !(isset($anonymous_login) && $username == $anonymous_login && $anonymous_user_session_collection)) {
    // Only has access to the result selection collection
    $selection_collection_only = true;
    $collection = $USER_SELECTION_COLLECTION;
}

$allowed_actions = array(
    "clear_selection_collection_resources",
    "get_selected_resources_counter",
    "render_selected_resources_counter",
    "render_edit_selected_btn",
    "render_clear_selected_btn",
    "remove_selected_from_collection",
    );

if ($collection == 0 && isset($anonymous_login) && $username == $anonymous_login) {
    if (is_null($USER_SELECTION_COLLECTION)) {
        // No selection collection is created for anonymous users until an action is performed by the user so create one now
        $USER_SELECTION_COLLECTION = create_collection($userref, "Selection Collection (for batch edit)", 0, 1);
        update_collection_type($USER_SELECTION_COLLECTION, COLLECTION_TYPE_SELECTION);
    }
    $collection = $USER_SELECTION_COLLECTION;
}

if (isset($selection_collection_only) && $collection != $USER_SELECTION_COLLECTION) {
    ajax_unauthorized();
}

$return = array();
$action = trim(getval("action", ""));

if ($action == "" || !in_array($action, $allowed_actions)) {
    $fail_msg = str_replace("%key", "action", $lang["error-request-missing-key"]);
    ajax_send_response(400, ajax_response_fail(ajax_build_message($fail_msg)));
}

if ($action == "clear_selection_collection_resources") {
    remove_all_resources_from_collection($USER_SELECTION_COLLECTION);
    ajax_send_response(200, ajax_response_ok_no_data());
}

if ($action == "get_selected_resources_counter") {
    $counter = count(get_collection_resources($USER_SELECTION_COLLECTION));
    ajax_send_response(200, ajax_response_ok(array("selected" => $counter)));
}

if ($action == "render_selected_resources_counter") {
    $counter = count(get_collection_resources($USER_SELECTION_COLLECTION));
    ajax_send_text_response(200, render_selected_resources_counter($counter));
}

if ($action == "render_clear_selected_btn") {
    ajax_send_text_response(200, cast_echo_to_string('render_clear_selected_btn'));
}

if ($action == "render_edit_selected_btn") {
    include_once "{$rsroot}/include/do_search.php";

    $restypes = getval("restypes", "");
    $archive = getval("archive", "");
    ajax_send_text_response(200, cast_echo_to_string('render_edit_selected_btn'));
}

if ($action == "remove_selected_from_collection") {
    if (isset($selection_collection_only)) {
        ajax_unauthorized();
    }
    if (!collection_readable($usercollection)) {
        $fail_msg = str_replace("%ref", $usercollection, $lang["error-collection-unreadable"]);
        ajax_send_response(400, ajax_response_fail(ajax_build_message($fail_msg)));
    }

    $selected_resources       = get_collection_resources($USER_SELECTION_COLLECTION);
    $usercollection_resources = get_collection_resources($usercollection);

    $refs_to_remove = array_intersect($selected_resources, $usercollection_resources);
    foreach (array_intersect($selected_resources, $usercollection_resources) as $ref) {
        remove_resource_from_collection($ref, $usercollection);
    }

    ajax_send_response(200, ajax_response_ok_no_data());
}

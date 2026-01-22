<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

// Generic endpoint that can be used for ajax calls
$action = getval('action', '');
$return = array();
$return['status'] = 400; // set to default

switch ($action) {
    case 'submitpending':
        // prevent search from returning all contributed resources (archive filter will not be ignored in this case)
        $search_all_workflow_states = false;

        $pending_items = do_search("!contributions" . $userref, "", "", -2, -1, "desc", false, 0, false, false, "", false, false, true);
        $collection_add = getval('collection_add', 0, true);
        $collection_resources = is_numeric($collection_add) ? get_collection_resources($collection_add) : array();

        $submit = array();
        $submitstates = array();

        for ($r = 0; $r < count($pending_items); $r++) {
            if ($collection_add == 0 || in_array($pending_items[$r]["ref"], $collection_resources)) {
                // Add this resource to the array of resources to submit
                $submit[] = $pending_items[$r]["ref"];
                $submitstates[] = -2; // Needed so that from state is logged correctly by update_archive_status
            }
        }
        if (count($submit) > 0) {
            // Submit all the resources
            update_archive_status($submit, -1, $submitstates, $collection_add);
        }

        $return['status'] = 200;
        break;

    case 'updatelock':
        $resource = getval("ref", 0, true);
        $lockaction = getval("lock", '') == "true";
        $resource_data = get_resource_data($resource);

        if (((string)(int)$resource != (string)$resource) || !$resource_data) {
            $return['message'] = $lang["error_invalid_input"] ;
            break;
        }
        $edit_access = get_edit_access($resource, $resource_data["archive"], $resource_data);
        $lockuser =  $resource_data["lock_user"];

        if ($lockaction && $lockuser > 0 && $lockuser != $userref) {
            // Already locked
            $return['status'] = 403;
            $return['message'] = get_resource_lock_message($lockuser);
        } elseif (
            checkperm("a")
            ||
            $lockuser == $userref
            ||
            ($edit_access && $lockuser == 0 && !checkperm("nolock"))
        ) {
            $success = update_resource_lock($resource, $lockaction, $userref, true);
            if ($success) {
                $return['status'] = 200;
            }
        } else {
            $return['status'] = 403;
            $return['message'] = $lang["error-permissiondenied"];
        }
        break;

    default:
        $return['message'] = $lang["error_generic"] ;
        break;
}
http_response_code($return['status']);
header('Content-type: application/json');
echo json_encode($return);
exit();

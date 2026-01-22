<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

$ref        = getval('ref', 0, true);
$related    = getval('related', 0, true);
$add        = getval('action', 'add') == "add";
$collection = getval('collection', 0, true);

$success = false;

if ($collection >  0) {
    if (allow_multi_edit($collection)) {
        $success = relate_all_collection($collection, false);
    }
} else {
    if (get_edit_access($ref) && get_edit_access($related)) {
        $success = update_related_resource($ref, $related, $add);
    }
}
if ($success) {
    exit("SUCCESS");
} else {
    http_response_code(403);
    exit(escape($lang["error-permissiondenied"]));
}

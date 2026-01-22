<?php

include_once '../../include/boot.php';
include_once '../../include/authenticate.php';

$collection = getval('collection', 0, true);

$success = false;

if ($collection > 0) {
    $success = unrelate_all_collection($collection, true);
}

if ($success) {
    exit("SUCCESS");
} else {
    http_response_code(403);
    exit(escape($lang["error-permissiondenied"]));
}

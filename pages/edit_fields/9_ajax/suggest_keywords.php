<?php

include dirname(__DIR__, 3) . '/include/boot.php';
$k = getval('k', '');
$upload_collection = getval('upload_share_active', '');
if ($k == "" || (!check_access_key_collection($upload_collection, $k))) {
    include dirname(__DIR__, 3) . '/include/authenticate.php';
}

if (is_anonymous_user() && !upload_share_active()) {
    exit(escape($lang['error-permissiondenied']));
}

$field    = getval('field', '');
$keyword  = getval('term', '');
$readonly = ('' != getval('readonly', '') ? true : false);

$results = suggest_dynamic_keyword_nodes($field, $keyword, $readonly);

// We return an array of objects with label and value properties: [ { label: "Node ID 1 - option name", value: "101" }, ... ]
// This will later be used by jQuery autocomplete
header('Content-Type: application/json');
echo json_encode($results);
exit();

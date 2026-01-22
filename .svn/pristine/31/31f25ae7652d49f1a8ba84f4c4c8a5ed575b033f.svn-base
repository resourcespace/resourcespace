<?php

include __DIR__ . '/../../../include/boot.php';

$k = getval('k', '');
$upload_collection = upload_share_active();
if ($k == "" || !check_access_key_collection($upload_collection, $k)) {
    include __DIR__ . '/../../../include/authenticate.php';
}

$field   = getval('field', '');
$keyword = getval('keyword', '');
$result  = array();

if (checkperm('bdk' . $field)) {
    header('HTTP/1.1 401 Unauthorized');
    die('Permission denied!');
}

// Add node and return to jQuery autocomplete the new node ID
$new_node_id = set_node(null, $field, $keyword, null, null);

if (false !== $new_node_id && is_numeric($new_node_id)) {
    $result['new_node_id'] = $new_node_id;
    clear_query_cache("schema");
}

header('Content-Type: application/json');
echo json_encode($result);
exit();

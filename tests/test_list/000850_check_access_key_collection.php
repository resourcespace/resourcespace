<?php

command_line_only();

$anonymous_login_placeholder = $anonymous_login;
$anonymous_login = ""; 

$resource_1     = create_resource(1, 0);
$resource_2     = create_resource(1, 0);
$resource_3     = create_resource(1, 0);
$collection_ref = create_collection($userref, 'Test collection access keys');

$collection_ref_empty = create_collection($userref, 'Test empty collection access keys');

add_resource_to_collection($resource_1, $collection_ref);
add_resource_to_collection($resource_2, $collection_ref);
add_resource_to_collection($resource_3, $collection_ref);

$valid_k   = generate_collection_access_key($collection_ref, 0, 'testEmail@testDomain.com', 0, date('Y-m-d H:i:s', strtotime('+3 days')));
$invalid_k = 'badKvalue';

$valid_k_empty = generate_collection_access_key($collection_ref_empty, 0, 'testEmail@testDomain.com', 0, date('Y-m-d H:i:s', strtotime('+3 days')));

// Valid K
if (!check_access_key_collection($collection_ref, $valid_k)) {
    return false;
}

// Invalid K
if (check_access_key_collection($collection_ref, $invalid_k)) {
    return false;
}

// Expired K
if (edit_collection_external_access($valid_k, 0, date('Y-m-d H:i:s', strtotime('-5 days'))) && check_access_key_collection($collection_ref, $valid_k)) {
    return false;
}

delete_collection_access_key($collection_ref, $valid_k);

// New tests for empty collection
// Valid K Empty (No resources; treat as failure regardless of key)
if (check_access_key_collection($collection_ref_empty, $valid_k_empty)) {
    return false;
}

// Invalid K Empty (No resources; treat as failure regardless of key)
if (check_access_key_collection($collection_ref_empty, $invalid_k)) {
    return false;
}

delete_collection_access_key($collection_ref_empty, $valid_k_empty);
$anonymous_login = $anonymous_login_placeholder;

return true;

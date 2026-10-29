<?php

command_line_only();

$anonymous_login_placeholder = $anonymous_login;
$anonymous_login = ""; 

$resource_ref = create_resource(1, 0);
$valid_k      = generate_resource_access_key($resource_ref, $userref, 0, date('Y-m-d H:i:s', strtotime('+3 days')), 'testEmail@testDomain.com');
$invalid_k    = 'badKvalue';

// Valid K
if (!check_access_key($resource_ref, $valid_k)) {
    return false;
}

// Expired K
if (edit_resource_external_access($valid_k, 0, date('Y-m-d H:i:s', strtotime('-5 days'))) && check_access_key($resource_ref, $valid_k)) {
    return false;
}

// Invalid K
if (check_access_key($resource_ref, $invalid_k)) {
    return false;
}

delete_resource_access_key($resource_ref, $valid_k);

$anonymous_login = $anonymous_login_placeholder;

return true;

<?php

command_line_only();


$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$userref            = new_user("user001340");
$otheruser          = new_user("other001340");

$resourcea      = create_resource(1, 0);
$resourceb      = create_resource(1, 0);

$collectiona    = create_collection($userref, "Share upload test");
add_resource_to_collection($resourcea, $collectiona);
$coldataa       = get_collection($collectiona);

$collectionb    = create_collection($userref, "Share upload test 2");
add_resource_to_collection($resourceb, $collectionb);
$coldatab       = get_collection($collectionb);

$shareoptionsa = array(
    "usergroup" => $usergroup,
    "user"      => $userref,
    "expires"   => date("Y-m-d", time() + 60 * 60 * 48)
    );

$sharea = create_upload_link($collectiona, $shareoptionsa);

$shareoptionsb = array(
    "usergroup" => $usergroup,
    "user"      => $otheruser,
    "expires"   => date("Y-m-d", time() + 60 * 60 * 48)
    );

$shareb = create_upload_link($collectionb, $shareoptionsb);

// Test A - check can edit own upload share
$userpermissions = array("s","c","d");
$editable       = can_edit_upload_share($collectiona, $sharea[0]);
if (!$editable) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Test B - check can edit other user's upload share
$userpermissions = array("s","c","d");
$editable       = can_edit_upload_share($collectionb, $shareb[0]);
if ($editable) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Test C - check admin can edit other user's upload share
$userpermissions = array("s","c","d","a");
$editable       = can_edit_upload_share($collectionb, $shareb[0]);
if (!$editable) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// Test D - check user with 'ex' permission can edit other user's upload share that never expires
$result = edit_collection_external_access($shareb[0], -1, '', $usergroup, '');
$userpermissions = array("s","c","d","ex");
$editable       = can_edit_upload_share($collectionb, $shareb[0]);
if (!$editable) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;

return true;

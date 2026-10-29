<?php

command_line_only();


$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$userref            = new_user("user001320");
$otheruser          = new_user("other001320");

$resourcea      = create_resource(1, 0);
$resourceb      = create_resource(1, 0);
$collectiona    = create_collection($userref, "Share upload test");
add_resource_to_collection($resourcea, $collectiona);
$coldataa       = get_collection($collectiona);
$collectionb    = create_collection($otheruser, "Share upload test 2");
add_resource_to_collection($resourceb, $collectionb);
$coldatab       = get_collection($collectionb);

// Subtest A - can_share_upload_link() with other user's collection and perm 'a'
$userpermissions = array("s","a","c","d");
if (!can_share_upload_link($coldatab)) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Subtest B - can_share_upload_link() with other user's collection and perm 'exup'
$userpermissions = array("s","exup","c","d");
if (can_share_upload_link($coldatab)) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// Subtest C - can_share_upload_link() with own collection
$userpermissions = array("s","exup","c","d");
if (!can_share_upload_link($coldataa)) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// Subtest D - can_share_upload_link() with own collection but no permission
$userpermissions = array("s","c","d");
if (can_share_upload_link($coldataa)) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;

return true;

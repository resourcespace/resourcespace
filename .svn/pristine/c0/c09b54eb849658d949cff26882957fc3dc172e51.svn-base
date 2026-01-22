<?php

command_line_only();


$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$userref            = new_user("user001330");
$userpermissions    = array("s","c","d");

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
    "user"      => $userref,
    "expires"   => date("Y-m-d", time() + 60 * 60 * 48)
    );

$shareb = create_upload_link($collectionb, $shareoptionsb);

$sharefltr = array("share_user" => $userref, "share_type" => 1);
$shares = get_external_shares($sharefltr);
if (count($shares) != 2) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Expire share B
$pastdate = date("Y-m-d", time() - 60 * 60 * 48);
$result = edit_collection_external_access($shareb[0], -1, $pastdate, $usergroup, '');

purge_expired_shares($sharefltr);

$sharefltr = array();
$shares = get_external_shares($sharefltr);

if (count($shares) > 1) {
    echo "ERROR - SUBTEST B (purge_expired_shares())\n";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;

return true;

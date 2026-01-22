<?php

command_line_only();


$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$userref            = new_user("user001150");

// Create resources
$resourcea      = create_resource(1, 0);
$resourceb      = create_resource(1, 0);
$resourcec      = create_resource(2, 0);
$resourced      = create_resource(3, 0);

// Test A - relate B and C to A and check they get related
update_related_resource($resourcea, array($resourceb,$resourcec));
$related = get_related_resources($resourcea);

if (count($related) != 2 || !match_values($related, array($resourceb, $resourcec))) {
    echo "SUBTEST A - ";
    return false;
}

// Test B - relate D to A and check all are related
update_related_resource($resourcea, array($resourced));
$related = get_related_resources($resourcea);

if (count($related) != 3 || !match_values($related, array($resourceb, $resourcec, $resourced))) {
    echo "SUBTEST B - ";
    return false;
}

// Test C - unrelate C from A
update_related_resource($resourcec, array($resourcea), false);
$related = get_related_resources($resourcea);

if (count($related) != 2 || !match_values($related, array($resourceb, $resourced))) {
    echo "SUBTEST C - ";
    return false;
}


// Test D - block edit access to resource type 3 and try to relate C to A again
$userpermissions = array("s","e0,e-1,e-2,z3");
$success = update_related_resource($resourcea, $resourcec);
$related = get_related_resources($resourcea);
if ($success || in_array($resourcec, $related)) {
    echo "SUBTEST D - ";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;

return true;

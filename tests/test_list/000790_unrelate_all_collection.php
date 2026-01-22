<?php

command_line_only();

$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$userref            = new_user("user001160");

// Create resources
$resourcea      = create_resource(1, 0);
$resourceb      = create_resource(1, 0);
$resourcec      = create_resource(2, 0);
$resourced      = create_resource(3, 0);

// Relate all in collection
$collection_ref = create_collection($userref, "related resources");
collection_add_resources($collection_ref, array($resourceb, $resourcec));
if (!relate_all_collection($collection_ref, true)) {
    echo "Cannot relate resources in collection - ";
    return false;
}

// Relating outside of the collection
update_related_resource($resourcea, $resourceb);
update_related_resource($resourceb, $resourced);

// Unrelate collection resources
if (!unrelate_all_collection($collection_ref, true)) {
    echo "Cannot unrelate resources in collection - ";
    return false;
}

// Check relations outside of the collection weren't affected
$ref_outside_collection = ps_value('SELECT COUNT(*) AS `value` FROM resource_related WHERE `resource` = ? AND related = ?', array('i', $resourcea, 'i', $resourceb), 0);
$ref_inside_collection = ps_value('SELECT COUNT(*) AS `value` FROM resource_related WHERE `resource` = ? AND related = ?', array('i', $resourceb, 'i', $resourced), 0);
if ($ref_outside_collection == 0 || $ref_inside_collection == 0) {
    echo "Relationships to resources outside of the collection have been changed - ";
    return false;
}

// Check permissions for unrelating are applied (tests allow_multi_edit()).
relate_all_collection($collection_ref, true);
$userpermissions = array_merge($userpermissions, array('XE2')); // Remove edit of resource type 2 so $resourcec isn't editable.
if (unrelate_all_collection($collection_ref, true)) {
    echo "Permissions check wasn't applied - ";
    return false;
}
$related_b_to_c = ps_value('SELECT COUNT(*) AS `value` FROM resource_related WHERE `resource` = ? AND related = ?', array('i', $resourceb, 'i', $resourcec), 0);
$related_c_to_b = ps_value('SELECT COUNT(*) AS `value` FROM resource_related WHERE `resource` = ? AND related = ?', array('i', $resourcec, 'i', $resourceb), 0);
if ($related_b_to_c != 1 || $related_c_to_b != 1) {
    echo "Relationships changed without permission - ";
    return false;
}

$userref = $saved_userref;
$userpermissions = $savedpermissions;

//----------------

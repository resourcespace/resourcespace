<?php

command_line_only();
$userpermissions_cache = $userpermissions;

$all_types = get_all_resource_types();

if (empty($all_types)) {
    echo "No resource types returned";
    return false;
}

$available_types = get_resource_types();

if (count($all_types) != count($available_types)) {
    echo "Not all resource types returned";
    return false;
}

$first_type = get_resource_types((string) $all_types[0]["ref"]);
if (count($first_type) != 1) {
    echo "Returning single resource type failed";
    return false;
}


// Block first resource type from user
// All results should omit this field now
$userpermissions = array_filter($userpermissions, function ($k) {
    return strpos($k, "T") !== 0;
});
$userpermissions[] = "T" . $all_types[0]["ref"];

$available_types = get_resource_types();

if (count($all_types) - 1 != count($available_types)) {
    echo "Blocked resource type returned A";
    return false;
}

$blocked_type = get_resource_types($all_types[0]["ref"]);

if (!empty($blocked_type)) {
    echo "Blocked resource type returned B";
    return false;
}

$userpermissions = $userpermissions_cache;
unset($all_types, $available_types, $first_type, $blocked_type);

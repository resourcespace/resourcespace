<?php

command_line_only();
$saved_permissions = $userpermissions;
$savedderestrictfilter = $userderestrictfilter;
$original_user_data = $userdata;

// Setup test specific user
$user_general = new_user("test_001402_general", 2);
if ($user_general === false) {
    $user_general = ps_value("SELECT ref AS `value` FROM user WHERE username = 'test_001402_general'", array(), 0);
}
if ($user_general === 0) {
    echo "Setup test: users - ";
    return false;
}
$user_general_data = get_user($user_general);

// Create a number of type 1 resources which are active
$resource_list = array();
$resource_list[] = create_resource(1, 0);
$resource_list[] = create_resource(1, 0);
$resource_list[] = create_resource(1, 0);
$resource_list[] = create_resource(1, 0);

// Ensure creation was successful
foreach ($resource_list as $resource_entry) {
    if ($resource_entry === false) {
        echo "Setup test: resources - ";
        return false;
    }
}

// Create an empty collection
$collection_ref = create_collection($user_general, "test_001402", 1);

// Ensure that reordering an empty collection does not fail
$new_order = array();
try {
    update_collection_order($new_order, $collection_ref);
} catch (Exception $e) {
    echo "Update empty collection order";
    return false;
}

// Ensure that deriving the minimum access for an empty collection does not fail
try {
    $min_access = collection_min_access($collection_ref);
} catch (Exception $e) {
    echo "Minimum access for empty collection";
    return false;
}

// Ensure that deriving the maximum access for an empty collection does not fail
try {
    $max_access = collection_max_access($collection_ref);
} catch (Exception $e) {
    echo "Maximum access for empty collection";
    return false;
}

// Add resources to the collection
foreach ($resource_list as $resource_entry) {
    if (!add_resource_to_collection($resource_entry, $collection_ref)) {
        echo "Setup test: collection resources - ";
        return false;
    }
}

// Ensure that reordering the populated collection is successful
$new_order = array($resource_list[3],$resource_list[0],$resource_list[2],$resource_list[1]);
try {
    update_collection_order($new_order, $collection_ref);
} catch (Exception $e) {
    echo "Update populated collection order exception";
    return false;
}

// Ensure that the resulting order is as expected
$resource_order_sql = "select resource value from collection_resource WHERE collection=? ORDER BY sortorder";
$resource_order = ps_array($resource_order_sql, array("i",$collection_ref));

$expected_order = array($resource_list[3],$resource_list[0],$resource_list[2],$resource_list[1]);
if ($resource_order != $expected_order) {
    echo "\nUpdate populated collection order wrong\n";
    return false;
}

 // Check collection_min_access()

setup_user($user_general_data);
// Should get 0 as minimum access by default
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_FULL) {
    echo "collection_min_access with 'g' permission - ";
    return false;
}

// Test restricted user access (no 'g' permission)
$userpermissions = array_values(array_diff($userpermissions, ['g']));
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_RESTRICTED) {
    echo "collection_min_access with no 'g' permission - " . $resource_list[0];
    return false;
}

// Restrict access to a resource type and a single resource
$userpermissions[] = "g";

// Restrict access to a resource type - should get 1 as minimum access
$userpermissions[] = "X1";
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_RESTRICTED) {
    echo "collection_min_access with 'X1' permission and restricted resource type - ";
    return false;
}
$userpermissions = array_values(array_diff($userpermissions, ['X1']));

// Restrict access to a resource type - should get 1 as minimum access
ps_query("UPDATE resource SET access=? WHERE ref=?", ["i", RESOURCE_ACCESS_RESTRICTED, "i", $resource_list[0]]);
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_RESTRICTED) {
    echo "collection_min_access with 'g' permission and restricted resource - ";
    return false;
}

// Derestricted resource - user has no 'g' permission but filter matches- should get 1 as minimum access even if some match
// Create new 'Derestrict' field
$derestrictfield = create_resource_type_field("Derestrict?", 0, FIELD_TYPE_CHECK_BOX_LIST, "derestrict");
$derestrictnode = set_node(null, $derestrictfield, "Derestrict?", '', 1000);
add_resource_nodes($resource_list[0], [$derestrictnode], false);

// Create filter
$filter_name = "Derestricted";
$filter_condition = RS_FILTER_ALL;
$derestrictfilter = save_filter(0, $filter_name, $filter_condition);
$rules = [
    [RS_FILTER_NODE_IN, [$derestrictnode]]
];
save_filter_rule(0, $derestrictfilter, $rules);
$userderestrictfilter = $derestrictfilter;
// Reset access
ps_query("UPDATE resource SET access=? WHERE ref=?", ["i", RESOURCE_ACCESS_FULL, "i", $resource_list[0]]);
$userpermissions = array_values(array_diff($userpermissions, ['g']));
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_RESTRICTED) {
    echo "collection_min_access with 'g' permission and one derestricted resource - ";
    return false;
}

// Update remaining resources to match filter - should get 0 as minimum access
add_resource_nodes($resource_list[1], [$derestrictnode], false);
add_resource_nodes($resource_list[2], [$derestrictnode], false);
add_resource_nodes($resource_list[3], [$derestrictnode], false);
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$min_access = collection_min_access($collection_ref);
if ($min_access !== RESOURCE_ACCESS_FULL) {
    echo "collection_min_access with 'g' permission and all derestricted resources - ";
    return false;
}

// Tear down
unset($user_general, $user_general_data);
unset($resource_list, $resource_entry, $collection_ref, $new_order);
unset($min_access, $max_access);
unset($resource_order_sql, $resource_order, $expected_order);
$GLOBALS["resource_access_cache"] = [];
$GLOBALS["get_resource_data_cache"] = [];
$userpermissions = $saved_permissions;
$userderestrictfilter = $savedderestrictfilter;

// Reset as the primary test user
setup_user($original_user_data);
$userdata = $original_user_data;
return true;

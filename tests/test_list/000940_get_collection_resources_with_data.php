<?php

command_line_only();


// Setup test
$original_user_data = $userdata;
$user_general = new_user("test_001401_general", 2);
if ($user_general === false) {
    $user_general = ps_value("SELECT ref AS `value` FROM user WHERE username = 'test_001401_general'", array(), 0);
}
if ($user_general === 0) {
    echo "Setup test: users - ";
    return false;
}
$user_general_data = get_user($user_general);

$resource_active = create_resource(1, 0);
$resource_delete = create_resource(1, 3);
if ($resource_active === false || $resource_delete === false) {
    echo "Setup test: resources - ";
    return false;
}

$collection_ref = create_collection($user_general, "test_001401", 1);
if (!add_resource_to_collection($resource_active, $collection_ref) || !add_resource_to_collection($resource_delete, $collection_ref)) {
    echo "Setup test: collection - ";
    return false;
}


$admin_result = get_collection_resources_with_data($collection_ref);

setup_user($user_general_data);
$genuser_result = get_collection_resources_with_data($collection_ref);

if ($admin_result !== $genuser_result) {
    echo "Result is the same for both Admins & General users - ";
    return false;
}

// Check if result has resource data. I picked up archive because I'm creating a resource in the delete state so can check it.
$result_archives = array_column($admin_result, "archive");
if (empty($result_archives) || !in_array(3, $result_archives)) {
    echo "Result has no resource data (archive) - ";
    return false;
}


// Tear down
$userdata = $original_user_data;
setup_user($original_user_data);
unset($original_user_data, $user_general, $user_general_data, $result_archives);
unset($resource_active, $resource_delete, $collection_ref, $result, $resources_count, $admin_result, $genuser_result);

return true;

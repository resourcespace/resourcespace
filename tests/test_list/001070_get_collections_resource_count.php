<?php

command_line_only();


// Setting up the test
$original_user_data = $userdata;
$fail_fct = function (string $msg) {
    echo "{$msg} - ";
    return false;
};
$add_ref_to_col_fct = function ($ref, $col, $col_state) {
    if (add_resource_to_collection($ref, $col)) {
        $col_state[] = $ref;
    }
    return $col_state;
};

// Create a general user
$user_general = new_user("test_001903_general", 2);
if ($user_general === false) {
    $user_general = ps_value("SELECT ref AS `value` FROM user WHERE username = 'test_001903_general'", array(), 0);
}
if ($user_general === 0) {
    return $fail_fct('Setting up test user (test_001903_general)');
}
$user_general_data = get_user($user_general);

// Create some collections
$col_A = create_collection($userref, 'test_001903_A');
$col_B = create_collection($userref, 'test_001903_B');
$col_C = create_collection($userref, 'test_001903_C');

// Create some resources
$active_resources[] = create_resource(1, 0);
$active_resources[] = create_resource(1, 0);
$active_resources[] = create_resource(1, 0);
$active_resources[] = create_resource(1, 0);
$active_resources[] = create_resource(1, 0);
$deleted_resources[] = create_resource(1, 3);
$deleted_resources[] = create_resource(1, 3);
$confidential_resource = create_resource(1, 0);
if (!put_resource_data($confidential_resource, ['access' => RESOURCE_ACCESS_CONFIDENTIAL])) {
    return $fail_fct('Mark resource as confidential');
}

// Populate collections
$col_A_state = $col_B_state = $col_C_state = [];
$col_A_state = $add_ref_to_col_fct($active_resources[0], $col_A, $col_A_state);
$col_A_state = $add_ref_to_col_fct($active_resources[1], $col_A, $col_A_state);
$col_A_state = $add_ref_to_col_fct($active_resources[2], $col_A, $col_A_state);
$col_B_state = $add_ref_to_col_fct($active_resources[3], $col_B, $col_B_state);
$col_B_state = $add_ref_to_col_fct($deleted_resources[0], $col_B, $col_B_state);
$col_B_state = $add_ref_to_col_fct($deleted_resources[1], $col_B, $col_B_state);
$col_C_state = $add_ref_to_col_fct($confidential_resource, $col_C, $col_C_state);

############################
##### TEST START POINT #####
############################

// Use case: an admin can see all resources
$admin_count = get_collections_resource_count([$col_A, $col_B]);
$test_admin_count = array_filter($admin_count, function ($v) {
    return $v != 3;
});
if (!empty($test_admin_count)) {
    return $fail_fct('Test admin can see all resources (3 for each collection)');
}

// Use case: changes to the collection are immediately reflected in the response
$initial_admin_count = get_collections_resource_count([$col_A]);
add_resource_to_collection($active_resources[4], $col_A);
$admin_count = get_collections_resource_count([$col_A]);
remove_resource_from_collection($active_resources[4], $col_A);
if ($initial_admin_count[$col_A] == $admin_count[$col_A]) {
    return $fail_fct('Test change to collection will update the count');
}

setup_user($user_general_data);

// Use case: a general user should NOT count any resources in a private collection
$genuser_count = get_collections_resource_count([$col_A, $col_B]);
if (!empty($genuser_count)) {
    return $fail_fct('General user NOT counting resources in a private collection');
}

// Use case: a general user should only count active resources
add_collection($user_general, $col_B); # Attach general user to collection B
$genuser_count = get_collections_resource_count([$col_B]);
if (count($col_B_state) == $genuser_count[$col_B] || $genuser_count[$col_B] != 1) {
    return $fail_fct('General user counting only Active resources');
}

// Use case: a general user should NOT count confidential resources
add_collection($user_general, $col_C); # Attach general user to collection C
$genuser_count = get_collections_resource_count([$col_C]);
if (!($genuser_count[$col_C] != count($col_C_state) && $genuser_count[$col_C] == 0)) {
    return $fail_fct('General user NOT counting confidential resources');
}



// Tear down
$userdata = $original_user_data;
setup_user($original_user_data);

unset($fail_fct, $add_ref_to_col_fct);
unset($original_user_data, $user_general, $user_general_data);
unset($col_A, $col_B, $col_C, $col_A_state, $col_B_state, $col_C_state, $active_resources, $deleted_resources, $confidential_resource);
unset($admin_count, $test_admin_count, $initial_admin_count, $genuser_count);

return true;

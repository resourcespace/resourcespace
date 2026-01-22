<?php

/*
IMPORTANT: User edit filters are tested in 001901_edit_filters_updated
*/
command_line_only();


// Setup test
$original_user_data = $userdata;

$get_user_by_username = function ($n) {
    $username = $n;
    ps_value("SELECT ref AS `value` FROM user WHERE username = ?", array("s",$username), 0);
};
$user_admin = new_user("test_000411_admin", 3);
if ($user_admin === false) {
    $user_admin = $get_user_by_username("test_000411_admin");
}
$user_general = new_user("test_000411_general", 2);
if ($user_general === false) {
    $user_general = $get_user_by_username("test_000411_general");
}
if ($user_admin === 0 || $user_general === 0) {
    echo "Setup test: users - ";
    return false;
}
$user_admin_data = get_user($user_admin);
$user_general_data = get_user($user_general);

$resource_contributed_by_admin = create_resource(1, 0, $user_admin);
$resource_contributed_by_general = create_resource(1, 0, $user_general);
if ($resource_contributed_by_admin === false || $resource_contributed_by_general === false) {
    echo "Setup test: resources - ";
    return false;
}

setup_user($user_general_data);




// A user can always edit its own user resource template
if (!get_edit_access(0 - $user_general)) {
    echo "User template - ";
    return false;
}


// Check defaults - a general user normally has edit access to Pending submission
if (!get_edit_access($resource_contributed_by_general, -2)) {
    echo "Pending submission - ";
    return false;
}


// Check defaults - a general user normally has edit access to Pending review
if (!get_edit_access($resource_contributed_by_general, -1)) {
    echo "Pending review - ";
    return false;
}


// Check defaults - a general user normally doesn't have edit access to Active state
if (get_edit_access($resource_contributed_by_general, 0)) {
    echo "Active - ";
    return false;
}


// Use case - cannot edit in Pending submission/review if resource not contributed by user or not admin (t perm) and user
// doesn't have force edit access to the resource type (ert perm)
if (get_edit_access($resource_contributed_by_admin, -2)) {
    echo "Pending submission (resource contributed by admin) - ";
    return false;
}


// Use case - ability for general users to edit their contributions when not having access to Active state (e0 perm)
$edit_access_for_contributor = true;
if (!get_edit_access($resource_contributed_by_general, 0)) {
    echo "Edit access for contributor (no e0 perm) - ";
    return false;
}
// Sub use case - user should not be able to edit someone else's resource without e0 perm
if (get_edit_access($resource_contributed_by_admin, 0)) {
    echo "Edit access for contributor (no e0 perm) on someone else's resource - ";
    return false;
}


// Use case - ability for general users to edit only their contributions when having access to Active state (e0 perm)
$userpermissions[] = "e0";
$edit_access_for_contributor = true;
$edit_only_own_contributions = true;
if (!get_edit_access($resource_contributed_by_general, 0)) {
    echo "Edit access for contributor (e0 perm) - ";
    return false;
}
// Sub use case - user should not be able to edit someone else's resource even with e0 perm
if (get_edit_access($resource_contributed_by_admin, 0)) {
    echo "Edit access for contributor (e0 perm) on someone else's resource - ";
    return false;
}
array_pop($userpermissions);
$edit_access_for_contributor = false;
$edit_only_own_contributions = false;


// Use case - general users don't have edit access to resource type
$userpermissions[] = "XE1";
if (get_edit_access($resource_contributed_by_general, -2)) {
    echo "Deny edit access to resource type (XE1) - ";
    return false;
}
array_pop($userpermissions);


// Use case - general users don't have edit access to resource types
$userpermissions[] = "XE";
if (get_edit_access($resource_contributed_by_general, -2)) {
    echo "Deny edit access to all resource types (XE) - ";
    return false;
}
array_pop($userpermissions);


// Use case - general users have access blocked to Pending submission (z-2)
$userpermissions[] = "z-2";
if (get_edit_access($resource_contributed_by_general, -2)) {
    echo "Blocked access to workflow state 'Pending submission' (z-2) - ";
    return false;
}
array_pop($userpermissions);


// Use case - edit access if force edit access to resource type
$userpermissions[] = "ert1";
if (!get_edit_access($resource_contributed_by_general, 0)) {
    echo "Force edit access to resource type (ert1) - ";
    return false;
}
array_pop($userpermissions);




// Tear down
setup_user($original_user_data);
unset($user_admin);
unset($user_general);
unset($user_admin_data);
unset($user_general_data);
unset($get_user_by_username);
unset($resource_contributed_by_admin);
unset($resource_contributed_by_general);
$edit_access_for_contributor = false;
$edit_only_own_contributions = false;

return true;

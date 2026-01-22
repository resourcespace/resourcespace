<?php

command_line_only();


// Count existing users first
$existingusers = get_users("", "", "username", true, -1, 1);
$usercount = count($existingusers);

$user1 = new_user("user1");
$user2 = new_user("user2");
$user3 = new_user("user3");
$user4 = new_user("user4");

$_POST['password'] = generateSecureKey();
$_POST['fullname'] = "User One";

$_POST['approved'] = "1";
$_POST['username'] = "user1";
$_POST['email'] = "userone@dummy.resourcespace.com";
$_POST['usergroup'] = "1";
save_user($user1);

$_POST['approved'] = "1";
$_POST['username'] = "user2";
$_POST['email'] = "usertwo@dummy.resourcespace.com";
$_POST['usergroup'] = "2";
save_user($user2);

unset($_POST['approved']);
$_POST['username'] = "user3";
$_POST['email'] = "userthree@dummy.resourcespace.com";
$_POST['usergroup'] = "2";
save_user($user3);

$_POST['approved'] = "1";
$_POST['username'] = "user4";
$_POST['email'] = "userfour@dummy.resourcespace.com";
$_POST['usergroup'] = "2";
save_user($user4);

// Get only approved users
$approved_users = get_users("", "", "username", true, -1, 1);

if (count($approved_users) != $usercount + 3) {
    return false;
}

// Get all users
$all_users = get_users("", "", "username");

if (count($all_users) != count($approved_users) + 1) {
    return false;
}

// Get users in group 1
$group1_users = get_users(1, "", "username");

// Get users in group 2
$group2_users = get_users("2", "", "username");

// Get users in groups 1 and 2
$group1_plus_group2_users = get_users("1,2", "", "username");

if (count($group1_plus_group2_users) != (count($group1_users) + count($group2_users))) {
    echo "Initial test failed";
    return false;
}


// Testing config:  $usergroup_approval_mappings;

// Create the test groups and users
$setoptions = array(
    "name" => "Supervisor group",
    "permissions" => "s,z1,z2,z3,z4,g,q,f*,e-2,e-1,d,n,j*,t,r,R,u",
    );
$group_supervisor = save_usergroup(0, $setoptions);

$supervisor = new_user("supervisor");
$_POST['fullname'] = "Supervisor";
$_POST['approved'] = "1";
$_POST['username'] = "supervisor";
$_POST['email'] = "supervisor@dummy.resourcespace.com";
$_POST['usergroup'] = $group_supervisor;
save_user($supervisor);

$setoptions = array(
    "name" => "Subordinate group",
    "permissions" => "s,z1,z2,z3,g,q,f*,e-2,e-1,d,n,j*",
    );
$group_subordinate = save_usergroup(0, $setoptions);

$subordinate = new_user("subordinate");
$_POST['fullname'] = "Subordinate";
$_POST['approved'] = "1";
$_POST['username'] = "subordinate";
$_POST['email'] = "subordinate@dummy.resourcespace.com";
$_POST['usergroup'] = $group_subordinate;
save_user($subordinate);

$setoptions = array(
    "name" => "Independent group",
    "permissions" => "s,z1,z2,z3,z4,g,q,f*,e-2,e-1,d,n,j*,t,r,R,u",
    );
$group_independent = save_usergroup(0, $setoptions);

$independent = new_user("independent");
$_POST['fullname'] = "Independent";
$_POST['approved'] = "1";
$_POST['username'] = "independent";
$_POST['email'] = "independent@dummy.resourcespace.com";
$_POST['usergroup'] = $group_independent;
save_user($independent);

// Setup config
$usergroup_approval_mappings = array($group_supervisor => array($group_subordinate));

$usergroup_before_test = $usergroup;

// Test 1 - is the config applied for specified groups
$usergroup = $group_supervisor;
$users_found_from_config = get_users(0, "", "u.username", true);
if (count($users_found_from_config) != 2) {
    echo "Test 1 failed";
    return false;
}  // Failure if any extra users are returned.

// Test 2
$filtered_users = get_notification_users($userpermission = "USER_ADMIN", $group_independent);
if (in_array($supervisor, array_column($filtered_users, 'ref'))) {
    echo "Test 2 failed";
    return false;
}  // Supervisor user should not be returned due to config filter.

// Test 3 - check config doesn't affect other groups
$usergroup = $group_independent;
$users_found_no_config = get_users(0, "", "u.username", true);
if (count($users_found_no_config) == 2) {
    echo "Test 3 failed.";
    return false;
}  // Failure if only filtered users are returned.

unset($usergroup_approval_mappings);
unset($notification_users_cache);

// Test 4
$filtered_users = get_notification_users($userpermission = "USER_ADMIN", $group_independent);
if (!in_array($supervisor, array_column($filtered_users, 'ref'))) {
    echo "Test 4 failed";
    return false;
}  // Supervisor user should now be returned as config removed.

// Tear down
$usergroup = $usergroup_before_test;


return true;

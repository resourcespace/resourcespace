<?php

command_line_only();

include_once __DIR__ . "/../../include/action_functions.php";
include_once __DIR__ . "/../../include/request_functions.php";

// Save settings
$saved_timezone = date_default_timezone_get();
$saved_new_action_email_interval = $new_action_email_interval;

// Clean out old data
ps_query("TRUNCATE resource");
ps_query("TRUNCATE user");
ps_query("TRUNCATE request");

// set up environment
$new_action_email_interval = 1;
unset($resource_type_request_emails);
$send_default_notifications = false;

// Set up test users

// Admin user A
$adminusera = new_user("actionuser005100_a");
$emailaddress = "adminuser005100_a@test.resourcespace.com";
$usergroup  = 3;
$approved   = 1;
$params = ["i",$approved,"s",$emailaddress,"i",$usergroup,"s",$language,"i",$adminusera];
ps_query("UPDATE user SET approved = ?,email = ?,usergroup = ?,lang = ? WHERE ref = ?", $params);

// Admin user B
$adminuserb = new_user("actionuser005100_b");
$emailaddress = "adminuser005100_b@test.resourcespace.com";
$usergroup  = 3;
$approved   = 1;
$params = ["i",$approved,"s",$emailaddress,"i",$usergroup,"s",$language,"i",$adminuserb];
ps_query("UPDATE user SET approved = ?,email = ?,usergroup = ?,lang = ? WHERE ref = ?", $params);

// General user
$generaluser = new_user("generaluser005100_2");
$emailaddress = "actionuser005100_1@test.resourcespace.com";
$usergroup  = 2;
$approved   = 1;
$params = ["i",$approved,"s",$emailaddress,"i",$usergroup,"s",$language,"i",$generaluser];
ps_query("UPDATE user SET approved = ?,email = ?,usergroup = ?,lang = ? WHERE ref = ?", $params);


// Test A - Get resource review actions - admin user
$adminbuserdata = get_user($generaluser);
setup_user($adminbuserdata);
$resourcea = create_resource(1, 0, $adminuserb);
$resourceb = create_resource(1, -1, $adminuserb);
$resourcec = create_resource(2, 0, $adminuserb);
$resourced = create_resource(2, -1, $adminuserb);
$resourcee = create_resource(3, -1, $adminuserb);

set_config_option($adminusera, "user_pref_new_action_emails", "1");
set_config_option($adminusera, "actions_resource_review", "1");
set_config_option($adminusera, "actions_notify_states", "-1");
set_config_option($adminusera, "actions_resource_types_hide", "2");
$actions = get_user_actions_recent(5, true);
if (
    !isset($actions[$adminusera]["resourcereview"]) ||
    !match_values(array_column($actions[$adminusera]["resourcereview"], "ref"), [$resourceb, $resourcee])
) {
    echo "Test A - failed to get correct actions" . PHP_EOL;
    echo print_r($actions);
    return false;
}

// Test B - Get resource review actions - standard user
$genuserdata = get_user($generaluser);
setup_user($genuserdata);
$resourcef = create_resource(1, -1, $generaluser);
$resourceg = create_resource(1, -1, $generaluser);
$resourceh = create_resource(2, -1, $generaluser);
$resourcei = create_resource(2, -1, $generaluser);
$resourcej = create_resource(3, -1, $generaluser);
set_config_option($generaluser, "user_pref_new_action_emails", "1");
set_config_option($generaluser, "actions_resource_review", "1");
set_config_option($generaluser, "actions_notify_states", "-2");
set_config_option($generaluser, "actions_resource_types_hide", "2");
$actions = get_user_actions_recent(5, true);
if (isset($actions[$generaluser]["resourcereview"])) {
    echo "Test B - failed to get correct actions" . PHP_EOL;
    echo print_r($actions);
    return false;
}

// Test C - move resources contributed by $generaluser back to pending submission
$adminauserdata = get_user($adminusera);
setup_user($adminauserdata);
update_archive_status($resourcef, -2);
update_archive_status($resourceh, -2);
update_archive_status($resourcej, -2);
$actions = get_user_actions_recent(5, true);
if (
    !isset($actions[$generaluser]["resourcereview"]) ||
    !match_values(array_column($actions[$generaluser]["resourcereview"], "ref"), [$resourcef, $resourcej])
) {
    echo "Test C - failed to get correct actions" . PHP_EOL;
    echo print_r($actions);
    return false;
}


// Test D - check resource requests
ps_query("UPDATE resource SET access=1 WHERE ref IN (?,?)", ["i",$resourcea,"i",$resourcec]);
$genuserdata = get_user($generaluser);
setup_user($genuserdata);
$col1 = create_collection($generaluser, "test_005100", 1);
add_resource_to_collection($resourcea, $col1);
$request = managed_collection_request($col1, "Test request", false);
// Get actions only for admin user
$adminuseradata = get_user($adminusera);
setup_user($adminuseradata);
$actions = get_user_actions_recent(5, false);
if (
    !isset($actions[$adminusera]["resourcerequest"]) ||
    isset($actions[$generaluser]) ||
    !match_values(array_column($actions[$adminusera]["resourcerequest"], "ref"), [$request])
) {
    echo "Test D - failed to get resourcerequest action" . PHP_EOL;
    echo print_r($actions);
    return false;
}

// Test E - check user account request
$userrequest = new_user("newuser_005100");
ps_query("UPDATE user set approved=0 WHERE ref=?", ["i",$userrequest]);
// Get actions only for admin user
$actions = get_user_actions_recent(5, false);
if (
    !isset($actions[$adminusera]["userrequest"]) ||
    !match_values(array_column($actions[$adminusera]["userrequest"], "ref"), [$userrequest])
) {
    echo "Test E - failed to get userrequest action" . PHP_EOL;
    echo print_r($actions);
    return false;
}

// Cleanup
$new_action_email_interval = $saved_new_action_email_interval;

return true;

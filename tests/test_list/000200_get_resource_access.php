<?php

command_line_only();


// Save userref for the end as we need to set user back to this one
$original_user_ref = $userref;

// Active resource - un-restricted
$resource_unrestricted_access = create_resource(1, 0);
update_archive_status($resource_unrestricted_access, 0);

// Archived resource - restricted access
$additional_archive_states = array(4);
$lang['status4'] = "Test state";
$resource_restricted_access = create_resource(1, 0);
update_archive_status($resource_restricted_access, 4);


$use_cases = array(
    array(
        'description' => 'Un-restricted resource',
        'resource' => $resource_unrestricted_access,
        'add_permissions' => array('rws2'),
        'assertion' => 0
    ),
    array(
        'description' => 'Restricted resource to workflow state (rws2)',
        'resource' => $resource_restricted_access,
        'add_permissions' => array('rws4'),
        'assertion' => 1
    ),
);


// Get a user ready
$user_000410 = new_user("user_000410");
$_POST['username'] = "user_000410";
$_POST['password'] = generateSecureKey();
$_POST['usergroup'] = 2;
$_POST['fullname'] = "User 000410";
$_POST['email'] = "user_000410@unit-test.resourcespace.com";
$_POST['approved'] = "1";
save_user($user_000410);
unset($udata_cache);
setup_user(get_user($user_000410));

$default_user_000410_permissions = $userpermissions;
$GLOBALS['get_resource_data_cache'] = [];
for ($i = 0; $i < count($use_cases); $i++) {
    $resource = $use_cases[$i]['resource'];
    $assertion = $use_cases[$i]['assertion'];

    // Change permissions per use case
    $userpermissions = $default_user_000410_permissions;
    if (!empty($use_cases[$i]['add_permissions'])) {
        $userpermissions = array_merge($userpermissions, $use_cases[$i]['add_permissions']);
    }

    // Actual assertion
    $resource_access = get_resource_access($resource);
    if ($resource_access == $assertion) {
        continue;
    }

    echo "Use case #{$i}: assertion {$resource_access} == {$assertion} - ";
    return false;
}

// Cleaning and resetting unit tests environment
unset($udata_cache);
setup_user(get_user($original_user_ref));

return true;

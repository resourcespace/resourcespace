<?php

command_line_only();

// Save current settings
$saved_edit_filter = $usereditfilter;
$saved_user = $userref;

// Create unique permission string
$testperm = "perm" . uniqid();

// Create new groups
$setoptions = array("permissions" => $testperm . ",u,U,t,s","permadmin", "name" => "1202adminrestricted");
$parentgroupa = save_usergroup(0, $setoptions);

$setoptions = array("permissions" => $testperm . ",u,t,s","permadmin", "name" => "1202adminunrestricted");
$parentgroupb = save_usergroup(0, $setoptions);

$setoptions = array("permissions" => $testperm . ",s", "name" => "1202child", "parent" => $parentgroupa);
$childgroupa = save_usergroup(0, $setoptions);

// Create users
$_POST['password'] = generateSecureKey();

$admina = new_user("1202admina");
$_POST['fullname'] = "Admin A";
$_POST['approved'] = "1";
$_POST['username'] = "1202admina";
$_POST['email'] = "1202admina@dummy.resourcespace.com";
$_POST['usergroup'] = $parentgroupa;
save_user($admina);

$adminb = new_user("1202adminb");
$_POST['fullname'] = "Admin B";
$_POST['approved'] = "1";
$_POST['username'] = "1202adminb";
$_POST['email'] = "1202adminb@dummy.resourcespace.com";
$_POST['usergroup'] = $parentgroupb;
save_user($adminb);
$adminbdata = get_user($adminb);

$childa = new_user("1202childa");
$_POST['fullname'] = "Child A";
$_POST['approved'] = "1";
$_POST['username'] = "1202childa";
$_POST['email'] = "1202childa@dummy.resourcespace.com";
$_POST['usergroup'] = $childgroupa;
save_user($childa);

// Clear cache
$udata_cache = [];

// Test A Get all users with $testperm permission, current and child groups
$adminadata = get_user($admina);
setup_user($adminadata);
$result = get_users_by_permission([$testperm]);
if (!is_array($result) || !match_values(array_column($result, 'ref'), array($admina, $childa))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Test C Get all users with $testperm permission, no restriction
$adminbdata = get_user($adminb);
setup_user($adminbdata);
$result = get_users_by_permission([$testperm]);
if (!is_array($result) || !match_values(array_column($result, 'ref'), [$admina, $adminb, $childa])) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// Test D Get all users with a set of permissions, no restriction
$result = get_users_by_permission([$testperm,"u","U","t","s"]);
if (!is_array($result) || !match_values(array_column($result, 'ref'), [$admina])) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

// Test E Get all users with $testperm as standard user (no results)
$childadata = get_user($childa);
setup_user($childadata);
$result = get_users_by_permission([$testperm]);
if (!empty($result)) {
    echo "ERROR - SUBTEST E\n";
    return false;
}

// Reset saved settings
$usereditfilter = $saved_edit_filter;
$userdata = get_user($saved_user);
setup_user($userdata);

return true;

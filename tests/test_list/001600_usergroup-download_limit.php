<?php

// Validate that usergroup download_limit functions
command_line_only();

ps_query("TRUNCATE resource");
ps_query("TRUNCATE resource_log");

// Set up group with limit
$usergroup_values = array(
    'name' => 'Limited group',
    'permissions' => 's,e-1,e-2,g,d,q,n,f*,j*,z1,z2,z3',
    'download_limit' => 3,
    'download_log_days' => 10
);
$groupref = save_usergroup(0, $usergroup_values);

// Create a user
$limiteduser = new_user("limiteduser", $groupref);
$limiteduserdata = get_user($limiteduser);

// Create dummy resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(2, 0);
$resourced = create_resource(2, 0);

$original_user_data = $userdata;
setup_user($limiteduserdata);

// Add fake downloads to log
resource_log($resourcea, LOG_CODE_DOWNLOADED, 0, 'TEST1');
resource_log($resourceb, LOG_CODE_DOWNLOADED, 0, 'TEST2');

// Call resource_download_allowed() - should return true
if (!resource_download_allowed($resourced, "", 1, -1)) {
    echo "ERROR - SUBTEST A ";
    return false;
}

resource_log($resourcec, LOG_CODE_DOWNLOADED, 0, 'TEST3');

// Call get_user_downloads()  - should return 3
$downloads = get_user_downloads($limiteduser, 10);
if ($downloads != 3) {
    echo "ERROR - SUBTEST B . Downloads: $downloads, should be 2 ";
    return false;
}

// Call resource_download_allowed() again - should return false
if (resource_download_allowed($resourced, "", 1, -1)) {
    echo "ERROR - SUBTEST C ";
    return false;
}

// Move the downloads to 11 days ago so downloads should now be allowed
ps_query("UPDATE resource_log SET date = DATE_SUB(date, INTERVAL 11 DAY) WHERE type=?", array("s",LOG_CODE_DOWNLOADED));

// Call resource_download_allowed() once again - should now return true
if (!resource_download_allowed($resourced, "", 1, -1)) {
    echo "ERROR - SUBTEST D ";
    return false;
}


// Reset as the primary test user
setup_user($original_user_data);

return true;

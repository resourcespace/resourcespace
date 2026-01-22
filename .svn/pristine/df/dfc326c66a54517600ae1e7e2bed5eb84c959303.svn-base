<?php

command_line_only();


// --- Set up
$run_id = test_generate_random_ID(10);
$original_user_data = $userdata;

$user_with_T_perms = get_user(
    new_user(
        "test_420-{$run_id}",
        save_usergroup(0, [
            "name" => "Group for test_420-{$run_id}",
            "permissions" => 's,e-1,e-2,g,d,q,n,f*,j*,z1,z2,z3,T1_scr',
        ])
    )
);
$resource = create_resource(1, 0);
// --- End of Set up



setup_user($user_with_T_perms);
if (resource_download_allowed($resource, 'scr', 1, -1) !== false) {
    echo "Use case: Prevent download when 'T?_?' permission set on size (scr) - ";
    return false;
}



// Tear down
setup_user($original_user_data);
unset($original_user_data, $run_id, $user_with_T_perms, $resource);

return true;

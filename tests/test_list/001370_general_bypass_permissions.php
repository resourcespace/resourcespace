<?php

command_line_only();


$original_user_data = $userdata;

$gen_user_010001      = new_user("gen_user_010001", 2);
$gen_user_010001_data = get_user($gen_user_010001);
setup_user($gen_user_010001_data);

$fn_v_required = function () {
    if (!checkperm("v")) {
        return false;
    }

    return true;
};

// If we fail to bypass the permission required by the function, fail the test.
if (!bypass_permissions(array("v"), $fn_v_required)) {
    echo "Bypass specific permission - ";
    return false;
}

// Check that the global $userpermissions variable hasn't been poluted with the bypassed permissions
if ($fn_v_required()) {
    echo "\$userpermissions untouched by bypass_permissions() - ";
    return false;
}


// Tear down
setup_user($original_user_data);
unset($fn_v_required);

return true;

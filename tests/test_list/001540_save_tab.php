<?php

if ('cli' != PHP_SAPI) {
    exit('This utility is command line only.');
}

// Set up
$orig_userpermissions = $userpermissions;
$tab = create_tab(['name' => '10503_Tab']);



// Users without permission to manage tabs shouldn't be able to save existing ones
$userpermissions = array_diff($userpermissions, ['a']);
$save_status = save_tab(['ref' => $tab, 'name' => '10503_Tab (unauthorised save)']);
$userpermissions = $orig_userpermissions;
if ($save_status) {
    echo 'Save tab (unauthorised) - ';
    return false;
}


// Save a tab with missing required information
$GLOBALS['use_error_exception'] = true;
try {
    save_tab(['name' => 'Missing ref']);
} catch (Throwable $t) {
    $error_triggered = true;
}
$GLOBALS['use_error_exception'] = false;
if (!isset($error_triggered)) {
    echo 'Save tab with missing required information - ';
    return false;
}


// Edit an exiting tab
$new_tab_name = '10503_Tab modified';
$save_status = save_tab(['ref' => $tab, 'name' => $new_tab_name]);
$found = get_tabs_by_refs([$tab])[0]['name'] ?? '';
if ($save_status && $found !== $new_tab_name) {
    echo 'Save tab - ';
    return false;
}



// Tear down
unset($tab, $new_tab_name, $save_status, $found);

return true;

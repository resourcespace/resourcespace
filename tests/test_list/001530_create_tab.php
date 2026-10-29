<?php

if ('cli' != PHP_SAPI) {
    exit('This utility is command line only.');
}

// Set up
$orig_userpermissions = $userpermissions;


// Users without permission to manage tabs shouldn't be able to create new ones
$userpermissions = array_diff($userpermissions, ['a']);
$tab = create_tab(['name' => '10502_Tab (unauthorised)']);
$userpermissions = $orig_userpermissions;
$found = ps_value('SELECT ref AS `value` FROM tab WHERE ref = ?', ['i', $tab], 0);
if ($found > 0) {
    echo 'Create tab (unauthorised) - ';
    return false;
}


// Simply create a new tab
$tab = create_tab(['name' => '10502_Tab']);
if ($tab === false) {
    echo 'Create tab - ';
    return false;
}


// Create a tab missing the required information
$invalid_tab = create_tab([]);
if ($invalid_tab !== false) {
    echo 'Create tab (missing required info) - ';
    return false;
}


// Tear down
unset($tab, $invalid_tab, $found);

return true;

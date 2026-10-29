<?php

command_line_only();



// Set up
$test_1407_user = new_user('test_1407', 2) ?: get_user_by_username('test_1407');
$A = create_collection($userref, 'test_1407 collection A');
$B = create_collection($userref, 'test_1407 collection B');
$C = create_collection($test_1407_user, 'test_1407 collection C');
$all = [$A, $B, $C];



// Check a Super Admin sees their collections when searching for one
$user_cols = array_column(get_user_collections($userref, 'test_1407', 'created', 'ASC'), 'ref');
if (empty(array_intersect([$A, $B], $user_cols))) {
    echo 'Get user collections as Super Admin (default user) - ';
    return false;
}


// Check from a general user perspective. It should behave the same.
$user_cols = array_column(get_user_collections($test_1407_user, 'test_1407', 'created', 'ASC'), 'ref');
if (!in_array($C, $user_cols)) {
    echo 'Get general user collections - ';
    return false;
}
// Users should only be seeing their own collections
elseif ($all === array_intersect($all, $user_cols)) {
    echo "Get only this users' collections - ";
    return false;
}



// Tear down
unset($A, $B, $C, $all, $user_cols);

return true;

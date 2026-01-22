<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Started upgrade script - update "user" table, column "password" ...');

$notification_users = array_column(get_notification_users('SYSTEM_ADMIN'), 'ref');

$user_tinfo = ps_query('DESCRIBE user');
if (!is_array($user_tinfo)) {
    $msg = '[error] Unable to describe table "user"';
    logScript($msg);
    message_add($notification_users, "Upgrade script 016: {$msg}", '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, MESSAGE_DEFAULT_TTL_SECONDS);
    return;
}

$user_tinfo = array_column($user_tinfo, 'Type', 'Field');

preg_match('/varchar\((\d+)\)/', $user_tinfo['password'], $matches);
if (empty($matches)) {
    $msg = '[warn] Missing expected varchar length for password column!';
    logScript($msg);
    message_add($notification_users, "Upgrade script 016: {$msg}", '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, MESSAGE_DEFAULT_TTL_SECONDS);
    return;
}

$password_column_len = $matches[1];
if ($password_column_len < 255) {
    logScript('Password column needs updating. Calling check_db_structs');
    check_db_structs(false);
} else {
    logScript('Password column doesn\'t need updating. Skipping upgrade level.');
    return;
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Successfully updated "user" table as per dbstruct definition!');

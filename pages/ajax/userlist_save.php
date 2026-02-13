<?php

# Feeder page for AJAX user/group search for the user selection include file

include "../../include/boot.php";
include "../../include/authenticate.php";

if (is_anonymous_user()) {
    exit(escape($lang['error-permissiondenied']));
}

$user = getval("userref", "");
$userstring = getval("userstring", "");
$userlistname = getval("userlistname", "");
$delete = getval("delete", "");

if ($delete != "") {
    $userlistref = getval("userlistref", "", true);
    ps_query("delete from user_userlist where ref= ?", ['i', $userlistref]);
}

if ($userstring != "" && $userstring != $lang['typeauserlistname'] && $userlistname != "") {
    ps_query("delete from user_userlist where user= ? and userlist_name= ?", ['i', $user, 's', $userlistname]);
    ps_query("insert into user_userlist (user,userlist_name,userlist_string) values (?, ?, ?)", ['i', $user, 's', $userlistname, 's', $userstring]);
}

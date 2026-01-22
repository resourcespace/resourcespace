<?php
# Feeder page for AJAX user/group search for the user selection include file.

include "../../include/boot.php";
include "../../include/authenticate.php";

$find = getval("term", "");
$getrefs = (getval("getrefs", "") != "") ? true : false;
$getuserref = (getval("getuserref", ""));

if (is_anonymous_user()) {
    exit(escape($lang['error-permissiondenied']));
}

if (!empty($getuserref)) {
    ob_clean();
    echo ps_value("select max(ref) as value from user where username=?", array("s",$getuserref), '');
    return;
}

$usersgroup_subordinates = get_approver_usergroups($usergroup);
$usersgroup_approvers = get_usergroup_approvers($usergroup);

$ignoregroups = (getval("nogroups", "") != "") ? true : false;
$ignoresmartgroups = (getval("nosmartgroups", "") != "") ? true : false;

$first = true;
header('Content-Type: application/json');
?> [ <?php

if (!$ignoregroups) {
    $groups = get_usergroups(true, $find);

    for ($n = 0; $n < count($groups) && $n <= 20; $n++) {
        $show = true;
        if (
            checkperm("E") && ($groups[$n]["ref"] != $usergroup) && ($groups[$n]["parent"] != $usergroup) && ($groups[$n]["ref"] != $usergroupparent)
            && !in_array($groups[$n]["ref"], $usersgroup_approvers) && (!in_array($groups[$n]["ref"], $usersgroup_subordinates))
        ) {
            $show = false;
        }
        if ($show) {
            $users = get_users($groups[$n]["ref"]);
            $ulist = "";

            for ($m = 0; $m < count($users); $m++) {
                if ($ulist != "") {
                    $ulist .= ", ";
                }
                $ulist .= $users[$m]["username"];
            }

            if ($ulist != "") {
                if (!$first) {
                    ?>, <?php
                }
                $first = false;

                ?>{ "label": "<?php echo escape($lang["group"]); ?>: <?php echo $groups[$n]["name"]; ?>", "value": "<?php echo escape($lang["group"]); ?>: <?php echo $groups[$n]["name"]; ?>" <?php if ($getrefs) {
    ?>,  "ref": "<?php echo $groups[$n]["ref"]; ?>"<?php
                }?> }<?php
            }
        }
    }
}

if (!$ignoregroups && !$ignoresmartgroups) {
    if (!isset($groups)) {
        $groups = get_usergroups(true, $find);
    }
    for ($n = 0; $n < count($groups) && $n <= 20; $n++) {
            $show = true;
        if (
            checkperm("E") && ($groups[$n]["ref"] != $usergroup) && ($groups[$n]["parent"] != $usergroup) && ($groups[$n]["ref"] != $usergroupparent)
            && !in_array($groups[$n]["ref"], $usersgroup_approvers) && (!in_array($groups[$n]["ref"], $usersgroup_subordinates))
        ) {
            $show = false;
        }
        if ($show) {
            $users = get_users($groups[$n]["ref"]);
            $ulist = "";

            for ($m = 0; $m < count($users); $m++) {
                if ($ulist != "") {
                    $ulist .= ", ";
                }
                $ulist .= $users[$m]["username"];
            }

            if ($ulist != "") {
                if (!$first) {
                    ?>, <?php
                }
                $first = false;

                ?>{ "label": "<?php echo escape($lang["groupsmart"]); ?>: <?php echo $groups[$n]["name"]; ?>", "value": "<?php echo escape($lang["groupsmart"]); ?>: <?php echo $groups[$n]["name"]; ?>" <?php if ($getrefs) {
    ?>,  "ref": "<?php echo $groups[$n]["ref"]; ?>"<?php
                }?> }<?php
            }
        }
    }
}

    $users = get_users(0, $find);
for ($n = 0; $n < count($users) && $n <= 20; $n++) {
    $show = true;
    if (
        checkperm("E") && ($users[$n]["groupref"] != $usergroup) && ($users[$n]["groupparent"] != $usergroup) && ($users[$n]["groupref"] != $usergroupparent)
        && !in_array($users[$n]["groupref"], $usersgroup_approvers) && (!in_array($users[$n]["groupref"], $usersgroup_subordinates))
    ) {
        $show = false;
    }
    if ($show) {
        if (!$first) {
            ?>, <?php
        }
        $first = false;

        ?>{ "label": "<?php echo $users[$n]["fullname"]; ?>", "value": "<?php echo $users[$n]["username"]; ?>" <?php if ($getrefs) {
    ?>,  "ref": "<?php echo $users[$n]["ref"]; ?>"<?php
        }?> } <?php
    }
}


?> ]

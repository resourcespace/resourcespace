<?php

// *******************************************************************************
//
//   Legacy filtering - to be moved to migration script (q13255)
//
// *******************************************************************************

if (
    strlen(trim((string) $usersearchfilter)) > 0
    && !is_numeric($usersearchfilter)
    && (
        (isset($userdata[0]["search_filter_override"]) && trim($userdata[0]["search_filter_override"]) != ""
            && isset($userdata[0]["search_filter_o_id"]) && $userdata[0]["search_filter_o_id"] != -1)
        ||
        (isset($userdata[0]["search_filter"]) && trim($userdata[0]["search_filter"]) != ""
            && isset($userdata[0]["search_filter_id"]) && $userdata[0]["search_filter_id"] != -1)
        )
) {
    // Migrate old style filter unless previously failed attempt
    $migrateresult = migrate_filter($usersearchfilter);
    $notification_users = get_notification_users();
    if (is_numeric($migrateresult)) {
        message_add(array_column($notification_users, "ref"), $lang["filter_migrate_success"] . ": '" . $usersearchfilter . "'", generateURL($baseurl . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));

        // Successfully migrated - now use the new filter
        if (isset($userdata[0]["search_filter_override"]) && $userdata[0]["search_filter_override"] != '') {
            // This was a user override filter - update the user record
            ps_query("UPDATE user SET search_filter_o_id = ? WHERE ref = ?", ["i",$migrateresult,"i",$userref]);
        } else {
            save_usergroup($usergroup, array('search_filter_id' => $migrateresult));
        }
        $usersearchfilter = $migrateresult;
        debug("FILTER MIGRATION: Migrated filter - new filter id#" . $usersearchfilter);
    } elseif (is_array($migrateresult)) {
        debug("FILTER MIGRATION: Error migrating filter: '" . $usersearchfilter . "' - " . implode('\n', $migrateresult));
        // Error - set flag so as not to reattempt migration and notify admins of failure
        if (isset($userdata[0]["search_filter_override"]) && $userdata[0]["search_filter_override"] != '') {
            ps_query("UPDATE user SET search_filter_o_id='-1' WHERE ref = ?", ["i",$userref]);
        } else {
            save_usergroup($usergroup, array('search_filter_id' => -1));
        }

        message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br />" . implode('\n', $migrateresult), generateURL($baseurl . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
    }
}
// Old text filters are no longer supported
if (is_int_loose($usersearchfilter) && $usersearchfilter > 0) {
    $search_filter_sql = get_filter_sql($usersearchfilter);
    if (!$search_filter_sql) {
        exit($lang["error_search_filter_invalid"]);
    }
    if (is_a($search_filter_sql, "PreparedStatementQuery")) {
        if ($sql_filter->sql != "") {
            $sql_filter->sql .= " AND ";
        }
        $sql_filter->sql .=  $search_filter_sql->sql;
        $sql_filter->parameters = array_merge($sql_filter->parameters, $search_filter_sql->parameters);
    }
}

if ($editable_only) {
    if (
        strlen(trim($usereditfilter ?? "")) > 0
        && !is_numeric($usereditfilter)
        && trim($userdata[0]["edit_filter"]) != ""
        && $userdata[0]["edit_filter_id"] != -1
    ) {
        // Migrate unless marked not to due to failure
        $usereditfilter = edit_filter_to_restype_permission($usereditfilter, $usergroup, $userpermissions);
        if (trim($usereditfilter) !== "") {
            $migrateresult = migrate_filter($usereditfilter);
        } else {
            $migrateresult = 0; // filter was only for resource type, hasn't failed but no need to migrate again
            save_usergroup($usergroup, array('edit_filter' => ''));
        }
        if (is_numeric($migrateresult)) {
            debug("Migrated . " . $migrateresult);
            // Successfully migrated - now use the new filter
            save_usergroup($usergroup, array('edit_filter_id' => $migrateresult));
            debug("FILTER MIGRATION: Migrated edit filter - '" . $usereditfilter . "' filter id#" . $migrateresult);
            $usereditfilter = $migrateresult;
        } elseif (is_array($migrateresult)) {
            debug("FILTER MIGRATION: Error migrating filter: '" . $usersearchfilter . "' - " . implode('\n', $migrateresult));
            // Error - set flag so as not to reattempt migration and notify admins of failure
            save_usergroup($usergroup, array('edit_filter_id' => -1));
            $notification_users = get_notification_users();
            message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br />" . implode('\n', $migrateresult), generateURL($baseurl . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
        }
    }

    if (is_numeric($usereditfilter) && $usereditfilter > 0) {
        $edit_filter_sql = get_filter_sql($usereditfilter);
        if (is_a($edit_filter_sql, "PreparedStatementQuery")) {
            if ($sql_filter->sql != "") {
                $sql_filter->sql .= " AND ";
            }
            $sql_filter->sql .=  $edit_filter_sql->sql;
            $sql_filter->parameters = array_merge($sql_filter->parameters, $edit_filter_sql->parameters);
        }
    }
}

<?php

include_once __DIR__ . "/../../include/migration_functions.php";

if (!isset($sysvars["EDIT_FILTER_MIGRATION"]) || $sysvars["EDIT_FILTER_MIGRATION"] == 0) {
    $notification_users = get_notification_users();
    $groups = ps_query("SELECT ref, name,edit_filter, derestrict_filter, permissions FROM usergroup");
    foreach ($groups as $group) {
        foreach (array("edit_filter","derestrict_filter") as $filtertype) {
            $filtertext = trim((string) $group[$filtertype]);
            if ($filtertext == "") {
                continue;
            }

            if ($filtertype == "edit_filter") {
                $filtertext = edit_filter_to_restype_permission($filtertext, $group["ref"], explode(",", $group["permissions"]));
            }

            $migrateresult = migrate_filter($filtertext);
            if (is_numeric($migrateresult)) {
                message_add(array_column($notification_users, "ref"), $lang["filter_migrate_success"] . ": '" . $filtertext . "'", generateURL($baseurl_short . "pages/admin/admin_group_management_edit.php", array("ref" => $group["ref"])));
                // Successfully migrated - now use the new filter
                save_usergroup($group['ref'], array($filtertype . '_id' => $migrateresult));
            } elseif (is_array($migrateresult)) {
                debug("FILTER MIGRATION: Error migrating filter: '" . $filtertext . "' - " . implode('\n', $migrateresult));
                // Error - set flag so as not to reattempt migration and notify admins of failure to be sorted manually
                save_usergroup($group['ref'], array($filtertype . '_id' => 0));
                message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br />" . implode('\n', $migrateresult), generateURL($baseurl_short . "pages/admin/admin_group_management_edit.php", array("ref" => $group["ref"])));
            }
        }
    }
    set_sysvar("EDIT_FILTER_MIGRATION", 1);
}

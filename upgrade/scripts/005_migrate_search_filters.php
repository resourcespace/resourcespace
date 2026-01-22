<?php

include_once __DIR__ . "/../../include/migration_functions.php";


// ---------------------------------------------------------------------------------------------------------------------
// To migrate filters which contain invalid values run with the option --force
// i.e. command line access: php 005_migrate_search_filters.php --force
//      web access         : migrate/scripts/005_search_filters.php?force=true
//
// This option will create the filter if at least one condition can find a node value.
// If no values are found the filter migration will still fail.
// Only filters that have failed migration will be processed in this case.
// ---------------------------------------------------------------------------------------------------------------------

if (PHP_SAPI != 'cli') {
    $allowpartialmigration = getval('force', false);
    if ($allowpartialmigration) {
        // Only allow admin users to do force filter creation because the filters should be checked manually first
        include_once __DIR__ . "/../../include/authenticate.php";
        if (!checkperm('v')) {
            exit("permission denied.");
        }
        include_once __DIR__ . "/../../include/boot.php";
    }
} else {
    include_once __DIR__ . "/../../include/boot.php";
    $cli_long_options  = array('force');
    $allowpartialmigration = false;
    foreach (getopt('', $cli_long_options) as $option_name => $option_value) {
        if ($option_name == 'force') {
            $allowpartialmigration = true;
        }
    }
}

if (!isset($sysvars["SEARCH_FILTER_MIGRATION"]) || $sysvars["SEARCH_FILTER_MIGRATION"] == 0 || $allowpartialmigration) {
    $notification_users = get_notification_users();
    $groups_sql = "SELECT ref, name,search_filter FROM usergroup WHERE ";
    if ($allowpartialmigration) {
        $groups_sql .= 'search_filter_id=-1';
    } else {
        $groups_sql .= "search_filter_id IS NULL OR search_filter_id=0";
    }
    $groups = ps_query($groups_sql);
    foreach ($groups as $group) {
        $filtertext = trim((string)$group["search_filter"]);
        if ($filtertext == "") {
            continue;
        }

        // Migrate unless marked not to due to failure (flag will be reset if group is edited)
        $migrateresult = migrate_filter($filtertext, $allowpartialmigration);
        if (is_numeric($migrateresult)) {
            message_add(array_column($notification_users, "ref"), $lang["filter_migrate_success"] . ": '" . $filtertext . "'", generateURL($baseurl_short . "pages/admin/admin_group_management_edit.php", array("ref" => $group["ref"])));
            // Successfully migrated - now use the new filter
            save_usergroup($group['ref'], array('search_filter_id' => $migrateresult));
        } elseif (is_array($migrateresult)) {
            debug("FILTER MIGRATION: Error migrating filter: '" . $filtertext . "' - " . implode('\n', $migrateresult));
            // Error - set flag so as not to reattempt migration and notify admins of failure
            save_usergroup($group['ref'], array('search_filter_id' => -1));

            message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br />" . implode('\n', $migrateresult), generateURL($baseurl_short . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
        }
    }

    $filterusers = ps_query("SELECT ref, search_filter_override, search_filter_o_id FROM user WHERE search_filter_o_id IS NULL OR search_filter_o_id=0");
    foreach ($filterusers as $user) {
        $filtertext = trim((string)$user["search_filter_override"]);
        if ($filtertext == "") {
            continue;
        }
        // Migrate unless marked not to due to failure
        $migrateresult = migrate_filter($filtertext);
        if (is_numeric($migrateresult)) {
            message_add(array_column($notification_users, "ref"), $lang["filter_migrate_success"] . ": '" . $filtertext . "'", generateURL($baseurl_short . "/pages/admin/admin_group_management_edit.php", array("ref" => $group["ref"])));
            // Successfully migrated - now use the new filter
            ps_query("UPDATE user SET search_filter_o_id= ? WHERE ref= ?", ['i', $migrateresult, 'i', $user['ref']]);
        } elseif (is_array($migrateresult)) {
            debug("FILTER MIGRATION: Error migrating filter: '" . $filtertext . "' - " . implode('\n', $migrateresult));
            // Error - set flag so as not to reattempt migration and notify admins of failure to be sorted manually
            ps_query("UPDATE user SET search_filter_o_id='0' WHERE ref= ?", ['i', $user['ref']]);
            message_add(array_column($notification_users, "ref"), $lang["filter_migration"] . " - " . $lang["filter_migrate_error"] . ": <br />" . implode('\n', $migrateresult), generateURL($baseurl_short . "/pages/admin/admin_group_management_edit.php", array("ref" => $usergroup)));
        }
    }

    set_sysvar("SEARCH_FILTER_MIGRATION", 1);
}

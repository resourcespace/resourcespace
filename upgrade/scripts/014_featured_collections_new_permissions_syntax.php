<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Started migrating featured collections permissions from the old format to the new one (e.g j[ID], -j[ID]) ...");

$all_ugs = ps_query("SELECT ref, `name`, permissions FROM usergroup");
foreach ($all_ugs as $ug) {
    logScript("Analysing user group #{$ug["ref"]} '{$ug["name"]}'");
    logScript("Original (old format) permissions: {$ug["permissions"]}");
    $permissions = trim_array(explode(",", $ug["permissions"]));

    $old_format_perms = array();
    foreach ($permissions as $perm) {
        $matches = array();
        if (preg_match("/^(j\-?){1}(.+[^*])$/", $perm, $matches) === 1) {
            /* Examples of a match (root category and a sub-category):
            Array
            (
                [0] => jFC1     --> actual permission (full regex match)
                [1] => j        --> permission type (j for root levels, "j-" for sub-categories which are also reverse permissions)
                [2] => FC1      --> the featured collection category branch path (separated by pipes "|" and using the category name)
            )
            Array
            (
                [0] => j-FC1|FC1/1.1|FC1/1.1/1.1.1
                [1] => j-
                [2] => FC1|FC1/1.1|FC1/1.1/1.1.1
            )
            */
            $old_format_perms[] = $matches;
        }
    }

    $update_permissions = false;
    foreach ($old_format_perms as $j_perm) {
        $find_fc_by_name = explode("|", $j_perm[2]);
        $find_fc_by_name = trim(end($find_fc_by_name));

        if ($find_fc_by_name == "") {
            logScript("Unable to determine the leaf node name for Featured Collection Category '{$j_perm[2]}'");
            continue;
        }

        $found_fc_categ_refs = ps_array(
            "SELECT DISTINCT c.ref AS `value`
                     FROM collection AS c
                LEFT JOIN collection AS cc ON c.ref = cc.parent
                    WHERE c.public = 1
                      AND c.`type`= ?
                      AND c.`name` = ?
                 GROUP BY c.ref
                   HAVING count(DISTINCT cc.ref) > 0",
            ['i', COLLECTION_TYPE_FEATURED, 's' ,$find_fc_by_name]
        );

        $fc_categ_ref = null;
        foreach ($found_fc_categ_refs as $found_fc_categ_ref) {
            // Ensure there were no issues finding the correct featured collection by double checking that the branch paths match
            $found_branch_path = get_featured_collection_category_branch_by_leaf($found_fc_categ_ref, array());
            $branch_path_str = array_reduce($found_branch_path, function ($carry, $item) {
                return "{$carry}|{$item["name"]}";
            }, "");
            $branch_path_str = mb_substr($branch_path_str, 1, mb_strlen($branch_path_str));
            if ($j_perm[2] != $branch_path_str) {
                logScript("Found a featured collection category but computed branch path is different! For the old permission this is '{$j_perm[2]}' and for the new one it is '{$branch_path_str}'. ResourceSpace might have found a similar named collection incorrectly! Skipping...");
                continue;
            }

            $fc_categ_ref = $found_fc_categ_ref;
        }

        if (is_null($fc_categ_ref)) {
            logScript("Unable to find Featured Collection Category named '{$j_perm[2]}'");
            continue;
        }

        // add the new permission format
        $new_fc_perm = ($j_perm[1] == "j" ? "" : "-") . "j{$fc_categ_ref}";
        $permissions[] = $new_fc_perm;

        // remove the old permission format
        $permissions = array_diff($permissions, array("{$j_perm[0]}"));

        $update_permissions = true;
    }

    if ($update_permissions) {
        $permissions_str = join(",", $permissions);
        logScript("New format permissions: " . $permissions_str);
        save_usergroup($ug["ref"], array('permissions' => $permissions_str));
    }
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Successfully migrated featured collections permissions to the new format!");

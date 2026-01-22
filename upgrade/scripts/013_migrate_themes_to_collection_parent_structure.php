<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Starting migrating themes to collections using parent structure...");
$theme_category_levels = (isset($theme_category_levels) ? $theme_category_levels : 3);

// Check public and theme are set in order to be able to continue with this script. This should apply to new systems
// where these columns are not generated anymore
$collection_structure = array_column(ps_query("DESCRIBE collection", array(), "", -1, false), "Field");
if (!in_array("public", $collection_structure) || !in_array("theme", $collection_structure)) {
    return;
}

$featured_collections = ps_query("SELECT * FROM collection WHERE public = 1 AND length(theme) > 0");
foreach ($featured_collections as $collection) {
    // Ensure the full tree structure exists first to support this.
    $parent = null;
    for ($level = 1; $level <= $theme_category_levels; $level++) {
        $col = "theme" . ($level == 1 ? "" : $level);

        if (!isset($collection[$col]) || trim($collection[$col]) == "") {
            logScript("Column '{$col}' is not set or empty! Skipping...");
            continue;
        }

        $parent_params = [];
        if (is_null($parent)) {
            $parent_sql = 'IS NULL';
        } else {
            $parent_sql = '= ?';
            $parent_params = ['i', $parent];
        }
        $new_fc_name = $collection[$col];

        logScript("Processing collection #{$collection["ref"]} - column {$col} = '{$collection[$col]}' and parent {$parent_sql}");

        $fc_ref = ps_value(
            "SELECT ref AS `value` FROM collection WHERE `name` = ? AND public = 1 AND `type` = ? AND parent " . $parent_sql,
            array_merge(['s', $collection[$col], 'i', COLLECTION_TYPE_FEATURED], $parent_params),
            null
        );

        if (is_null($fc_ref)) {
            $sql = "INSERT INTO collection(name, public, type, parent, thumbnail_selection_method) VALUES ( ?, 1, ?, ?, ?)";
            logScript($sql);
            ps_query($sql, ['s', $new_fc_name, 'i', COLLECTION_TYPE_FEATURED, 'i', $parent, 'i', $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"]]);
            $fc_ref = sql_insert_id();
            logScript("Created new FC #{$fc_ref}");
        }

        // Set the parent to this collection as we descend down the tree
        $parent = $fc_ref;
    }

    // The necessary parts of the tree now exist to support this collection. Drop it into the tree.
    logScript("Update collection parent for the actual collection: {$collection["ref"]} with parent '$parent'");
    ps_query(
        "UPDATE collection SET `type` = ?, parent = ?, thumbnail_selection_method = ? WHERE ref = ?",
        [
        'i', COLLECTION_TYPE_FEATURED,
        'i', (is_null($parent) ? null : $parent),
        'i', $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
        'i', $collection["ref"]
        ]
    );
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Successfully migrated themes to collections using the parent structure");

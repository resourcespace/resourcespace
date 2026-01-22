<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Start migrating featured collections names that contain asterisks to use order_by column instead...");

$collection_structure = array_column(ps_query('DESCRIBE collection', [], '', -1, false), 'Field');
if (!in_array('order_by', $collection_structure)) {
    logScript('Checking DB structs to add order_by column to collection table...');
    check_db_structs(false);
}

logScript('Searching for collections with asterisks prefixed to their names (for ordering) ...');
$fcs_w_stars = ps_query(
    'SELECT ref, parent FROM collection WHERE `type` = ? AND `name` LIKE "*%" ORDER BY parent ASC',
    ['i', COLLECTION_TYPE_FEATURED]
);

// Legacy logic for ordering featured collection (categories): FC categories first, then FC collections.
// The ones with * go first based on how many stars they have
$old_fc_sort = function (array $a, array $b) use ($descthemesorder) {
    if ($a["has_resources"] == $b["has_resources"]) {
        if ($descthemesorder) {
            return strnatcasecmp($b["name"], $a["name"]);
        }
        return strnatcasecmp($a["name"], $b["name"]);
    }

    return $a["has_resources"] < $b["has_resources"] ? -1 : 1;
};

$starred_fcs_parents = array_unique(array_column($fcs_w_stars, 'parent'));
foreach ($starred_fcs_parents as $fc_parent) {
    $fcs = get_featured_collections((int) $fc_parent, ['access_control' => false]);
    usort($fcs, $old_fc_sort);
    sql_reorder_records('collection', array_column($fcs, 'ref'));
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Finished migrating featured collections names that contain asterisks to use order_by column instead!");

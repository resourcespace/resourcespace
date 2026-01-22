<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Starting migrating public collections to use the new collection type - COLLECTION_TYPE_PUBLIC...");

// Check public and theme are set in order to be able to continue with this script. This should apply to new systems
// where these columns are not generated anymore
$collection_structure = array_column(ps_query("DESCRIBE collection", array(), "", -1, false), "Field");
if (!in_array("public", $collection_structure) || !in_array("theme", $collection_structure)) {
    return;
}

$public_collections = ps_array("SELECT ref AS `value` FROM collection WHERE `type` = ? AND public = 1 AND (theme IS NULL OR length(trim(theme)) = 0)", ['i', COLLECTION_TYPE_STANDARD]);
if (!empty($public_collections)) {
    ps_query("UPDATE collection SET `type` = ? WHERE ref IN (" . ps_param_insert(count($public_collections)) . ")", array_merge(['i', COLLECTION_TYPE_PUBLIC], ps_param_fill($public_collections, 'i')));
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Successfully migrated public collections to use the new 'COLLECTION_TYPE_PUBLIC' type");

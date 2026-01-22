<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Removing \$use_checkboxes_for_selection - deprecated configuration option");
ps_query("DELETE FROM user_preferences WHERE parameter = 'use_checkboxes_for_selection'");

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Preparing to set the collection type to 'UPLOAD' for all users with an upload collection");

$collections = ps_array("
        SELECT c.ref AS `value`
          FROM collection AS c
    INNER JOIN `user` AS u ON c.`user` = u.ref AND c.ref = 0 - u.ref", []);

$log = "Successfully updated collection type" . PHP_EOL;
if (!update_collection_type($collections, COLLECTION_TYPE_UPLOAD)) {
    $log = "Warning - unable to update collection type" . PHP_EOL;
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $log);
echo $cli ? $log : nl2br(str_pad($log, 4096));

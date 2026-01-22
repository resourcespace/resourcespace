<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Start decoupling of resource types and fields to enable multi type support...");

$field_tinfo = ps_query('DESCRIBE resource_type_field');
if (!is_array($field_tinfo)) {
    $msg = '[error] Unable to describe table "resource_type_field"';
    logScript($msg);
    message_add($notification_users, "Upgrade script 025: {$msg}", '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN, MESSAGE_DEFAULT_TTL_SECONDS);
    return;
}

if (!in_array("resource_type", array_column($field_tinfo, "Field"))) {
    // No resource_type column, script not required
    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Finished decoupling of resource types and fields to enable multi type support!");
    return;
}

// Clear out any entries added by dbstruct
ps_query("TRUNCATE resource_type_field_resource_type");

// Populate new join table
ps_query("INSERT INTO resource_type_field_resource_type (resource_type_field,resource_type) SELECT ref,resource_type FROM resource_type_field WHERE resource_type>0 AND resource_type<999");

// Set global values
$noglobals = ps_array("SELECT ref value FROM resource_type WHERE inherit_global_fields=0");
if (count($noglobals) == 0) {
    // All resource types inherit the global fields
    // If resource_type is 0 or 999, global is 1. For all higher values of resource_type, global is zero.
    ps_query("UPDATE resource_type_field SET global=IF(resource_type=999,1,1-LEAST(1,resource_type))");
} else {
    // No global flags can be set if any resource type does not inherit the global fields (this option is now removed from the interface)
    ps_query("UPDATE resource_type_field SET global=0");
    $allrestypes = get_resource_types();
    $globalrestypes = array_diff(array_column($allrestypes, "ref"), $noglobals);
    $globalfields = ps_array("SELECT ref value FROM resource_type_field WHERE resource_type=0 OR resource_type=999");

    if (count($globalrestypes) > 0 && count($globalfields) > 0) {
        // Add what would otherwise be global fields to the new table
        $addvals = [];
        foreach ($globalfields as $globalfield) {
            foreach ($globalrestypes as $globalrestype) {
                $addvals[] = "(" . $globalfield . "," . $globalrestype . ")";
            }
        }
        ps_query("INSERT INTO resource_type_field_resource_type (resource_type_field,resource_type) VALUES " . implode(",", $addvals));
    }
}
clear_query_cache("schema");
set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Finished decoupling of resource types and fields to enable multi type support!");

<?php

set_time_limit(0);

$tables = ps_query("SHOW TABLES");
if (!in_array("resource_data", array_column($tables, "Tables_in_" . $mysql_db))) {
    // Migration only required if resource_data table exists
    return true;
}

// ---------------------------------------------------------------------------------------------------------------------
// Step 1.  Check that composite primary key has been set on resource_node table (since added to dbstruct)
// ---------------------------------------------------------------------------------------------------------------------

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Checking primary key and removing duplicates from resource_node table");

$rnindexes = ps_query("SHOW KEYS FROM resource_node WHERE Key_name = 'PRIMARY'");
$rnkeys = array_column($rnindexes, "Column_name");

if (!in_array("resource", $rnkeys) || !in_array("node", $rnkeys)) {
    // Copy to a temporary table and then rename to remove duplicates. Allows use of INSERT  - ON DUPLICATE KEY syntax when inserting new nodes
    ps_query("DROP TABLE IF EXISTS resource_node_deduped");
    db_begin_transaction("check_resource_node");
    ps_query("CREATE TABLE resource_node_deduped like resource_node");
    ps_query("ALTER TABLE resource_node_deduped ADD PRIMARY KEY(resource,node)");
    ps_query("INSERT INTO resource_node_deduped SELECT * FROM resource_node ON DUPLICATE KEY UPDATE resource_node_deduped.hit_count=resource_node.hit_count");
    ps_query("RENAME TABLE resource_node TO resource_node_with_dupes");
    ps_query("RENAME TABLE resource_node_deduped TO resource_node");
    ps_query("DROP TABLE resource_node_with_dupes");
    db_end_transaction("check_resource_node");
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Checking for any missing resource_data fields that have not moved to resource_node table");

$last_check_field = get_sysvar("resource_node_check_field");

$resource_type_fields = ps_query(
    "SELECT * FROM `resource_type_field` WHERE `type` IN (" . ps_param_insert(count($FIXED_LIST_FIELD_TYPES)) . ") and ref> ? ORDER BY `ref`",
    array_merge(ps_param_fill($FIXED_LIST_FIELD_TYPES, 'i'), ['i', $last_check_field])
);

foreach ($resource_type_fields as $resource_type_field) {
    $resource_data_entries = ps_query("SELECT `resource`,`value` FROM `resource_data` WHERE  resource_type_field= ?", ['i', $resource_type_field['ref']]);
    $datarowcount = count($resource_data_entries);
    $out = PHP_EOL . "Updating resource_node values for resource_type_field {$resource_type_field['ref']}:{$resource_type_field['name']}" .
        " (" . $datarowcount . " rows found)" . PHP_EOL;

    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $out);
    if ($cli) {
        echo $out;
    } else {
        echo nl2br(str_pad($out, 4096));
    }
    ob_flush();
    flush();

    $limit = floor($datarowcount / 100);
    $n = 0;
    $fieldnodes = get_nodes($resource_type_field['ref']);
    foreach ($resource_data_entries as $resource_data_entry) {
            $resourceid = $resource_data_entry["resource"];
            $nodes_to_add = array();
            $node_names = array();
            // Add any values that match but are not currently in resource_node
            $resource_nodes = get_resource_nodes($resourceid, $resource_type_field['ref']);

            $datavalues = explode(",", $resource_data_entry["value"]);

        foreach ($datavalues as $datavalue) {
            $datavalue =   trim($datavalue);
            if ($datavalue == "") {
                continue;
            }
            // Add any values that match but are not currently in resource_node
            foreach ($fieldnodes as $fieldnode) {
                if (
                    (
                        $datavalue == trim($fieldnode["name"])
                        || $datavalue == trim(i18n_get_translated($fieldnode["name"]))
                    )
                    && !in_array($fieldnode["ref"], $resource_nodes) // This is a valid node, is the corresponding resource_node set
                ) {
                        $nodes_to_add[] = $fieldnode["ref"];
                        $node_names[] = $fieldnode["name"];
                }
            }
        }
        if (count($nodes_to_add) > 0) {
            add_resource_nodes($resourceid, $nodes_to_add);
        }
        if ($n > $limit) {
            if ($cli) {
                echo "+";
            } else {
                echo str_pad("+", 4096);
            }
            ob_flush();
            flush();
            $n = 0;
        }
            $n++;
    }
    set_sysvar("resource_node_check_field", $resource_type_field['ref']);
}

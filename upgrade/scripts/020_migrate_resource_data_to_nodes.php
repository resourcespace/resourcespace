<?php

// Script to migrate all non-fixed list data to nodes


$tables = ps_query("SHOW TABLES");
if (!in_array("resource_data", array_column($tables, "Tables_in_" . $mysql_db))) {
    // Migration only required if resource_data table exists
    return true;
}

// Ensure node name column is moved to mediumtext
check_db_structs();

$tomigrate = array_diff(array_keys($field_types), array_merge($FIXED_LIST_FIELD_TYPES, [FIELD_TYPE_DATE_RANGE]));
$startfield = get_sysvar("node_migrated_data_field", 0);

$debug_log = false; // This would slow things down too much
$global_start_time = microtime(true);

$resource_type_fields = ps_query('SELECT * FROM `resource_type_field` WHERE `type` IN (' . ps_param_insert(count($tomigrate)) . ') AND ref > ? ORDER BY `ref`', array_merge(ps_param_fill($tomigrate, "i"), ["i",$startfield]));

// Number of resource_data rows to migrate in each batch to avoid out of memory errors
$chunksize = 10000;
foreach ($resource_type_fields as $resource_type_field) {
    $fref = $resource_type_field['ref'];
    $fname = $resource_type_field['name'];
    $status = "Migrating resource_data for field #" . $fref . " (" . $fname . ")";
    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $status);

    // get_nodes() can cause memory errors for non-fixed list fields so will get hash (a single md5 is too susceptible to collisions for large datasets) and compare on that
    $nodeinfo =  ps_query("SELECT ref, concat(MD5(name),MD5(CONCAT('!',name))) hash FROM node WHERE resource_type_field = ?", ["i", $fref]);
    $allfieldnodes = array_column($nodeinfo, "ref", "hash");

    $todorows = ps_query("SELECT MIN(resource) AS minref, MAX(resource) as maxref, count(resource) AS count FROM `resource_data` WHERE resource_type_field = ?", ["i",$fref]);
    $totalrows  = $todorows[0]["count"] ?? 0;
    $minref     = $todorows[0]["minref"] ?? 0;
    $maxref     = $todorows[0]["maxref"] ?? 0;
    $out = " (" . $totalrows . " rows found)";
    logScript(str_pad($status . $out, 100, ' '));
    ob_flush();
    $resourcestart = $minref;
    $processed = 0;
    while ($resourcestart <= $maxref) {
        $rows = ps_query(
            "SELECT rd.resource, rd.value
                        FROM resource_data rd 
                        JOIN (SELECT resource, resource_type_field FROM resource_data WHERE resource_type_field = ?) rd2
                        ON rd2.resource=rd.resource AND rd.resource_type_field=rd2.resource_type_field
                    WHERE rd.resource >= ? AND rd.resource < ?",
            [
                        "i",$fref,
                        "i",$resourcestart,
                        "i",($resourcestart + $chunksize),
                        ]
        );

        if (count($rows) == 0) {
            // Nothing for this batch of resources
            $resourcestart = $resourcestart + $chunksize;
            continue;
        }
        // Process in smaller chunks for inserts
        $rowbatches = array_chunk($rows, 2000);

        // Get current nodes for this batch of resources
        $batchresources = array_column($rows, "resource");
        $max = max($batchresources);
        $min = min($batchresources);
        $resnodes = ps_query("SELECT rn.resource, rn.node FROM resource_node rn LEFT JOIN node n ON n.ref=rn.node WHERE rn.resource >= '" . $min . "' AND rn.resource <= '" . $max . "' AND n.resource_type_field = ?", ["i",$fref]);
        $resnodearr = [];
        foreach ($resnodes as $resnode) {
            $resnodearr[$resnode["resource"]][] = $resnode["node"];
        }
        for ($n = 0; $n < count($rowbatches); $n++) {
            db_begin_transaction("populate_nodes_from_data");
            foreach ($rowbatches[$n] as $rowdata) {
                $rowdata["value"] = (string) $rowdata["value"];
                if (trim($rowdata["value"]) != "") {
                    if (isset($allfieldnodes[md5($rowdata["value"]) . md5('!' . $rowdata["value"])])) {
                        $newnode = $allfieldnodes[md5($rowdata["value"]) . md5('!' . $rowdata["value"])];
                    } else {
                        // Not using set_node() here as that will reindex node.
                        // The existing data from resource_keyword can be used instead to speed things up
                        $addnodequery = "INSERT INTO `node` (`resource_type_field`, `name`, `parent`, `order_by`) VALUES (?, ?, NULL, 0)";
                        $parameters = array
                            (
                            "i",$fref,
                            "s",$rowdata["value"],
                            );
                        ps_query($addnodequery, $parameters);
                        $newnode = sql_insert_id();
                        $copykeywordquery = "INSERT INTO node_keyword (node, keyword, position) SELECT ?, keyword, position FROM resource_keyword WHERE resource = ? AND resource_type_field = ?";
                        $copykeywordparams = ["i",$newnode,"i",$rowdata["resource"],"i", $fref];
                        ps_query($copykeywordquery, $copykeywordparams);
                        $allfieldnodes[md5($rowdata["value"]) . md5("!" . $rowdata["value"])] = $newnode;
                    }
                    if (!isset($resnodearr[$rowdata["resource"]]) || !in_array($newnode, $resnodearr[$rowdata["resource"]])) {
                        logScript("Updating resource " . $rowdata["resource"] . ", field #" . $fref . " (" . $fname . ") with node " . $newnode . " (" . str_replace("\n", " ", mb_strcut($rowdata["value"], 0, 30)) . "...)");
                        // Not using add_resource_nodes() here to speed things up
                        ps_query("INSERT INTO resource_node(resource, node) VALUES (?,?)", ["i",$rowdata["resource"],"i",$newnode]);
                        $resnodearr[$rowdata["resource"]][] = $newnode;
                    } else {
                        logScript("Skipping, correct node already set for resource - " . $rowdata["resource"] . ", field #" . $fref . " (" . $fname . ") with node " . $newnode . " (" . str_replace("\n", " ", mb_strcut($rowdata["value"], 0, 30)) . "...)");
                    }
                    // Remove any extra nodes that may be present
                    if (isset($resnodearr[$rowdata["resource"]])) {
                        $nodestoremove = array_diff($resnodearr[$rowdata["resource"]], [$newnode]);
                        if (count($nodestoremove) > 0) {
                            ps_query("DELETE FROM resource_node WHERE resource = ? AND node IN (" . ps_param_insert(count($nodestoremove)) . ")", array_merge(["i",$rowdata["resource"]], ps_param_fill($nodestoremove, "i")));
                        }
                    }
                    $processed++;
                }
            }
            db_end_transaction("populate_nodes_from_data");
        }

        $resourcestart = $resourcestart + $chunksize;
        $out = " - processed " . $processed . "/" . $totalrows . " records for field # " . $fref;
        logScript(str_pad($out, 100, ' '));
        set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $status . $out);
        ob_flush();
    }
    $out = sprintf(" - Completed $processed records in %01.2f seconds.\n", microtime(true) - $global_start_time);
    logScript(str_pad($out, 100, ' '));
    set_sysvar("node_migrated_data_field", $fref);
    ob_flush();
}


// Migrate any annotations (plugin) as these are not field linked
// The plugin may only be enabled for some usergroups so can't just check $plugins array
$annotate_enabled = ps_value("SELECT COUNT(*) value FROM plugins WHERE name='annotate' AND inst_version IS NOT NULL", [], 0);
if ($annotate_enabled) {
    logScript("Annotate plugin enabled, migrating to use new nodes");
    $count = 0;
    // Force CheckDBStruct() as this won't run if only enabled for specific groups
    CheckDBStruct("plugins/annotate/dbstruct");
    $annotate_config = get_plugin_config("annotate");
    $annotate_field = (isset($annotate_config["annotate_resource_type_field"]) && $annotate_config["annotate_resource_type_field"] >  0) ? $annotate_config["annotate_resource_type_field"] : 0;

    logScript("Checking if metadata field set: " . ($annotate_field  > 0 ? $annotate_field : "Not set"));
    if ($annotate_field == 0) {
        // Create a new field to hold annotations
        $annotate_field = create_resource_type_field("Annotations plugin", 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, "annotateplugin", true);
        ps_query("UPDATE resource_type_field SET display_field=0, advanced_search=0,hide_when_uploading=1 WHERE ref = ?", ["i",$annotate_field]);
        // Set plugin to use this field
        $annotate_config["annotate_resource_type_field"] = $annotate_field;
        set_plugin_config("annotate", $annotate_config);
        logScript("Set new annotation field " . $annotate_field);
    } else {
        $annotate_field = $annotate_config["annotate_resource_type_field"];
    }
    // Get existing annotations
    $current_annotations = ps_query("SELECT ref, note, note_id, node FROM annotate_notes");
    foreach ($current_annotations as $annotation) {
        logScript("Found annotation for resource  " . $annotation["ref"] . ", node: " . $annotation["node"]);
        if ((int)$annotation["node"] == 0) {
            // No node set, create a new one
            logScript("Migrating annotation for resource  " . $annotation["ref"] . ", note: " . $annotation["note"]);
            $node = set_node(null, $annotate_field, $annotation["note"], null, 10);
            ps_query("UPDATE annotate_notes SET node = ? WHERE ref= ?", ["i",$node,"i",$annotation["ref"]]);
            // Add nodes so will be searchable
            add_resource_nodes($annotation["ref"], [$node], true, true);
            $count++;
        }
    }
    logScript("Completed " . $count . " annotations");
}

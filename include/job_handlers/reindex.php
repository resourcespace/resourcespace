<?php

# $job_data['field_refs'] -> Optional. Comma-separated list of field references to be reindexed

logScript("[reindex] Starting reindex job", $log_file);

$allfields = [];

if (isset($job_data['field_refs']) && $job_data['field_refs'] !== '') {

    $processed_field_refs = parse_csv_to_list_of_type($job_data['field_refs'], "is_positive_int_loose");

    if (empty($processed_field_refs)) {
        logScript("[reindex] [ERROR] unable to process field list", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

    foreach ($processed_field_refs as $fieldref) {
    
        $fieldref_info = get_resource_type_field($fieldref);

        if (!$fieldref_info) {
            logScript("[update_exiftool_field] [ERROR] field " . (int) $fieldref . " doesn't exist", $log_file);
            job_queue_update($jobref, $job_data, STATUS_ERROR);
            return;
        }

        $allfields[] = $fieldref_info;
    }

} else {
    // Reindex nodes, by field to minimise chance of memory issues
    $allfields = get_resource_type_fields();
}

// Disable sql_logging
$mysql_log_transactions = false;

$sql = '';
$params = [];

$total_fields = count($allfields);
$field_count = 1;

$time_start = microtime(true);

foreach ($allfields as $field) {

    logScript("[reindex] indexing nodes for field #" . $field["ref"] . " (" . $field["title"] . ")", $log_file);
    
    // node query
    $query = "SELECT n.ref, n.name, n.resource_type_field, f.partial_index FROM node n JOIN resource_type_field f ON n.resource_type_field=f.ref WHERE n.resource_type_field = ?";
    $params = ["i", $field["ref"]];

    $nodes = ps_query($query, $params);
    $count = count($nodes);

    logScript("[reindex] found " . $count . " nodes...", $log_file);

    $start = 0;
    $batchsize = 100;
    $indexed = 0;

    while ($indexed < $count) {
        db_begin_transaction("reindex_field_nodes");
        for ($n = $start; $n < ($start + $batchsize) && $indexed < $count; $n++) {
            // Remove any existing keywords for this field first
            remove_all_node_keyword_mappings($nodes[$n]['ref']);
            if ($field["keywords_index"] == 1) {
                // Populate node_keyword table only if indexing enabled
                add_node_keyword_mappings($nodes[$n], $nodes[$n]["partial_index"]);
            }
            $indexed++;
        }
        db_end_transaction("reindex_field_nodes");

        logScript("[reindex] " . round(($indexed / $count * 100), 2) . "% completed " . $indexed . "/" . $count . " nodes", $log_file);

        $start += $batchsize;
    }

    $progress = round(($field_count / $total_fields) * 100, 0);
    logScript("[reindex] [PROGRESS] $progress%", $log_file);
    $field_count++;    
}

$time_end = microtime(true);
$time     = $time_end - $time_start;

logScript("[reindex] reindex took $time seconds", $log_file);

logScript("[reindex] Ending reindex job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed reindex job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
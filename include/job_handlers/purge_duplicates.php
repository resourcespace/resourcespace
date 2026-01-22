<?php

# $job_data['collection_refs'] -> Optional. Comma-separated list of collection references, can be ranges
# $job_data['manage_method']   -> Determines how the script will deal with the duplicates removal, can be lifo or fifo, defaults to lifo
# $job_data['dry_run']         -> Boolean, controls whether any resources will be deleted, will default to false if not specified
# $job_data['delete_perm']     -> Boolean, any resources flagged for deletion will be permenantly deleted, will default to false if not specified

logScript("[purge_duplicates] Starting purge_duplicates job", $log_file);

$manage_method = (string) $job_data['manage_method'] ?? 'lifo';
$dry_run = (bool) $job_data['dry_run'] ?? false;
$delete_permanently = (bool) $job_data['delete_perm'] ?? false;

// Reject invalid parameters and combinations
if ($dry_run && $delete_permanently) {
    logScript("[purge_duplicates] [ERROR] dry-run and delete permanently are mutually exclusive", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

if ($dry_run) {
    $dry_run_text = "[DRY-RUN]";
} else {
    $dry_run_text = "";
}

if (!in_array($manage_method, array('fifo', 'lifo'))) {
    logScript("[purge_duplicates] [ERROR] specified manage method is invalid", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
}

if ($manage_method == 'lifo') {
    $order_by = 'ASC';
} else {
    $order_by = 'DESC';
}

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {    
    
    $range_condition = build_range_where_condition($job_data['collection_refs'], "cr.collection");

    $params = [];

    if ($range_condition["ok"]) {
        $conditions[] = $range_condition['where'];
        $params = $range_condition['params'];
    } else {
        logScript("[purge_duplicates] [ERROR] unable to process where condition", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

    // Duplicates where the checksum is present in any of the passed-in collections
    $duplicates_by_checksum =
    ps_query("SELECT d1.file_checksum, d1.ref FROM (
        SELECT r1.file_checksum, r1.ref FROM resource r1
            WHERE coalesce(r1.file_checksum, '') <> ''
            AND ( SELECT count(*) r2count from resource r2 WHERE r2.file_checksum = r1.file_checksum ) > 1
            ORDER BY r1.file_checksum ASC, r1.ref ASC) as d1
        WHERE d1.file_checksum IN
            (SELECT r3.file_checksum from collection_resource cr
            INNER JOIN resource r3 on r3.ref = cr.resource and coalesce(r3.file_checksum,'') <> ''
            WHERE r3.ref > 0"
                       . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") .
            ") ORDER BY d1.file_checksum ASC, d1.ref {$order_by}", $params);

} else {

    // All duplicates
    $duplicates_by_checksum =
    ps_query("SELECT r1.file_checksum, r1.ref 
        FROM resource r1
        WHERE coalesce(r1.file_checksum, '') <> ''
        AND ( SELECT count(*) r2count from resource r2 WHERE r2.file_checksum = r1.file_checksum ) > 1
        ORDER BY r1.file_checksum ASC, r1.ref {$order_by}");

}

$count_matching_checksums = count($duplicates_by_checksum);
$count_permanent_deletions = 0;
$count_marked_deletions = 0;
$count_unchanged = 0;

$keep_resources = array();
$delete_resources = array();
$last_kept_resource = null;
$last_checksum = null;

$delete_total_count = 0;

// Build an array of resources which will be kept, and another array of the resources to be deleted
foreach ($duplicates_by_checksum as $duplicate) {
    if ($duplicate["file_checksum"] !== $last_checksum) {
        // The first resource for each new checksum will be kept
        $keep_resources[$duplicate["ref"]] = $duplicate["file_checksum"];
        $last_checksum = $duplicate["file_checksum"];
        $last_kept_resource = $duplicate["ref"];
    } else {
        // Subsequent resources for this checksum will be deleted
        $delete_resources[$last_kept_resource][] = $duplicate["ref"];
        $delete_total_count++;
    }
}
// The kept resources array is currently in checksum sequence
// We want to process the resources in ascending kept resource sequence for logging readability
ksort($keep_resources);


logScript("[purge_duplicates] " . $dry_run_text . " count of candidate resources with matching checksums is {$count_matching_checksums}", $log_file);

if ($dry_run) {
    logScript("[purge_duplicates] " . $dry_run_text . " dry run enabled so no actual resources will be deleted", $log_file);
}

$delete_progress_count = 1;

// // Process and log each kept resource and checksum, deleting the other resources identified earlier (ie. with the same checksum))
foreach ($keep_resources as $keep_ref => $keep_checksum) {
    
    // Log resource which will be kept
    logScript("[purge_duplicates] $dry_run_text keep resource #{$keep_ref} with checksum '{$keep_checksum}'", $log_file);
    $count_unchanged += 1;

    // Resource deletion
    foreach ($delete_resources[$keep_ref] as $delete_resource) {
        if ($delete_permanently) {
            // Option delete-permanently and dry-run are mutually exclusive
            // Option dry-run will never be true and the associated text is always blank at this point; this is just a belt and braces check
            logScript("[purge_duplicates] $dry_run_text ...deleting resource #{$delete_resource} with checksum '{$keep_checksum}' permanently", $log_file);
            $count_permanent_deletions += 1;
            if (!$dry_run) {

                $old_resource_deletion_state = $GLOBALS["resource_deletion_state"];
                unset($GLOBALS["resource_deletion_state"]);
                delete_resource($delete_resource);
                $GLOBALS["resource_deletion_state"] = $old_resource_deletion_state;
            }
        } else {
            logScript("[purge_duplicates] $dry_run_text ...deleting resource #{$delete_resource} with checksum '{$keep_checksum}' logically; marked as '{$GLOBALS["resource_deletion_state"]}'", $log_file);
            $count_marked_deletions += 1;
            if (!$dry_run) {
                update_archive_status($delete_resource, $GLOBALS["resource_deletion_state"]);
            }
        }

        $progress = round(($delete_progress_count / $delete_total_count) * 100, 0);
        logScript("[purge_duplicates] [PROGRESS] $progress%", $log_file);
        $delete_progress_count++;    
    }
}

// // Report various processing counts

$count_processed_resources = 0;

logscript("[purge_duplicates] " . $dry_run_text . " {$count_unchanged} resources kept", $log_file);

if ($delete_permanently) {
    logscript("[purge_duplicates] " . $dry_run_text . " {$count_permanent_deletions} resources permanently deleted", $log_file);
    $count_processed_resources = $count_unchanged + $count_permanent_deletions;
} else {
    logscript("[purge_duplicates] " . $dry_run_text . " {$count_marked_deletions} resources marked as deleted", $log_file);
    $count_processed_resources = $count_unchanged + $count_marked_deletions;
}

// Report whether or not ending counts are as expected
if ($count_matching_checksums == $count_processed_resources) {
    logScript("[purge_duplicates] " . $dry_run_text . " count of processed resources with matching checksums is {$count_processed_resources} as expected", $log_file);
} else {
    logScript("[purge_duplicates] [ERROR] " . $dry_run_text . " count of processed resources with matching checksums is {$count_processed_resources} which is unexpected", $log_file);
}

if ($dry_run) {
    logScript("[purge_duplicates] " . $dry_run_text . " dry run enabled so no actual resources have been deleted", $log_file);
}

logScript("[purge_duplicates] Ending purge_duplicates job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed purge_duplicates job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);

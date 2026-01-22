<?php

include_once __DIR__ . '/../image_processing.php';

global $extracted_text_field, $extracted_text_extensions;

# $job_data['collection_refs'] -> Optional. Comma-separated list of collection references, can be ranges
# $job_data['update_all']      -> Optional. Boolean, will default to false if not specified

logScript("[update_extracted_text] Starting update_extracted_text job", $log_file);

$update_all = (bool) $job_data['update_all'] ?? false;

$resources = [];

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {    
    
    $range_condition = build_range_where_condition($job_data['collection_refs'], "cr.collection");

    $params = [];

    if ($range_condition["ok"]) {
        $conditions[] = $range_condition['where'];
        $params = $range_condition['params'];
    } else {
        logScript("[update_extracted_text] [ERROR] unable to process where condition", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

     // Find all resources with an extractable file type that are part of the collections passed
    $resources = ps_query("SELECT r.ref, r.file_extension 
                            FROM resource r
                            INNER JOIN collection_resource cr ON r.ref = cr.resource
                            WHERE r.ref > 0
                            AND LOWER(r.file_extension) IN (" . ps_param_insert(count($extracted_text_extensions)) . ")"
                                . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") .
                            "ORDER BY r.ref ASC", array_merge(ps_param_fill($extracted_text_extensions, "s"), $params));

} else {
    // Find all resources with an extractable file type
    $resources = ps_query("SELECT r.ref, r.file_extension 
                            FROM resource r
                            WHERE r.ref > 0
                            AND LOWER(r.file_extension) IN (" . ps_param_insert(count($extracted_text_extensions)) . ")
                            ORDER BY r.ref ASC", ps_param_fill($extracted_text_extensions, "s"));

}

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;
    $edit_count = 0;

    logScript("[update_extracted_text] Extracting text for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[update_extracted_text] Extracting text for resource #" . $resource["ref"] . "...", $log_file);

        $current_extracted_text = get_data_by_field($resource['ref'], $extracted_text_field);

        if (!empty($current_extracted_text) && !$update_all) {
            logScript("[update_extracted_text] ...resource #" . $resource['ref'] . " - already has extracted text - skipping", $log_file);
        } else {
            $result = extract_text($resource['ref'], $resource['file_extension']);

            if (generate_file_checksum($resource["ref"], $resource["file_extension"], true)) {
                logScript("[update_extracted_text] ...completed", $log_file);
                $edit_count++;
            } else {
                logScript("[update_extracted_text] [ERROR] Failed - skipping", $log_file);
            }
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[update_extracted_text] [PROGRESS] $progress%", $log_file);
        $resource_count++;    
    }

    logScript("[update_extracted_text] $edit_count resources modified", $log_file);

} else {
    logScript("[update_extracted_text] No resources found", $log_file);
}

logScript("[update_extracted_text] Ending update_extracted_text job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed update_extracted_text job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
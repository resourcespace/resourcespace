<?php

include_once dirname(__FILE__, 2) . '/include/google_vision_functions.php';

global $google_vision_restypes;

# $job_data['collection_refs'] -> Optional. Comma-separated list of collection references, can be ranges

logScript("[process_gv_existing] Starting process_gv_existing job", $log_file);

$collections = [];
$resources = [];
$ignore_resource_type_constraint = false;

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {    
    
    // Find all resources that have not been processed yet    
    $ignore_resource_type_constraint = true;
    $resources = array();

    $collection_refs = parse_int_ranges($job_data['collection_refs'], 0, true, false);

    if ($collection_refs["ok"]) {
        $collections = $collection_refs['numbers'];
    } else {
        logScript("[process_gv_existing] [ERROR] Unable to process ranges", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

    foreach ($collections as $collection) {
        $collection_resources = get_collection_resources($collection);
        $resources = array_merge($resources, $collection_resources);
    }

    $resources = array_unique($resources);

} else {

    // Find all resources that have not been processed yet
    $gv_query = "SELECT ref AS `value`
                    FROM resource
                    WHERE (google_vision_processed IS NULL OR google_vision_processed = 0)
                        AND ref > 0
                        AND has_image <> ?
                        AND resource_type IN (" . ps_param_insert(count($google_vision_restypes)) . ") ";

    $parameters = array_merge(["i", RESOURCE_PREVIEWS_NONE], ps_param_fill($google_vision_restypes, "i"));

    $resources = ps_array($gv_query, $parameters);
}

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[process_gv_existing] Processing $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[process_gv_existing] Processing resource #" . $resource . "...", $log_file);

        if (google_visionProcess($resource, true, $ignore_resource_type_constraint)) {
            logScript("[process_gv_existing] ....completed", $log_file);
        } else {
            logScript("[process_gv_existing] [ERROR] Failed - skipping", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[process_gv_existing] [PROGRESS] $progress%", $log_file);
        $resource_count++;   

    }

} else {
    logScript("[process_gv_existing] No resources found", $log_file);
}

logScript("[process_gv_existing] Ending process_gv_existing job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed process_gv_existing job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
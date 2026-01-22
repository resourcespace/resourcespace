<?php

# $job_data['collection_refs'] -> Optional. Comma-separated list of collection references, can be ranges

logScript("[faces_tag] Starting faces_tag job", $log_file);

$resources = [];

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {    
    
    $range_condition = build_range_where_condition($job_data['collection_refs'], "cr.collection");

    $params = [];

    if ($range_condition["ok"]) {
        $conditions[] = $range_condition['where'];
        $params = $range_condition['params'];
    } else {
        logScript("[faces_tag] [ERROR] unable to process where condition", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

     // Find all faces that have not been identified that are part of the collections passed.
    $resources = ps_array("SELECT DISTINCT resource value
                            FROM (
                                SELECT rf.resource 
                                FROM resource_face rf
                                INNER JOIN collection_resource cr ON cr.resource = rf.resource
                                WHERE (node IS NULL OR node = 0)" . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") . 
                                "ORDER BY rf.ref DESC) resources", $params);

} else {
    // Find all faces that have not been identified.
    $resources = ps_array("SELECT DISTINCT resource value FROM resource_face WHERE (node IS NULL OR node = 0) ORDER BY ref DESC");
}

if (count($resources) > 0) {

    $total_resources = count($resources);

    $resource_count = 1;

    logScript("[faces_tag] Checking tagging for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {

        logScript("[faces_tag] Tagging faces for resource #" . $resource . "...", $log_file);
        ob_flush();

        if (faces_tag($resource)) {
            logScript("[faces_tag] ....completed", $log_file);
        } else {
            logScript("[faces_tag] [ERROR] Failed - skipping", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[faces_tag] [PROGRESS] $progress%", $log_file);
        $resource_count++;
        ob_flush();        
    }

} else {
    logScript("[faces_tag] No resources found", $log_file);
}

logScript("[faces_tag] Ending faces_tag job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed faces_tag job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
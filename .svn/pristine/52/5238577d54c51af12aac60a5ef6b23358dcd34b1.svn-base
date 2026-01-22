<?php

include_once __DIR__ . '/../image_processing.php';

global $ffmpeg_preview_extension, $ffmpeg_supported_extensions, $resource_deletion_state;

# $job_data['process_type'] -> Either "resource" or "collection"
# $job_data['refs'] -> Comma-separated list of references (can be either Resource or Collection refs), can be ranges
# $job_data['sizes'] -> List of image sizes to generate, by preview_size.id
# $job_data['types'] -> List of resource types to process, by resource_type.ref
# $job_data['use_existing'] -> Optional. Boolean, will default to false if not specified
# $job_data['video_update'] -> Optional. Boolean, will default to false if not specified
# $job_data['delete_existing'] -> Optional. Boolean, will default to false if not specified
#                                 Delete option cannot be used with sizes, types, use_existing or video_update options

function update_preview($ref, $previewbased, $sizes, $delete_existing)
{
    $resourceinfo = ps_query("select file_path, file_extension from resource where ref = ?", array("i", (int) $ref));
    if (count($resourceinfo) > 0 && !hook("replaceupdatepreview", '', array($ref, $resourceinfo[0]))) {
        if (!empty($resourceinfo[0]['file_path'])) {
            $ingested = false;
        } else {
            $ingested = true;
        }
        if ($delete_existing) {
            delete_previews($ref);
        }
        create_previews(
            $ref, 
            false, 
            ($previewbased || in_array($resourceinfo[0]["file_extension"], NON_PREVIEW_EXTENSIONS) ? "jpg" : $resourceinfo[0]["file_extension"]), 
            false, 
            $previewbased || in_array($resourceinfo[0]["file_extension"], NON_PREVIEW_EXTENSIONS), 
            -1, 
            true, 
            $ingested, 
            true, 
            $sizes
        );
        hook("afterupdatepreview", "", array($ref));
        update_disk_usage($ref);
        return true;
    }
    return false;
}

logScript("[recreate_previews] Starting recreate_previews job", $log_file);

// Process $job_data
if (is_array($job_data['sizes']) && !in_array('all', $job_data['sizes'])) {
    $sizes = $job_data['sizes'];
} else {
    $sizes = array();
}

if (is_array($job_data['types']) && !in_array('all', $job_data['types'])) {
    $types = $job_data['types'];
} else {
    $types = array();
}

$previewbased = (bool) $job_data['use_existing'] ?? false;
$videoupdate = (bool) $job_data['video_update'] ?? false;
$delete_existing = (bool) $job_data['delete_existing'] ?? false;

if ($delete_existing && (count($sizes) > 0 || count($types) > 0 || $previewbased || $videoupdate)) {
    logScript("[recreate_previews] [ERROR] invalid parameters provided for job, cannot use delete_existing with other options", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

// Build the list of resources to process
$joins = "";
$conditions = array();
$conditions_params = array();

if ($job_data['process_type'] == "collections") {
    $range_condition = build_range_where_condition($job_data['refs'], "cr.collection", 0, true);
    $joins .= "INNER JOIN collection_resource cr ON r.ref = cr.resource";
} else {
    $range_condition = build_range_where_condition($job_data['refs'], "r.ref", 0, true);
}

if ($range_condition["ok"]) {
    $conditions[] = $range_condition['where'];
    $conditions_params = array_merge($conditions_params, $range_condition['params']);
} else {
    logScript("[recreate_previews] [ERROR] unable to process where condition", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

if ($videoupdate) {
    $conditions[] = "file_extension in (" . ps_param_insert(count($ffmpeg_supported_extensions)) . ")";
    $conditions_params = array_merge($conditions_params, ps_param_fill($ffmpeg_supported_extensions, "s"));
}
if (isset($resource_deletion_state)) {
    $conditions[] = "archive <> ?";
    $conditions_params = array_merge($conditions_params, array("i", $resource_deletion_state));
}
if (!empty($types)) {
    $conditions[] = "resource_type in (" . ps_param_insert(count($types)) . ")";
    $conditions_params = array_merge($conditions_params, ps_param_fill($types, "i"));
}

clear_query_cache("recreate_previews");

$resources = ps_array("SELECT DISTINCT ref value
                        FROM (
                            SELECT r.ref 
                            FROM resource r
                            $joins
                            WHERE r.ref > 0" . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") . "
                            ) resources
                        ORDER BY ref;", $conditions_params, "recreate_previews");

$total_resources = count($resources);

// Attempt to process the resources
if (is_array($resources) && $total_resources > 0) {
    
    hook('beforescriptaction');
    $resource_count = 1;

    logScript("[recreate_previews] Recreating previews for $total_resources resource(s)", $log_file);

    foreach ($resources as $resource) {        

        if ($videoupdate) {
            $checkflvpreview = get_resource_path($resource, true, 'pre', false, 'flv', true, 1, false, '');
            $correctvideo_preview = get_resource_path($resource, true, 'pre', false, $ffmpeg_preview_extension, true, 1, false);
            echo "Checking for video preview of resource #" . $resource .  ".....";
            if (file_exists($correctvideo_preview)) {
                echo "...already exists, skipping\n";
                continue;
            }
        }

        logScript("[recreate_previews] Recreating previews for resource #" . $resource . "...", $log_file);
        ob_flush();

        if (update_preview($resource, $previewbased, $sizes, $delete_existing)) {
            logScript("[recreate_previews] ....completed", $log_file);
        } else {
            logScript("[recreate_previews] [ERROR] Failed - skipping", $log_file);
        }

        $progress = round(($resource_count / $total_resources) * 100, 0);
        logScript("[recreate_previews] [PROGRESS] $progress%", $log_file);
        $resource_count++;
        ob_flush();
    }
} else {
    logScript("[recreate_previews] No resources found", $log_file);
}

logScript("[recreate_previews] Ending recreate_previews job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed recreate_previews job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
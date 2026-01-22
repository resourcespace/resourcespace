<?php

# $job_data['field_ref'] -> Field reference for AI field to process
# $job_data['collection_refs'] -> Comma-separated list of collection references
# $job_data['overwrite'] -> Boolean, will default to false if not specified

logScript("[process_gpt_existing] Starting process_gpt_existing job", $log_file);

$collections    = [];
$collectionset  = false;

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {

    $collection_refs = parse_int_ranges($job_data['collection_refs'], 0);

    if ($collection_refs["ok"]) {
        $collections = $collection_refs['numbers'];
        $collectionset = true;
    } else {
        logScript("[process_gpt_existing] [ERROR] Unable to process ranges", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }
}

$collections = array_filter($collections, "is_int_loose");

$targetfield = (int) $job_data['field_ref'] ?? 0;
$targetfield_data = get_resource_type_field($targetfield);

$overwrite = (bool) $job_data['overwrite'] ?? false;

if (!$targetfield_data) {
    logScript("[process_gpt_existing] [ERROR] Invalid field specified", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

$input_field = $targetfield_data["openai_gpt_input_field"];

$input_is_file = false;
if ($input_field === -1) {
    // Input field is the image i.e GPT Input is "Image: Preview image"
    $input_is_file = true;
} else {
    $input_field_data = get_resource_type_field($input_field);
    if (!$input_field_data) {
        logScript("[process_gpt_existing] [ERROR] Invalid input field for {$targetfield}", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }
}

$allstates = get_workflow_states();
$arr_toprocess = [];

logScript("[process_gpt_existing] Overwrite existing data: " . ($overwrite ? "TRUE" : "FALSE"), $log_file);
logScript("[process_gpt_existing] Target field : #" . $targetfield  . " - " . $targetfield_data["title"] . " (" . $targetfield_data["name"] . ")", $log_file);

if ($input_is_file) {
    logScript("[process_gpt_existing] Input field : Image: Preview image", $log_file);
} else {
    logScript("[process_gpt_existing] Input field : #" . $input_field . " - " . $input_field_data["title"] . " (" . $input_field_data["name"] . ")", $log_file);
}

logScript("[process_gpt_existing] Prompt : " . $targetfield_data["openai_gpt_prompt"], $log_file);
logScript("[process_gpt_existing] Collections : " . implode(",", $collections), $log_file);

if (!$overwrite && !$input_is_file) {
    $arr_allresources = do_search('!hasdata' . $input_field,'','',implode(",", $allstates),-1,'desc',true,null,true,false,'',false,false,true);
} else {
    // Need to process all resources, including those with no data in the source field
    $arr_allresources = do_search('','','',implode(",", $allstates),-1,'desc',true,null,true,false,'',false,false,true);
}

if (empty($collections)) {
    $arr_toprocess = array_column($arr_allresources, "ref");
} else {
    $resources = [];
    foreach ($collections as $collection) {
        $collection_resources = get_collection_resources($collection);
        $resources = array_merge($resources, $collection_resources);
    }
    $arr_toprocess = array_intersect($resources, array_column($arr_allresources, "ref"));
}

if (!$overwrite) {
    // Remove resources with data in the target field
    $arr_existingdata = do_search("!hasdata" . $targetfield,'','',implode(",", $allstates),-1,'desc',true,null,true,false,'',false,false,true);
    $arr_toprocess = array_diff($arr_toprocess, array_column($arr_existingdata, "ref"));
}

if (count($arr_toprocess) > 0) {

    $total_resources = count($arr_toprocess);

    $resource_count = 1;

    logScript("[process_gpt_existing] Processing AI field for $total_resources resource(s)", $log_file);

    $arr_success = [];
    $arr_failure = [];

    if ($input_is_file) {
        foreach ($arr_toprocess as $resource) {
            
            logScript("[process_gpt_existing] Processing resource #" . $resource . "...", $log_file);
            $path_to_file = get_resource_path($resource, true, "pre");

            if (!file_exists($path_to_file)) {
                $arr_failure[] = $resource;
                logScript("[process_gpt_existing] [ERROR] Pre size file was not found for resource", $log_file);
                
                $progress = round(($resource_count / $total_resources) * 100, 0);
                logScript("[process_gpt_existing] [PROGRESS] $progress%", $log_file);
                $resource_count++;

                continue;
            }

            $updated = openai_gpt_update_field($resource, $targetfield_data, array(), $path_to_file);
            if (isset($updated[$resource])) {
                $updated = $updated[$resource];
            }

            if ($updated) {
                $arr_success[] = $resource;
                logScript("[process_gpt_existing] ...completed", $log_file);
            } else {
                $arr_failure[] = $resource;
                logScript("[process_gpt_existing] [ERROR] Failed - skipping", $log_file);
            }

            $progress = round(($resource_count / $total_resources) * 100, 0);
            logScript("[process_gpt_existing] [PROGRESS] $progress%", $log_file);
            $resource_count++;
        }
    } else {
        // Sort into an array indexed by nodes so resources with the same data can be processed together
        $nodegroups = [];
        foreach ($arr_toprocess as $resource) {
            $resnodes = get_resource_nodes($resource, $input_field, true, SORT_ASC);
            $nodehash = empty($resnodes) ? "BLANK" : md5(implode(",", array_column($resnodes, "ref")));
            if (!isset($nodegroups[$nodehash])) {
                $nodegroups[$nodehash] = [];
                $nodegroups[$nodehash]["resources"] = [];
                $nodegroups[$nodehash]["nodes"] = $resnodes;
            }
            $nodegroups[$nodehash]["resources"][] = $resource;
        }

        foreach ($nodegroups as $nodehash => $nodegroup) {

            logScript("[process_gpt_existing] Processing resources " . implode(",", $nodegroup["resources"]) . "...", $log_file);

            $strings = ($nodehash != "BLANK" && count($nodegroup["nodes"]) > 0) ? get_node_strings($nodegroup["nodes"]) : [];
            $updated = openai_gpt_update_field($nodegroup["resources"],$targetfield_data,$strings);
            
            if (is_array($updated)) {
                foreach ($updated as $update_ref => $update_result) {
                    if ($update_result) {
                        $arr_success = array_merge($arr_success, array($update_ref));
                        logScript("[process_gpt_existing] $update_ref ...completed", $log_file);
                    } else {
                        $arr_failure = array_merge($arr_failure, array($update_ref));
                        logScript("[process_gpt_existing] [ERROR] $update_ref Failed - skipping", $log_file);
                    }

                    $progress = round(($resource_count / $total_resources) * 100, 0);
                    logScript("[process_gpt_existing] [PROGRESS] $progress%", $log_file);
                    $resource_count++;
                }
            } else {
                $arr_failure = array_merge($arr_failure, $nodegroup["resources"]);
                logScript("[process_gpt_existing] [ERROR] None of the above resources were updated", $log_file);

                $progress = round(($resource_count / $total_resources) * 100, 0);
                logScript("[process_gpt_existing] [PROGRESS] $progress%", $log_file);
                $resource_count += count($nodegroup["resources"]);
            }
        }
    }

    $c_success = count($arr_success);

    if ($c_success > 0) {
        logScript("[process_gpt_existing] $c_success updated", $log_file);
    }

    $c_failure = count($arr_failure);
    if ($c_failure > 0) {
        logScript("[process_gpt_existing] $c_failure resources failed to update", $log_file);
        logScript("[process_gpt_existing] Failed resources: " . implode(",", $arr_failure), $log_file);
    }
} else {
    logScript("[process_gpt_existing] No resources found to process", $log_file);
}

logScript("[process_gpt_existing] Ending process_gpt_existing job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed process_gpt_existing job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);
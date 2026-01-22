<?php

include_once __DIR__ . '/../image_processing.php';

# $job_data['field_refs']      -> Comma-separated list of field references
# $job_data['collection_refs'] -> Optional. Comma-separated list of collection references, can be ranges
# $job_data['blanks']          -> Optional. Boolean, will default to true if not specified
# $job_data['overwrite']       -> Optional. Boolean, will default to false if not specified

logScript("[update_exiftool_field] Starting update_exiftool_field job", $log_file);


// Exiftool check before proceeding
$exiftool_fullpath = get_utility_path("exiftool");

if (!$exiftool_fullpath) {
    logScript("[update_exiftool_field] [ERROR] could not find Exiftool", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

// Process $job_data
$blanks = (bool) $job_data['blanks'] ?? false;
$overwrite = (bool) $job_data['overwrite'] ?? false;

$join = "";
$conditions = [];
$params = [];

if (isset($job_data['collection_refs']) && !empty($job_data['collection_refs'])) {

    $range_condition = build_range_where_condition($job_data['collection_refs'], "cr.collection");

    if ($range_condition["ok"]) {
        $join .= "INNER JOIN collection_resource cr ON r.ref = cr.resource";
        $conditions[] = $range_condition['where'];
        $params = $range_condition['params'];
    } else {
        logScript("[update_exiftool_field] [ERROR] unable to process where condition", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }
}

$processed_field_refs = parse_csv_to_list_of_type($job_data['field_refs'], "is_positive_int_loose");

if (empty($processed_field_refs)) {
    logScript("[update_exiftool_field] [ERROR] unable to process field list", $log_file);
    job_queue_update($jobref, $job_data, STATUS_ERROR);
    return;
}

$resource_list = [];
$total_updates = 0;

foreach ($processed_field_refs as $fieldref) {
    
    $fieldref_info = get_resource_type_field($fieldref);

    if (!$fieldref_info) {
        logScript("[update_exiftool_field] [ERROR] field " . (int) $fieldref . " doesn't exist", $log_file);
        job_queue_update($jobref, $job_data, STATUS_ERROR);
        return;
    }

    $title = (string) $fieldref_info["title"];
    $exiftool_tag = (string) $fieldref_info["exiftool_field"];
    $restypes = !is_null($fieldref_info["resource_types"]) ? explode(",", (string) $fieldref_info["resource_types"]) : [];

    if ($exiftool_tag == "") {
        logScript("[update_exiftool_field] [ERROR] no exiftool mapping for " . escape($title) . " field", $log_file);
        continue;
    }

    if ($fieldref_info["global"] === 1) {
        $rd = ps_query("SELECT r.ref, r.file_extension 
                        FROM resource r
                        $join 
                        WHERE r.ref > 0" . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") .
                        " ORDER BY r.ref", $params);
    } elseif (empty($restypes)) {
        logScript("[update_exiftool_field] [ERROR] field " . $field_ref . " not assigned to any fields or global, skipping...", $log_file);
        continue;
    } else {
        $rd = ps_query("SELECT r.ref, r.file_extension 
                        FROM resource r
                        $join 
                        WHERE r.resource_type IN (" . ps_param_insert(count($restypes)) . ")"                        
                         . ((count($conditions) > 0) ? " AND " . implode(" AND ", $conditions) : "") .  " ORDER BY r.ref", 
                         array_merge(ps_param_fill($restypes, "i"), $params));
    }

    if (!empty($rd)) {
        $resource_list[$fieldref]['field_info'] = $fieldref_info;
        $resource_list[$fieldref]['resources'] = $rd;
        $total_updates += count($rd);
    }    
}

logScript("[update_exiftool_field] $total_updates updates to be processed", $log_file);

$processed_count = 1;

foreach ($resource_list as $field_ref => $field_data) {

    $title = (string) $field_data["field_info"]["title"];
    $name = (string) $field_data["field_info"]["name"];
    $type = (int) $field_data["field_info"]["type"];
    $exiftool_filter = (string) $field_data["field_info"]["exiftool_filter"];
    $exiftool_tag = (string) $field_data["field_info"]["exiftool_field"];
    $restypes = !is_null($field_data["field_info"]["resource_types"]) ? explode(",", (string) $field_data["field_info"]["resource_types"]) : [];

    $exiftool_tags = explode(",", $exiftool_tag);

    foreach ($field_data["resources"] as $resource) {

        $ref = $resource['ref'];
        $extension = $resource['file_extension'];

        $image = get_resource_path($ref, true, "", false, $extension);

        if (file_exists($image)) {

            logScript("[update_exiftool_field] checking resource " . (int) $ref, $log_file);

            if (!$overwrite) {
                $existing = get_data_by_field($ref, $field_ref);
                if (trim($existing) != "") {
                    logScript("[update_exiftool_field] resource " . (int) $ref . " already has data present in the field " . (int) $field_ref . ": " . escape($existing) . ", skipping...", $log_file);
                    
                    $progress = round(($processed_count / $total_updates) * 100, 0);
                    logScript("[update_exiftool_field] [PROGRESS] $progress%", $log_file);
                    $processed_count++;
                    
                    continue;
                }
            }

            $value = "";
            $exiftool_tag = "";

            foreach ($exiftool_tags as $current_exiftool_tag) {
                if (strpos(trim($current_exiftool_tag), " ") !== false) {
                    logScript("[update_exiftool_field] [ERROR] exiftool tags do not use spaces please check the tags used in the fields options for Field " . (int) $fieldref, $log_file);
                    break;
                }

                $command = $exiftool_fullpath . " -s -s -s -f -m -d \"%Y-%m-%d %H:%M:%S\" -" . trim($current_exiftool_tag) . " " . escapeshellarg($image);

                $current_value = iptc_return_utf8(trim(run_command($command)));

                if ($current_value != "-") {
                    # exiftool returned hyphen for unset tag.
                    $value = $current_value;
                    $exiftool_tag = $current_exiftool_tag;
                }

                $plugin = "../../plugins/exiftool_filter_" . safe_file_name($name) . ".php";

                if ($exiftool_filter != "") {
                    eval(eval_check_signed($exiftool_filter));
                }
                if (file_exists($plugin)) {
                    include $plugin;
                }
            }

            if ($blanks) {
                if (trim($value) != "") {
                    if ($type == FIELD_TYPE_DATE) {
                        $invalid_date = check_date_format($value);

                        if (!empty($invalid_date)) {
                            $invalid_date = str_replace("%field%", $name, $invalid_date);
                            $invalid_date = str_replace("%row% ", "", $invalid_date);
                            
                            logScript("[update_exiftool_field] -Exiftool " . escape($invalid_date), $log_file);
                            continue;
                        }
                    }

                    update_field($ref, $field_ref, $value);
                    logScript("[update_exiftool_field] -Exiftool found \"" . escape($value) . "\" embedded in the -" . escape($exiftool_tag) . " tag and applied it to Resource " . (int) $ref . " Field " . (int) $field_ref, $log_file);
                } else {
                    update_field($ref, $field_ref, $value);
                    logScript("[update_exiftool_field] -Exiftool found no value embedded in the " . escape(implode(", ", $exiftool_tags)) . " tag/s and applied \"\" to Resource " . (int) $ref . " Field " . (int) $field_ref, $log_file);
                }
            } else {
                if (trim($value) != "") {
                    if ($type == FIELD_TYPE_DATE) {
                        $invalid_date = check_date_format($value);

                        if (!empty($invalid_date)) {
                            $invalid_date = str_replace("%field%", $name, $invalid_date);
                            $invalid_date = str_replace("%row% ", "", $invalid_date);

                            logScript("[update_exiftool_field] -Exiftool " . escape($invalid_date), $log_file);
                            continue;
                        }
                    }

                    update_field($ref, $field_ref, $value);
                    logScript("[update_exiftool_field] -Exiftool found \"" . escape($value) . "\" embedded in the -" . escape($exiftool_tag) . " tag and applied it to Resource " . (int) $ref . " Field " . (int) $field_ref, $log_file);
                } else {
                    logScript("[update_exiftool_field] -Exiftool found no value embedded in the " . escape(implode(", ", $exiftool_tags)) . " tag/s and has made no changes for Resource " . (int) $ref, $log_file);
                }
            }
        }
        
        $progress = round(($processed_count / $total_updates) * 100, 0);
        logScript("[update_exiftool_field] [PROGRESS] $progress%", $log_file);
        $processed_count++;

    }       
    
}

logScript("[update_exiftool_field] Ending update_exiftool_field job", $log_file);

job_queue_update($jobref, $job_data, STATUS_COMPLETE);
log_activity("Completed update_exiftool_field job $jobref",
                LOG_CODE_JOB_COMPLETED, null, 'job_queue', null, null, null, "", null, true);

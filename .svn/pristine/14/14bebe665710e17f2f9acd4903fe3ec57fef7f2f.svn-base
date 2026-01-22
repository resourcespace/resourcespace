<?php

/**
 * Functions used (mostly) to generate the content needed for CSV files
 */

/**
* Generates the CSV content of the metadata for resources passed in the array
* The CSV is echoed to output for direct download or saved to a file
*
* @param array  $resources  array of resource ids to create a CSV for
* @param bool   $personal   flag to include only fields expected to include personal data
* @param bool   $alldata    flag to include extra data from the resource table
* @param string $outputfile optional file path to output CSV to
*
* @return bool|void TRUE if the file has been created, void if the data has been sent as a direct download
*/
function generateResourcesMetadataCSV(array $resources, $personal = false, $alldata = false, $outputfile = "")
{
    global $lang, $csv_export_add_original_size_url_column, $file_checksums, $k, $scramble_key,
        $get_resource_data_cache,$csv_export_add_data_fields;

    // Write the CSV to a disk to avoid memory issues with large result sets
    $tempcsv = trim($outputfile) != "" ? $outputfile : get_temp_dir() . "/csv_export_" . uniqid() . ".csv";

    $csv_field_headers      = array();
    $csvoptions = array("csvexport" => true,"personal" => $personal,"alldata" => $alldata);
    $allfields = get_resource_type_fields("", "order_by", "asc");
    $cache_location = get_temp_dir();
    $cache_data = array();
    $restypearr = get_resource_types();
    $resource_types = array();
    // Sort into array with ids as keys
    foreach ($restypearr as $restype) {
        $resource_types[$restype["ref"]] = $restype;
    }

    $field_restypes = get_resource_type_field_resource_types();

    // Break resources up into smaller arrays to avoid hitting memory limits
    $resourcebatches = array_chunk($resources, 2000);

    $csv_field_headers["resource_type"] = $lang["resourcetype"];
    $csv_field_headers["status"] = $lang['status'];
    $csv_field_headers["created_by"] = $lang["contributedby"];
    if ($alldata) {
        $csv_field_headers["access"] = $lang['access'];
    }
    if ($file_checksums && $alldata) {
        $csv_field_headers["file_checksum"] = $lang["filechecksum"];
    }
    // Add original size URL column
    if ($csv_export_add_original_size_url_column) {
        $csv_field_headers['original_link'] = $lang['collection_download_original'];
    }

    for ($n = 0; $n < count($resourcebatches); $n++) {
        $resources_fields_data = array();
        $fullresdata = get_resource_field_data_batch($resourcebatches[$n], true, $k != '', true, $csvoptions);

        // Get data for all resources
        $resource_data_array = get_resource_data_batch($resourcebatches[$n]);
        foreach ($resourcebatches[$n] as $resource) {
            $resdata = isset($resource_data_array[$resource]) ? $resource_data_array[$resource] : false;
            if (!$resdata || checkperm("T" . $resdata["resource_type"])) {
                continue;
            }

            // Add resource type
            $restype = get_resource_type_name($resdata["resource_type"]);
            $resources_fields_data[$resource]["resource_type"] = $restype;

            // Add resource status
            $resources_fields_data[$resource]['status'] = $lang["status{$resource_data_array[$resource]['archive']}"] ?? $lang['unknown'];

            if ($alldata) {
            // Add resource access
                $resources_fields_data[$resource]['access'] = $lang["access{$resource_data_array[$resource]['access']}"] ?? $lang['unknown'];
            }

            // Add contributor
            $udata = get_user($resdata["created_by"]);
            if ($udata !== false) {
                $resources_fields_data[$resource]["created_by"] = (trim($udata["fullname"] ?? "") != "" ? $udata["fullname"] :  $udata["username"]);
            }

            if ($alldata) {
                if (isset($csv_export_add_data_fields)) {
                    foreach ($csv_export_add_data_fields as $addfield) {
                        $resources_fields_data[$resource][$addfield["column"]] = $resdata[$addfield["column"]];
                        $csv_field_headers[$addfield["column"]] = $addfield["title"];
                    }
                }
                if ($file_checksums) {
                    $resources_fields_data[$resource]["file_checksum"] = $resdata["file_checksum"];
                }
            }

            foreach ($allfields as $restypefield) {
                if (
                    metadata_field_view_access($restypefield["ref"])
                    &&
                        (!$personal || $restypefield["personal_data"])
                    &&
                        ($alldata || $restypefield["include_in_csv_export"])
                    &&
                        (
                        isset($field_restypes[$restypefield["ref"]]) && in_array($resdata["resource_type"], $field_restypes[$restypefield["ref"]])
                        )
                ) {
                    if (!isset($csv_field_headers[$restypefield["ref"]])) {
                        $csv_field_headers[$restypefield["ref"]] = $restypefield['title'];
                    }
                    // Check if the resource has a value for this field in the data retrieved
                    if (isset($fullresdata[$resource])) {
                        $resdataidx = array_search($restypefield["ref"], array_column($fullresdata[$resource], 'ref'));
                        $fieldvalue = ($resdataidx !== false) ? $fullresdata[$resource][$resdataidx]["value"] : "";
                        $resources_fields_data[$resource][$restypefield['ref']] = $fieldvalue;
                    }
                }
            }

            /*Provide the original URL only if we have access to the resource or the user group
            doesn't have restricted access to the original size*/
            $access = get_resource_access($resdata);
            if (0 != $access || resource_has_access_denied_by_RT_size($resdata['resource_type'], '')) {
                continue;
            }
            if ($csv_export_add_original_size_url_column) {
                $filepath      = get_resource_path($resource, true, '', false, $resdata['file_extension'], -1, 1, false, '', -1, false);
                $original_link = get_resource_path($resource, false, '', false, $resdata['file_extension'], -1, 1, false, '', -1, false);
                if (file_exists($filepath)) {
                    $resources_fields_data[$resource]['original_link'] = $original_link;
                }
            }
        }

        if (count($resources_fields_data) > 0) {
            // Save data to temporay files in order to prevent memory limits being reached
            $tempjson = json_encode($resources_fields_data);
            $cache_data[$n] = $cache_location . "/csv_export_" . md5($scramble_key . $tempjson) . ".json"; // Scrambled path to cache
            file_put_contents($cache_data[$n], $tempjson);
            $tempjson = null;
        }
    }

    $csv_field_headers = array_unique($csv_field_headers);

    // Header
    $header = "\"" . $lang['resourceids'] . "\",\"" . implode('","', $csv_field_headers) . "\"\n";
    file_put_contents($tempcsv, $header);

    // Results
    for ($n = 0; $n < count($resourcebatches); $n++) {
        $filedata = "";
        $resources_fields_data = array();
        if (file_exists($cache_data[$n])) {
            $resources_fields_data = json_decode(file_get_contents($cache_data[$n]), true);
        }
        if (is_null($resources_fields_data)) {
            $resources_fields_data = array();
        }

        foreach ($resources_fields_data as $resource_id => $resource_fields) {
            // First column will always be Resource ID
            $csv_row = $resource_id . ',';

            // Field values
            foreach ($csv_field_headers as $column_header => $column_header_title) {
                if (!array_key_exists($column_header, $resource_fields)) {
                    $csv_row .= '"",';
                    continue;
                }

                foreach ($resource_fields as $field_name => $field_value) {
                    if ($column_header == $field_name) {
                        $csv_row .= '"' . str_replace(array("\""), array("\"\""), i18n_get_translated($field_value)) . '",';
                    }
                }
            }

            $csv_row = rtrim($csv_row, ',');
            $csv_row .= "\n";
            $filedata .= $csv_row;
        }

        // Add this data to the file and delete disk copy of array
        file_put_contents($tempcsv, $filedata, FILE_APPEND);
        if (file_exists($cache_data[$n])) {
            unlink($cache_data[$n]);
        }
    }

    if ($outputfile != "") {
        // Data has been saved to file, just return
        return true;
    }

    // Echo the data for immediate download
    echo file_get_contents($tempcsv);
}

/**
* Generates the file content when exporting nodes
*
* @param array   $field        Array containing field information (as retrieved by get_field)
* @param boolean $send_headers If true, function sends headers used for downloading content. Default is set to false
*
* @return mixed
*/
function generateNodesExport(array $field, $parent = null, $send_headers = false)
{
    global $lang, $FIXED_LIST_FIELD_TYPES;

    if (0 === count($field) || !isset($field['ref']) || !isset($field['type'])) {
        trigger_error('Field array cannot be empty. generateNodesExport() requires at least "ref" and "type" indexes!');
    }

    if (!in_array($field['type'], $FIXED_LIST_FIELD_TYPES)) {
        return false;
    }

    $return = '';
    $nodes  = get_nodes($field['ref'], $parent);

    foreach ($nodes as $node) {
        $return .= "{$node['name']}\r\n";
    }

    log_activity("{$lang['export']} metadata field options - field {$field['ref']}", LOG_CODE_DOWNLOADED);

    if ($send_headers) {
        header('Content-type: application/octet-stream');
        header("Content-disposition: attachment; filename=field{$field['ref']}_nodes_export.txt");

        echo $return;

        ob_flush();
        exit();
    }

    return $return;
}

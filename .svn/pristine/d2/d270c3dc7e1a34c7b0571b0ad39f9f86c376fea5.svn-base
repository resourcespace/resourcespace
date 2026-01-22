<?php

/**
* Format date for EMu use (similar to the ISO8601 date format except
* the time zone designator is not included)
*
* @param integer $timestamp
* @param string  $format    PHP's date() valid format
*
* @return string
*/
function emu_format_date($timestamp, $format = 'c')
{
    if (!is_string($format) || !is_integer($timestamp)) {
        trigger_error('Wrong arguments passed to emu_format_date()');
    }

    $result = date($format, $timestamp);

    if ('c' == $format) {
        $result = substr($result, 0, -6);
    }

    return $result;
}


/**
* Function to retrieve all resources that have their IRN field set to a value
* and that are within the allowed resource types for an EMu update
*
* @return array
*/
function get_emu_resources()
{
    global $emu_irn_field, $emu_resource_types, $emu_created_by_script_field;

    $emu_config_rtfs = array_filter(array_filter(array_map('intval', [$emu_irn_field, $emu_created_by_script_field])), 'is_int_loose');
    $emu_config_rts = array_filter(array_filter(array_map('intval', $emu_resource_types)), 'is_int_loose');
    if (empty($emu_config_rtfs) || empty($emu_config_rts)) {
        return [];
    }

    $sql_rtf_in = ps_param_insert(count($emu_config_rtfs));
    $sql_rt_in = ps_param_insert(count($emu_config_rts));

    $sql = "SELECT 
                   rn.resource AS resource,
                   max(CASE WHEN n.resource_type_field = ? THEN n.`name` ELSE NULL END) AS object_irn,
                   max(CASE WHEN n.resource_type_field = ? THEN n.`name` ELSE NULL END) AS created_by_script_flag,
                   r.file_checksum
              FROM resource_node AS rn
        INNER JOIN node AS n ON rn.node = n.ref AND n.resource_type_field IN ({$sql_rtf_in})
        INNER JOIN resource AS r ON rn.resource = r.ref AND r.resource_type IN ({$sql_rt_in})
             WHERE r.ref > 0
          GROUP BY rn.resource
          ORDER BY r.ref";

    $sql_params = array_merge(
        [
            'i', $emu_irn_field,
            'i', $emu_created_by_script_field,
        ],
        ps_param_fill($emu_config_rtfs, 'i'),
        ps_param_fill($emu_config_rts, 'i')
    );

    return ps_query($sql, $sql_params);
}


/**
* Get EMu data by using an array of IRNs.
*
* @param array $irns             Array of one/ more IRNs to get data by
* @param array $emu_rs_mappings EMu table-column -> RS field mappings
*
* @return array $return[IRN][Column] = Column value
*
* Example:
* [74766] => Array
*         (
*             [ObjTitle] =>
*             [ObjName] => mask
*             [ChaAspectRatio] =>
*         )
*
* [886159] => Array
*         (
*             [ObjTitle] =>
*             [ObjName] => mask
*         )
*/
function get_emu_data($emu_api_server, $emu_api_server_port, array $irns, array $emu_rs_mappings)
{
    # Remove blanks or 0 values from the array as no need to call API for these.
    $irns = array_values(array_filter($irns, 'is_positive_int_loose'));
    if (count($irns) === 0) {
        return array();
    }

    $GLOBALS['use_error_exception'] = true;
    try {
        $emu_api = new EMuAPI($emu_api_server, $emu_api_server_port);
    } catch (Throwable $t) {
        return [];
    }
    unset($GLOBALS['use_error_exception']);

    $return = [];
    foreach ($emu_rs_mappings as $emu_module => $emu_module_columns) {
        $columns_list = array_keys($emu_module_columns);

        $emu_api->setModule($emu_module);
        $emu_api->setColumns($columns_list);

        $objects_data = $emu_api->getObjectsByIrns($irns);

        foreach ($objects_data as $object_data) {
            foreach ($columns_list as $column) {
                if (!array_key_exists($column, $object_data)) {
                    continue;
                }

                $return[$object_data['irn']][$column] = $object_data[$column];
            }
        }
    }

    return $return;
}


/**
* Log EMu script history both on screen and in a file
*
* @param string $message
* @param resource $log_file_pointer
*
* @return void
*/
function emu_script_log($message, $log_file_pointer = null)
{
    $message .= PHP_EOL;

    echo $message;

    if (!is_null($log_file_pointer) && (is_resource($log_file_pointer) && 'file' == get_resource_type($log_file_pointer) || 'stream' == get_resource_type($log_file_pointer))) {
        fwrite($log_file_pointer, $message);
    }
}


/**
* Utility function to figure out emu plugin configuration changed.
*
* For example, can be used in cases when we need to check all records again because of
* new mappings have been added.
*
* @return boolean
*/
function check_config_changed()
{
    global $emu_config_modified_timestamp;

    $script_last_ran = ps_value('SELECT `value` FROM sysvars WHERE name = "last_emu_import"', [], '');

    if ('' == $script_last_ran) {
        return true;
    }

    $emu_script_last_ran = strtotime($script_last_ran);

    if ($emu_config_modified_timestamp > $emu_script_last_ran) {
        return true;
    }

    return false;
}


/**
* Add search criteria to any existing IMuTerms object before doing a search on a module
* Note: uses $emu_search_criteria which value is basic TexQL (currently only AND and OR
* are supported, without support for nesting)
*
* @param IMuTerms $imu_terms Any IMuTerms object on which we want to add new search terms
*
* @return IMuTerms
*/
function add_search_criteria(IMuTerms $imu_terms)
{
    global $emu_search_criteria;

    $kind            = $imu_terms->getKind();
    $search_criteria = trim($emu_search_criteria);

    if (!is_string($search_criteria) && '' == $search_criteria) {
        return $imu_terms;
    }

    // One condition, add it and return IMuTerms object
    if (
        false === strpos($search_criteria, ' AND ')
        && false === strpos($search_criteria, ' OR ')
        && false !== strpos($search_criteria, '=')
    ) {
        $condition = explode('=', $search_criteria);

        if ('' != $condition[0] && '' != $condition[1]) {
            $imu_terms->add($condition[0], $condition[1]);
        }

        return $imu_terms;
    }

    /* Example
    [
        [and] = [
            0 => [column, val]
            1 => [column, val]
        ]
        [or]  = [
            0 => [column, val]
            1 => [column, val]
        ]
    ]
    */
    $conditions = array();

    if (false !== strpos($search_criteria, ' AND ')) {
        $and_search_criterias = explode(' AND ', $search_criteria);

        foreach ($and_search_criterias as $and_search_criteria) {
            // AND condition
            if (false === strpos($and_search_criteria, ' OR ') && false !== strpos($and_search_criteria, '=')) {
                $condition = explode('=', $and_search_criteria);
                $column    = trim($condition[0]);
                $value     = trim($condition[1]);

                if ('' != $column && '' != $value) {
                    $conditions['and'][] = array($column, $value);
                    $search_criteria     = str_replace("{$and_search_criteria} AND ", '', $search_criteria);
                    $search_criteria     = str_replace("{$and_search_criteria}", '', $search_criteria);
                }
            }
        }
    }

    if (false !== strpos($search_criteria, ' OR ')) {
        $and_search_criterias = explode(' OR ', $search_criteria);

        foreach ($and_search_criterias as $and_search_criteria) {
            // OR condition
            if (false === strpos($and_search_criteria, ' OR ') && false !== strpos($and_search_criteria, '=')) {
                $condition = explode('=', $and_search_criteria);
                $column    = trim($condition[0]);
                $value     = trim($condition[1]);

                if ('' != $column && '' != $value) {
                    $conditions['or'][] = array($column, $value);
                    $search_criteria    = str_replace("{$and_search_criteria} OR ", '', $search_criteria);
                    $search_criteria    = str_replace("{$and_search_criteria}", '', $search_criteria);
                }
            }
        }
    }

    // Adding to the actual IMuTerms object based on what kind the original
    // object was in order to end up with a correctly configured IMuTerms object
    foreach ($conditions as $condition => $available_criteria) {
        $use_separate_terms = false;

        if ('and' == $condition && 'and' != $kind) {
            $separate_terms     = $imu_terms->addAnd();
            $use_separate_terms = true;
        }

        if ('or' == $condition && 'or' != $kind) {
            $separate_terms     = $imu_terms->addOr();
            $use_separate_terms = true;
        }

        foreach ($available_criteria as $criteria) {
            if ($use_separate_terms) {
                $separate_terms->add($criteria[0], $criteria[1]);

                continue;
            }

            $imu_terms->add($criteria[0], $criteria[1]);
        }
    }

    return $imu_terms;
}


/**
* Update resource with the newly imported orginal file from EMu
*
* @param integer $ref       Resource ID
* @param integer $type      Resource type ID
* @param string  $file_path Resource original file path
*
* @return boolean
*/
function emu_update_resource($ref, $type, $file_path)
{
    global $file_checksums, $file_checksums_50k, $file_checksums_offline, $enable_thumbnail_creation_on_upload;

    /*Generate checksums now in order to make sure when the SCRIPT compares them against EMu records
    there is a valid value to compare it against otherwise the SCRIPT might think files have been
    changed and will add media files as alternatives*/
    $file_checksums         = true;
    $file_checksums_50k     = false;
    $file_checksums_offline = false;

    if (!is_numeric($ref) || 0 >= $ref || '' == trim($file_path)) {
        return false;
    }

    update_resource_type($ref, $type);

    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

    $sql = "UPDATE resource SET archive = 0, file_extension = ?, preview_extension = ?, file_modified = NOW() WHERE ref = ?";
    $sql_params = array(
        "s",$file_extension,
        "s",$file_extension,
        "i",$ref
    );
    ps_query($sql, $sql_params);

    // Ensure folder is created, then create previews
    get_resource_path($ref, false, 'pre', true, $file_extension);

    // Generate previews / thumbnails (if configured - i.e if not completed by offline process 'create_previews.php')
    if ($enable_thumbnail_creation_on_upload && !create_previews($ref, false, $file_extension)) {
        return false;
    }

    return true;
}


/**
* Utility function to get the mapped ResourceSpace field from
* the mapping we set for emu plugin
*
* @param array  $rs_module_column_field_mappings
* @param string $column                          EMu column that should have a map to a ResourceSpace field
*
* @return integer Returns 0 if could not find value
*/
function emu_get_rs_mapped_field_id($column, array $rs_module_column_field_mappings)
{
    foreach ($rs_module_column_field_mappings as $module_column_field_mappings) {
        if (isset($module_column_field_mappings[$column]) && is_numeric($module_column_field_mappings[$column])) {
            return $module_column_field_mappings[$column];
        }
    }

    return 0;
}


/**
* Update a resource with all the information from an EMu record
* which has mapped fields to ResourceSpace
*
* @param integer $resource                        Resource ID
* @param array   $record                          EMu record processed by emu plugin
* @param array   $rs_module_column_field_mappings
*
* @return boolean
*/
function emu_update_resource_metadata_from_record($resource, array $record, array $rs_module_column_field_mappings)
{
    if (!is_numeric($resource) || 0 === count($record) || 0 === count($rs_module_column_field_mappings)) {
        return false;
    }

    foreach ($record as $record_field => $record_value) {
        $field = emu_get_rs_mapped_field_id($record_field, $rs_module_column_field_mappings);

        // If the record contains other information we don't need to record (ie. not mapped), skip it
        if (0 == $field) {
            continue;
        }

        emu_update_rs_field($resource, $field, $record_value);
    }

    return true;
}


/**
* Update ResourceSpace field based on an EMu record field value (atomic/ non-atomic)
* Note: non-atomic values will be converted to a CSV value
*
* @param integer      $ref          Resource ID
* @param integer      $field        Field ID
* @param string|array $record_value The value of the record field
*
* @return boolean
*/
function emu_update_rs_field($ref, $field, $record_value)
{
    // Atomic values can be saved upfront
    if (is_string($record_value) && '' != $record_value && update_field($ref, $field, $record_value)) {
        return true;
    }

    // Beyond this point, make sure we work with fields that are multi lines to allow
    // big text chunks
    $allowed_field_types = array(FIELD_TYPE_TEXT_BOX_MULTI_LINE, FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE);
    $field_info          = get_field($field);

    if (!in_array($field_info['type'], $allowed_field_types)) {
        return false;
    }

    // Convert non-atomic values into a CSV value
    $field_value = emu_convert_to_atomic($record_value);

    if ('' != $field_value && update_field($ref, $field, $field_value)) {
        return true;
    }

    return false;
}


/**
* Utility function which allows ResourceSpace to convert any EMu record field to an
* atomic field with its value formatted as CSV
*
* @param string|array $values The value client code wants to convert to
*
* @return string CSV formatted string
*/
function emu_convert_to_atomic($values)
{
    $return = '';

    if (!is_array($values)) {
        return $values;
    }

    foreach ($values as $value) {
        $return .= ',' . emu_convert_to_atomic($value);
    }

    return substr($return, 1);
}

<?php
/**
* Get resource table columns relevant to the MuseumPlus integration
* 
* @param array $refs List of resource IDs
* 
* @return array
*/
function mplus_resource_get_data(array $refs)
    {
    $r_refs = array_filter($refs, 'is_int_loose');
    if(empty($r_refs))
        {
        return [];
        }

    return ps_query("SELECT ref, museumplus_data_md5, museumplus_technical_id FROM resource WHERE ref IN (". ps_param_insert(count($r_refs)) .")", ps_param_fill($r_refs, 'i'));
    }


/**
* Mark resource failed validating the MuseumPlus association
* 
* @param array $resources List of resource IDs (key) and computed MD5 hashes (value). {@see mplus_compute_data_md5()}
* 
* @return void
*/
function mplus_resource_mark_validation_failed(array $resources)
    {
    if(empty($resources))
        {
        return;
        }

    $qvals = [];
    $params = [];
    foreach($resources as $ref => $md5)
        {
        // Sanitise input
        if((string)(int) $ref !== (string) $ref || $md5 === '')
            {
            continue;
            }

        // Prepare SQL query values
        $qvals[$ref] = '(?, ? , NULL)';
        $params = array_merge($params, ['i', $ref, 's', $md5]);
        }
    if(empty($qvals)) { return; }

    // Validate the list of resources input to avoid creating new resources. We only want to update existing ones
    $valid_refs = ps_array("SELECT ref AS `value` FROM resource WHERE ref IN (". ps_param_insert(count($qvals)) .")", ps_param_fill(array_keys($qvals), 'i'));
    if(empty($valid_refs)) { return; }

    // Update resources with the new computed MD5s
    $query = "INSERT INTO resource (ref, museumplus_data_md5, museumplus_technical_id) VALUES " . implode(',', $qvals) ."
                       ON DUPLICATE KEY UPDATE museumplus_data_md5 = VALUES(museumplus_data_md5), museumplus_technical_id = VALUES(museumplus_technical_id)";
    ps_query($query, $params);

    mplus_log_event('Validation failed!', [ 'resources' => $valid_refs], 'error');
    }


/**
* Mark resource module association as valid. This updates the MD5 hash for the current combination of "module name - MpID"
* and the valid technical ID retrieved from MuseumPlus.
* 
* @param array $resources List of resource IDs (key) and MuseumPlus technical ID - ie. "__id" - as value.
* @param array $md5s      List of resource IDs (key) and computed MD5 hashes (value). {@see mplus_compute_data_md5()}
* 
* @return void
*/
function mplus_resource_update_association(array $resources, array $md5s)
    {
    if(empty($resources))
        {
        return;
        }

    $qvals  = [];
    $params = [];
    foreach($resources as $ref => $mplus_technical_id)
        {
        // Sanitise input
        if(!is_int_loose($ref))
            {
            continue;
            }

        $md5 = (isset($md5s[$ref]) ? $md5s[$ref] : '');

        // Prepare SQL query values        
        $addvals = ['?'];
        $params[] = 'i';
        $params[] = $ref;
        if($md5 == '')
            {
            $addvals[] = 'NULL';
            }
        else
            {
            $addvals[] = '?';
            $params[] = 's';
            $params[] = $md5;
            }

        if($mplus_technical_id == '')
            {
            $addvals[] = 'NULL';
            }
        else
            {
            $addvals[] = '?';
            $params[] = 'i';
            $params[] = $mplus_technical_id;
            }
        $qvals[$ref] = implode(',',$addvals);
        }
    if(empty($qvals)) { return; }

    // Validate the list of resources input to avoid creating new resources. We only want to update existing ones
    $valid_refs = ps_array("SELECT ref AS `value` FROM resource WHERE ref IN (". ps_param_insert(count($qvals)) .")", ps_param_fill(array_keys($qvals), 'i'));
    if(empty($valid_refs)) { return; }

    // Update resources with the new computed MD5s
    $sql_strings = array_intersect_key($qvals, array_flip($valid_refs));
    $q = "INSERT INTO resource (ref, museumplus_data_md5, museumplus_technical_id) VALUES (". implode('),(', $sql_strings) .")
                   ON DUPLICATE KEY UPDATE museumplus_data_md5 = VALUES(museumplus_data_md5), museumplus_technical_id = VALUES(museumplus_technical_id)";
    ps_query($q, $params);

    mplus_log_event('Updated resource module association!', [ 'qvals' => $qvals], 'info');
    }


/**
* Clear resource metadata fields that are mapped to any of the modules configured by the plugin.
* 
* @param array $refs List of resource IDs
* 
* @return void
*/
function mplus_resource_clear_metadata(array $refs)
    {
    mplus_log_event('Called mplus_resource_clear_metadata()', ['refs' => $refs], 'debug');

    global $museumplus_modules_saved_config, $museumplus_clear_field_mappings_on_change;
    $refs = array_filter($refs, 'is_int');

    if(
        empty($refs)
        // No modules configured
        || is_null($museumplus_modules_saved_config) || $museumplus_modules_saved_config === ''
        // System configured to not clear existing (old) MuseumPlus data on change
        || $museumplus_clear_field_mappings_on_change === false
    )
        {
        return;
        }

    // Get list of unique metadata fields that are mapped to MuseumPlus modules' fields
    $resource_type_fields = [];
    foreach(plugin_decode_complex_configs($museumplus_modules_saved_config) as $module_cfg)
        {
        $resource_type_fields = array_merge($resource_type_fields, array_column($module_cfg['field_mappings'], 'rs_field'));
        }
    $resource_type_fields = array_values(array_filter(array_unique($resource_type_fields), 'ctype_digit'));
    if(empty($resource_type_fields)) { return; }

    $ref_params = ps_param_fill($refs, 'i');
    $rtf_params = ps_param_fill($resource_type_fields, 'i');

    ps_query("DELETE rn FROM resource_node AS rn LEFT JOIN node AS n ON n.ref = rn.node LEFT JOIN resource_type_field AS rtf ON rtf.ref = n.resource_type_field 
              WHERE rn.resource IN (". ps_param_insert(count($refs)) .") AND rtf.ref IN (". ps_param_insert(count($resource_type_fields)) .")", 
              array_merge($ref_params, $rtf_params)
    );

    // Clear related 'joined' fields
    $joins = get_resource_table_joins();
    $sql_joins = '';
    foreach($joins as $join)
        {
        if(!is_int_loose($join) || !in_array($join, $resource_type_fields))
            {
            continue;
            }
        $sql_joins .= 'field' . $join . ' = NULL,';
        }

    if($sql_joins !== '')
        {
        $sql_joins = trim($sql_joins, ',');
        ps_query("UPDATE resource SET {$sql_joins} WHERE ref IN (". ps_param_insert(count($refs)) .")", $ref_params);
        }

    mplus_log_event('Cleared metadata field values', ['refs' => $refs, 'resource_type_fields' => $resource_type_fields]);
    }


/**
* Get all resources associated with a MuseumPlus module.
* 
* @param array $filters Rules to filter results (if applicable). There are "flag" filters (e.g new_and_changed_associations filter)
*                       and filters that take arguments (e.g byref)
* 
* @return array
*/
function mplus_resource_get_association_data(array $filters)
    {
    if(
        !isset($GLOBALS['museumplus_module_name_field'], $GLOBALS['museumplus_modules_saved_config'])
        || !(is_string($GLOBALS['museumplus_modules_saved_config']) && $GLOBALS['museumplus_modules_saved_config'] !== '')
    )
        {
        return [];
        }

    // Additional filters (as required by caller code)
    $additional_filters = [];
    $additional_params = [];
    foreach(mplus_validate_resource_association_filters($filters) as $filter_name => $filter_args)
        {
        switch($filter_name)
            {
            case 'byref':
                $refs = array_filter($filter_args, 'is_int_loose');
                $additional_filters[] = 'AND r.ref IN ('. ps_param_insert(count($refs)) .')';
                $additional_params = array_merge($additional_params, ps_param_fill($refs, 'i'));
                break;
            }
        }


    // When plugin is not configured to store the module name in a metadata field, then we fallback to the "Object" module
    // because the plugin used to work only for that module so it's assumed a safe choice. If the Object module config
    // is not found then there's nothing to process.
    $field_to_hold_module_name_set = (is_int_loose($GLOBALS['museumplus_module_name_field']) && $GLOBALS['museumplus_module_name_field'] > 0);
    if(!$field_to_hold_module_name_set)
        {
        $object_mcfg = mplus_get_cfg_by_module_name('Object');
        if(empty($object_mcfg))
            {
            return [];
            }

        $applicable_resource_types = array_filter($object_mcfg['applicable_resource_types'], 'is_int_loose');
        if(empty($applicable_resource_types))
            {
            return [];
            }

        return ps_array('SELECT r.ref `value` FROM resource r WHERE r.archive = 0 
                          AND r.resource_type IN ('. ps_param_insert(count($applicable_resource_types)) .') ' . implode(PHP_EOL, $additional_filters),
                          array_merge(ps_param_fill($applicable_resource_types, 'i'), $additional_params)
                        );
        }

    // Get filters required at a "per module configuration" level.
    // IMPORTANT: do not continue if the plugin isn't properly configured (ie. this information is missing or corrupt)
    $rs_uid_fields = [];
    $per_module_cfg_filters = [];
    $params = [];
    $modules_config = plugin_decode_complex_configs($GLOBALS['museumplus_modules_saved_config']);
    foreach($modules_config as $mcfg)
        {
        $module_name = $mcfg['module_name'];
        $rs_uid_field = $mcfg['rs_uid_field'];
        $applicable_resource_types = array_filter($mcfg['applicable_resource_types'], 'is_int_loose');

        if(is_int_loose($rs_uid_field) && $rs_uid_field > 0 && !in_array($rs_uid_field, $rs_uid_fields))
            {
            $rs_uid_fields[] = $rs_uid_field;
            }

        if($module_name !== '' && !empty($applicable_resource_types) && is_int_loose($rs_uid_field) && $rs_uid_field > 0)
            {
            $per_module_cfg_filters[] = '('. ($module_name === 'Object' ? 'coalesce(n.`name`, \'Object\')' : 'n.`name`')  .' = ? AND r.resource_type IN('. ps_param_insert(count($applicable_resource_types)) .'))';
            $params[] = 's'; $params[] = $module_name;
            $params = array_merge($params, ps_param_fill($applicable_resource_types, 'i'));
            }
        }
    if(empty($rs_uid_fields) || empty($per_module_cfg_filters)) { return []; }


    $module_name_field_ref = $GLOBALS['museumplus_module_name_field'];

    return ps_array('SELECT r.ref AS `value`
                        FROM resource AS r
                    LEFT JOIN resource_node AS rn ON r.ref = rn.resource
                    LEFT JOIN node AS n ON rn.node = n.ref AND n.resource_type_field = ?
                    WHERE r.archive = 0
                        '. 'AND (' . PHP_EOL . implode(PHP_EOL . 'OR ', $per_module_cfg_filters) . PHP_EOL . ')' .' # Filters specific to each module configuration (e.g applicable resource types)
                        '.  implode(PHP_EOL, $additional_filters) .' # Additional filters
                    GROUP BY r.ref
                    ORDER BY r.ref DESC', array_merge(['i', $module_name_field_ref], $params, $additional_params)
    );
    }

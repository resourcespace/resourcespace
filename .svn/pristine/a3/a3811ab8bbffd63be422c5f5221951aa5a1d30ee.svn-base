<?php
/**
* Execute a cURL request
* 
* @param string $url            URL
* @param string $basic_auth     Username and password used for basic authentication.
*                               MUST follow the "username:password" syntax to work!
* @param string $content_type   Content type header value (e.g application/xml)
* @param string $request_method HTTP request methods (e.g GET, POST, PUT, DELETE) 
* @param string $data           Posted data (e.g XML)
* 
* @return array Response information such status code (e.g 200), headers and actual body
*/
function do_http_request($url, $basic_auth, $content_type, $request_method, $data)
    {
    $curl_handle = curl_init();

    curl_setopt($curl_handle, CURLOPT_URL, $url);
    curl_setopt($curl_handle, CURLOPT_HEADER, false);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);

    if(strpos($basic_auth, ':') !== false)
        {
        list($username, $password) = explode(':', $basic_auth);

        curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_handle, CURLOPT_USERPWD, "{$username}:{$password}");
        }

    // Set HTTP headers
    $request_http_headers = array();

    if(trim($content_type) != '')
        {
        $request_http_headers[] = "Content-Type: {$content_type}";
        }

    if(!empty($request_http_headers))
        {
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $request_http_headers);
        }
    // End of setting HTTP headers

    // Set request method and posted data
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $request_method);
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);

    // Get a list of response headers in order to have the ability to react to them (e.g can be useful to avoid reaching 
    // rate limits or knowing content type)
    $curl_response_headers = array();
    curl_setopt(
        $curl_handle,
        CURLOPT_HEADERFUNCTION,
        function($curl, $header) use (&$curl_response_headers)
            {
            $length = strlen($header);
            $header = explode(':', $header, 2);

            // Invalid header
            if(count($header) < 2)
                {
                return $length;
                }

            $name = strtolower(trim($header[0]));

            if(!array_key_exists($name, $curl_response_headers))
                {
                $curl_response_headers[$name] = array(trim($header[1]));
                }
            else
                {
                $curl_response_headers[$name][] = trim($header[1]);
                }

            return $length;
            }
    );

    if ($GLOBALS['debug_log'] && $curl_verbose_stream_handle = fopen('php://temp', 'w+'))
        {
        curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
        curl_setopt($curl_handle, CURLOPT_STDERR, $curl_verbose_stream_handle);
        }

    $result = curl_exec($curl_handle);
    if ($GLOBALS['debug_log'])
        {
        debug(sprintf('[do_http_request][curl] POSTd data >>> %s <<<', str_replace(PHP_EOL, '', $data)));
        
        if (is_resource($curl_verbose_stream_handle))
            {
            rewind($curl_verbose_stream_handle);
            debug(sprintf(
                '[do_http_request][curl] Verbose request-response output%s%s',
                PHP_EOL,
                stream_get_contents($curl_verbose_stream_handle)
            ));
            }

        if ($result === false)
            {
            debug(sprintf('[do_http_request][curl] errno #%d: %s', curl_errno($curl_handle), curl_error($curl_handle)));
            }
        }

    $response_status_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    curl_close($curl_handle);

    $response = array(
        'status_code' => $response_status_code,
        'headers'     => $curl_response_headers,
        'result'      => $result,
    );
    debug(sprintf(
        '[do_http_request][curl] Response (status = %d) >>> %s <<<',
        $response_status_code,
        str_replace(PHP_EOL, '', $result)
    ));

    return $response;
    }


/**
* Helper function to ensure required connection data was provided
* 
* @see http://docs.zetcom.com/ws/
* 
* @return array
*/
function mplus_get_connection_data()
    {
    global $museumplus_host, $museumplus_host_api, $museumplus_application, $museumplus_api_user, $museumplus_api_pass;

    # Don't check $museumplus_application - this isn't used with the newer style M+ URLs.
    if(trim($museumplus_host) == '' || trim($museumplus_api_user) == '' || trim($museumplus_api_pass) == '')
        {
        mplus_log_event('Missing MuseumPlus API configuration (host, API username or API password', array(), 'error');
        return array();
        }

    return array(
        'host'        => ($museumplus_host_api==''?$museumplus_host:$museumplus_host_api), // Use the separate API host config if set
        'application' => $museumplus_application,
        'username'    => $museumplus_api_user,
        'password'    => $museumplus_api_pass);
    }


/**
* Send notifications to users regarding MuseumPlus events (e.g script failed)
* 
* @uses message_add()
* 
* @param array   $users    List of users' IDs
* @param string  $message  Notification message
* 
* @return boolean TRUE on success FALSE on failure
*/
function mplus_notify(array $users, $message)
    {
    if(count($users) == 0 || trim($message) === '')
        {
        return false;
        }

    message_add($users, $message);

    return true;
    }


/**
* Generate a MuseumPlus URL for any module records without having to know the form name
* 
* @param string  $module Module name
* @param integer $id     Module record technical (internal) ID
* 
* @return string Returns the generated URL or empty string on failure
*/
function mplus_generate_module_record_url(string $module, int $id)
    {
    global $museumplus_host, $museumplus_application;

    $host = trim($museumplus_host, " \n\r\t\v\0/");
    $application = trim($museumplus_application, " \n\r\t\v\0/");
    $module = trim($module, " \n\r\t\v\0/");
    $id = ($id > 0 ? $id : '');

    if($host == '' || $module == '' || $id == '')
        {
        return '';
        }

    if ($application != '')
        {
        return sprintf('%s/%s/v#!m/%s/%s',
            $host,
            $application,
            escape($module),
            escape($id));
        }
    else
        {
        return sprintf('%s/v#!m/%s/%s',
            $host,
            escape($module),
            escape($id));
        }
    }


/**
* Save MuseumPlus modules configuration
* 
* @param array $cf Modules configuration to save
* 
* @return void
*/
function mplus_save_module_config(array $cf)
    {
    $mplus_config = get_plugin_config('museumplus');
    if(is_null($mplus_config))
        {
        $mplus_config = array();
        }

    $mplus_config['museumplus_modules_saved_config'] = plugin_encode_complex_configs($cf);

    if(is_plugin_activated('museumplus'))
        {
        set_plugin_config('museumplus', $mplus_config);
        }
    }


/**
* For a list of resources, obtain the associated modules' configuration.
* IMPORTANT: make sure the return of this function is not exposed to the end user when called using the $with_values param.
* 
* @param array   $resource_refs List of resource IDs
* @param boolean $with_values   Should associated module configurations include the RS metadata fields values (this applies
*                               to module configurations that are using a metadata field - e.g. rs_uid_field or the field mappings).
* 
* @return array The associated modules' configuration for each of the resources in the list -or- an empty array.
*/
function mplus_get_associated_module_conf(array $resource_refs, bool $with_values)
    {
    if(empty($resource_refs))
        {
        return array();
        }

    global $museumplus_module_name_field;

    $modules_cfgs = [];
    $rn_batch = get_resource_nodes_batch($resource_refs, array($museumplus_module_name_field), true);
    $found_resources_nodes = !empty($rn_batch);

    // Each resource can only be linked to one module (for syncing data purposes). If the module name field is not defined/set,
    // fallback to 'Object' (initially this plugin only worked with the Object module so it's considered a safe default value)
    $object_cfg = mplus_get_cfg_by_module_name('Object');
    if(!empty($object_cfg)) { $modules_cfgs['Object'] = $object_cfg; }

    // No modules linked to these resources and the fallback module configuration (Object) can't be found either, return early
    if(empty($object_cfg) && !$found_resources_nodes)
        {
        return array();
        }

    // Don't honour user permissions as this data shouldn't reach the end user. It should be used to trigger other processes (as needed based on the values).
    $rfd_batch = ($with_values ? get_resource_field_data_batch($resource_refs, false) : array());

    $resources_with_assoc_module_config = array();
    foreach($resource_refs as $r_ref)
        {
        // Resource is "linked" with a module. Find the respective module configuration and associate it if found
        if(isset($rn_batch[$r_ref][$museumplus_module_name_field][0]['name']))
            {
            // Note: museumplus_module_name_field should already be constrained to fixed list fields that support only 
            // one value (ie. dropdown and radio) on the setup_module.php page
            $resource_module_name = $rn_batch[$r_ref][$museumplus_module_name_field][0]['name'];

            if(isset($modules_cfgs[$resource_module_name]))
                {
                $module_cfg = $modules_cfgs[$resource_module_name];
                }
            else
                {
                $module_cfg = mplus_get_cfg_by_module_name($resource_module_name);
                $modules_cfgs[$resource_module_name] = $module_cfg;
                }
            if(empty($module_cfg))
                {
                mplus_log_event(
                    'Unable to find module configuration',
                    array(
                        'resource' => $r_ref,
                        'museumplus_module_name_field' => $museumplus_module_name_field,
                        'resource_module_name' => $resource_module_name,
                    ),
                    'error');

                continue;
                }

            $resources_with_assoc_module_config[$r_ref] = $module_cfg;
            }
        // Resource isn't "linked" with a module. Fallback to 'Object' (initially this plugin only worked with the Object
        // module so it's considered a safe default value as long as the plugin is still configured for the Object module).
        elseif(!isset($rn_batch[$r_ref][$museumplus_module_name_field][0]['name']) && !empty($object_cfg))
            {
            $resources_with_assoc_module_config[$r_ref] = $object_cfg;
            }
        // None of the resources had an explicit link with a module. Fallback to the "Object" module configuration (if it exists)
        elseif(!$found_resources_nodes && !empty($object_cfg))
            {
            $resources_with_assoc_module_config[$r_ref] = $object_cfg;
            }
        else
            {
            mplus_log_event('Unable to determine the associated module configuration. MuseumPlus plugin should have at least the "Object" module configured', array('resource_ref' => $r_ref), 'error');
            continue;
            }


        // Include the metadata field values (if required - $with_values)
        if($with_values)
            {
            // No data found at all for this resource? No point in returning this resource record, we won't be able to process it in this state.
            if(!isset($rfd_batch[$r_ref]))
                {
                unset($resources_with_assoc_module_config[$r_ref]);
                continue;
                }

            $rs_uid_field = $resources_with_assoc_module_config[$r_ref]['rs_uid_field'];
            $field_mappings_rs_fields = array_column($resources_with_assoc_module_config[$r_ref]['field_mappings'], 'rs_field');
            $rs_fields = array_merge(array($rs_uid_field), $field_mappings_rs_fields);

            // Array of resource type field data for all the fields involved with this associated module configuration (e.g for rs_uid_field
            // or each of the field_mappings). Keys are the resource type field ref and values are the value of that field for this resource
            $assoc_rtf_data = array();

            foreach($rfd_batch[$r_ref] as $resource_field_data)
                {
                if(!in_array($resource_field_data['ref'], $rs_fields))
                    {
                    continue;
                    }

                $assoc_rtf_data[$resource_field_data['ref']] = $resource_field_data['value'];
                }

            // All mapped fields need to have a value. Default to empty string.
            $rs_no_val_fields = array_diff($rs_fields, array_keys($assoc_rtf_data));
            foreach($rs_no_val_fields as $no_val_field)
                {
                if($no_val_field == $rs_uid_field)
                    {
                    $assoc_rtf_data = [$rs_uid_field => ''] + $assoc_rtf_data;
                    continue;
                    }

                $assoc_rtf_data[$no_val_field] = '';
                }

            $resources_with_assoc_module_config[$r_ref]['field_values'] = $assoc_rtf_data;
            }
        }

    return $resources_with_assoc_module_config;
    }


/**
* Find the module configuration using the module name.
* 
* @param string $n Module name to search by in the plugin configuration
* 
* @return array Returns the module configuration record found. @see $museumplus_modules_saved_config elements for the array structure 
*/
function mplus_get_cfg_by_module_name(string $n)
    {
    global $museumplus_modules_saved_config;

    if(is_null($museumplus_modules_saved_config) || $museumplus_modules_saved_config === '')
        {
        return array();
        }

    $museumplus_modules_config = plugin_decode_complex_configs($museumplus_modules_saved_config);

    // Used a foreach (instead of array_search) because $museumplus_modules_config indexes start from 1 as these are used 
    // as end user record IDs and they might not be in order. Using array_search you'll have to offset it by one (as it 
    // doesn't honour the original keys) and you can potentially return the wrong configuration back.
    foreach($museumplus_modules_config as $mod_cfg_id => $module_cfg)
        {
        if($module_cfg['module_name'] == $n)
            {
            mplus_log_event('Found module configuration', ['module_name' => $n],'debug');
            $found_index = $mod_cfg_id;
            break;
            }
        }

    if(isset($found_index))
        {
        return $museumplus_modules_config[$found_index];
        }

    return array();
    }


/**
* Validate a modules' record ID (technical or virtual)
* 
* @param array   $ramc             Resources associated module configurations. {@see mplus_get_associated_module_conf()}
* @param boolean $use_technical_id Validate using the technical ID (ie __id) fieldPath.
* @param boolean $force            Force re-validation even if association state is already known.
* 
* @return array Returns the valid resources that have a valid combination of "module name - MpID (virtual or technical)".
* IMPORTANT: Each returned resource associated module configuration will get mutated with an additional "__id" key 
*            which will always hold the technical ID of the module item "linked" to that resource - always use
*            MPLUS_FIELD_ID constant to find this key.
* An optional "errors" key may be added to the return array to hold any errors the end user should be aware of.
*/
function mplus_validate_association(array $ramc, bool $use_technical_id, bool $force)
    {
    debug(sprintf("mplus_validate_association(): mplus_validate_association(use_technical_id = %s)", json_encode($use_technical_id)));
    mplus_log_event('Called mplus_validate_association()', ['use_technical_id' => $use_technical_id], 'debug');

    global $lang, $museumplus_api_batch_chunk_size;

    $valid_ramc = [];
    $ramc_to_retry = [];
    $errors = [];

    $modules = mplus_flip_struct_by_module($ramc);
    foreach($modules as $module_name => $mdata)
        {
        // Remove resources that don't have the MuseumPlus identifier (MpID). The information that really "links" to a MuseumPlus module record.
        $empty_mpid = [];
        $empty_mpid_md5 = [];
        $non_empty_mpid = [];
        foreach($mdata['resources'] as $r_ref => $r_mpid)
            {
            if($r_mpid != '')
                {
                $non_empty_mpid[$r_ref] = $r_mpid;
                continue;
                }

            // Prepare to clear the association data in the resource table
            $empty_mpid[$r_ref] = '';
            $empty_mpid_md5[$r_ref] = '';
            }
        mplus_resource_update_association($empty_mpid, $empty_mpid_md5);
        $mdata['resources'] = $non_empty_mpid;

        $computed_md5s = mplus_compute_data_md5($mdata['resources'], $module_name);
        $resources_mpdata = mplus_resource_get_data(array_keys($mdata['resources']));
        debug("mplus_validate_association(): module_name = {$module_name}");
        debug("mplus_validate_association(): computed_md5s = " . json_encode($computed_md5s));

        $resources_to_validate = [];
        foreach($resources_mpdata as $r_mpdata)
            {
            /*
            md5_hash | __id  | state
            ========================
            null     | null  | to validate
            value    | null  | validation failed previously, skip now. Users need to relook at the modulename-mpid combo or an admin needs to determine what happened
            value    | value | already valid, add technical id (__id) to valid_ramc for further processing (e.g syncing data)
            != value | value | to validate. Module name and/or MpID changed so we need to revalidate (and clear the current __id) the association
            */
            $r_ref = $r_mpdata['ref'];
            $r_technical_id = trim((string) $r_mpdata['museumplus_technical_id']);
            $r_md5 = trim((string) $r_mpdata['museumplus_data_md5']);
            debug(sprintf(
                'mplus_validate_association(): Checking resource #%s with technical_id = "%s" and MD5 = "%s"',
                $r_ref,
                $r_technical_id,
                $r_md5
            ));

            // The computed MD5 helps us figure out if either the module name or MpID have changed since our last attempt. 
            // A different computed MD5 should always trigger validation of the resource-module association
            $r_computed_md5 = (isset($computed_md5s[$r_ref]) ? trim($computed_md5s[$r_ref]) : '');

            // Validation failed previously for this resource-module association (one validation attempt has been made) and
            // no changes have been recorded to the "module name - MpID" combo
            if(!$force && $r_md5 !== '' && $r_md5 === $r_computed_md5 && $r_technical_id === '')
                {
                unset($computed_md5s[$r_ref]);
                continue;
                }
            // No changes have been recorded to the "module name - MpID" combo and this resource-module association is valid
            // and we have a technical (ie. "__id") ID to use for further processing (e.g syncing data from M+)
            elseif(!$force && $r_md5 !== '' && $r_md5 === $r_computed_md5 && $r_technical_id !== '' && is_numeric($r_technical_id))
                {
                $valid_ramc[$r_ref] = $ramc[$r_ref] + [MPLUS_FIELD_ID => $r_technical_id];
                unset($computed_md5s[$r_ref]);
                continue;
                }

            $resources_to_validate[$r_ref] = $mdata['resources'][$r_ref];
            }
        debug("mplus_validate_association(): resources_to_validate = " . json_encode($resources_to_validate));

        $field_path = ($use_technical_id ? MPLUS_FIELD_ID : $mdata['mplus_id_field']);
        // The technical ID will always be returned as an attribute of the moduleItem element, but the virtual field needs to be specifically selected.
        $select_fields = array($field_path);

        $run_search_using_technical_id = ($field_path === MPLUS_FIELD_ID);
        debug("mplus_validate_association(): run_search_using_technical_id = " . json_encode($run_search_using_technical_id));
        if($run_search_using_technical_id)
            {
            $resources_to_validate = array_filter($resources_to_validate, 'is_numeric');
            debug("mplus_validate_association(): Filtered resources by numeric MpID");
            $non_numeric_resources = array_diff_key($computed_md5s, $resources_to_validate);
            debug("mplus_validate_association(): Resources that that don't have a numeric MpID (to be marked invalid) = " . json_encode($non_numeric_resources));

            if(!empty($non_numeric_resources))
                {
                mplus_resource_mark_validation_failed($non_numeric_resources);
                $errors[] = $lang['museumplus_error_invalid_association'];
                }
            }

        foreach(array_chunk($resources_to_validate, $museumplus_api_batch_chunk_size, true) as $resources_chunk)
            {
            debug("mplus_validate_association(): resources_chunk = " . json_encode($resources_chunk));

            $search_xml = mplus_xml_search_by_fieldpath($field_path, $resources_chunk, $select_fields);
            $mplus_search = mplus_search($module_name, $search_xml);
            if(empty($mplus_search))
                {
                // Search failed for some reason. Move to the next chunk silently (do not exit process) and don't try 
                // to validate using the technical ID (if this validation used a virtual one)
                continue;
                }
            $mplus_search_xml = mplus_get_response_xml($mplus_search);

            $module_node = $mplus_search_xml->getElementsByTagName("module")->item(0);
            if($module_node->hasAttributes())
                {
                $totalSize = $module_node->attributes->getNamedItem('totalSize')->value;

                // No module items found
                if($totalSize == 0)
                    {
                    // First attempt failed, push these ramcs to be validated using the technical ID instead
                    if(!$run_search_using_technical_id)
                        {
                        $ramc_to_retry += array_intersect_key($ramc, $resources_chunk);
                        }
                    // Validating using the technical ID (ie "__id") fieldPath failed. Update MD5 hashes and move on to next chunk.
                    else
                        {
                        mplus_resource_mark_validation_failed(array_intersect_key($computed_md5s, $resources_chunk));
                        $errors[] = $lang['museumplus_error_invalid_association'];
                        }

                    continue;
                    }
                // Search returned more records than we searched for. Issue on MuseumPlus side, a virtual ID has been re-used
                // on multiple module items. ResourceSpace would be unable to determine which module item is the one 
                // meant to be "linked" to the resource.
                elseif($totalSize > count($resources_chunk))
                    {
                    mplus_log_event(
                        'mplus_validate_association(): Search responded with more records than we searched for',
                        array(
                            'module_name' => $module_name,
                            'resources_chunk' => $resources_chunk,
                            'searched_by_technical_id' => $run_search_using_technical_id,
                        ),
                        'error');
                    $errors[] = $lang['museumplus_id_returns_multiple_records'];

                    mplus_resource_mark_validation_failed(array_intersect_key($computed_md5s, $resources_chunk));
                    continue;
                    }
                }

            // Process and move to valid_ramc any resource that got a module item back using its associated MpID
            $found_valid_associations = [];
            if($run_search_using_technical_id)
                {
                foreach($mplus_search_xml->getElementsByTagName('systemField') as $system_field)
                    {
                    if($system_field->getAttribute('name') !== MPLUS_FIELD_ID)
                        {
                        continue;
                        }

                    $technical_id_value = $system_field->nodeValue;

                    foreach($resources_to_validate as $r_ref => $r_mpid)
                        {
                        if($r_mpid != $technical_id_value)
                            {
                            continue;
                            }

                        $found_valid_associations[$r_ref] = $technical_id_value;
                        $valid_ramc[$r_ref] = $ramc[$r_ref] + [MPLUS_FIELD_ID => $technical_id_value];
                        }
                    }
                }
            else
                {
                foreach($mplus_search_xml->getElementsByTagName('virtualField') as $virtual_field)
                    {
                    if($virtual_field->getAttribute('name') !== $field_path)
                        {
                        continue;
                        }

                    $vrt_field_value = $virtual_field->nodeValue;
                    $technical_id_value = $virtual_field->parentNode->getAttribute('id');

                    if($technical_id_value === '')
                        {
                        continue;
                        }

                    foreach($resources_to_validate as $r_ref => $r_mpid)
                        {
                        if(mb_strtolower($r_mpid) != mb_strtolower($vrt_field_value))
                            {
                            continue;
                            }

                        $found_valid_associations[$r_ref] = $technical_id_value;
                        $valid_ramc[$r_ref] = $ramc[$r_ref] + [MPLUS_FIELD_ID => $technical_id_value];
                        }
                    }
                }

            // Save computed_md5s (at this point we know data has changed and we've revalidated) and the valid associated technical ID
            mplus_resource_update_association($found_valid_associations, $computed_md5s);

            // Handle remaining invalid module associations
            $invalid_associations = array_diff_key($resources_chunk, $found_valid_associations);
            if(!empty($invalid_associations))
                {
                debug("mplus_validate_association(): invalid_associations = " . json_encode($invalid_associations));

                // First attempt failed, push these ramcs to be validated using the technical ID instead
                if(!$run_search_using_technical_id)
                    {
                    $ramc_to_retry += array_intersect_key($ramc, $invalid_associations);
                    }
                // Validating using the technical ID (ie "__id") fieldPath failed. Update MD5 hashes and move on to next chunk.
                else
                    {
                    mplus_resource_mark_validation_failed(array_intersect_key($computed_md5s, $invalid_associations));
                    $errors[] = $lang['museumplus_error_invalid_association'];
                    }
                }
            }
        }


    // One last attempt to validate the resource-module associations, this time using the technical ID (__id).
    if(!empty($ramc_to_retry))
        {
        $valid_ramc += mplus_validate_association($ramc_to_retry, true, $force);
        }

    if(!empty($errors))
        {
        $valid_ramc['errors'] = array_unique($errors);
        }

    return $valid_ramc;
    }


/**
* Transpose a resource associated module config array to one ready to be used for batch searching via MuseumPlus API. 
* Utility function for validation & syncing.
* 
* @param array $ramc Resources associated module configurations. {@see mplus_get_associated_module_conf()}
* 
* @return array Returns array structure where the key is the module name and value contains information useful for 
*               validation/syncing data from MuseumPlus
*/
function mplus_flip_struct_by_module(array $ramc)
    {
    $flipped_struct = array();
    foreach($ramc as $resource_ref => $amc)
        {
        if(!is_numeric($resource_ref)) { continue; }

        if(!isset($flipped_struct[$amc['module_name']]))
            {
            $flipped_struct[$amc['module_name']] = array(
                // always try the virtual ID (if one was configured), otherwise default to the technical ID
                'mplus_id_field' => ($amc['mplus_id_field'] !== '' ? $amc['mplus_id_field'] : MPLUS_FIELD_ID),
                'field_mappings' => array_column($amc['field_mappings'], 'field_name'),
                'resources' => array(),
            );
            }

        // The technical ID key (MPLUS_FIELD_ID - __id) is added after a successful validation to the associated module
        // configuration ($amc). From this point on, the code will use the __id value. We don't use the one held in the 
        // mplus_id_field because it might be holding a virtual ID value.
        $mpid = (isset($amc[MPLUS_FIELD_ID]) ? $amc[MPLUS_FIELD_ID] : $amc['field_values'][$amc['rs_uid_field']]);
        $flipped_struct[$amc['module_name']]['resources'][$resource_ref] = $mpid;
        }

    return $flipped_struct;
    }


/**
* Compute the MD5 hash for the association  between a resource and the combination of "module name - MpID (virtual or technical).
* Utility function which is used to save the data in the resource table, column "museumplus_data_md5" and used to determine
* if either of the data of interest (module name or MpID) have changed in order to revalidate the association.
* 
* @param  array  $resources_data  Resources data array - key is the resource ref and value is the module item ID (virtual or technical).
*                                 Example: [23 => '2423432', 24 => 'OB3223-N']
* @param  string $module_name     Module name 
* 
* @return array
*/
function mplus_compute_data_md5(array $resources_data, string $module_name)
    {
    $md5s = [];
    foreach($resources_data as $r_ref => $mpid)
        {
        $md5s[$r_ref] = md5("{$r_ref}_comb({$module_name}-{$mpid})");
        }
    return $md5s;
    }


/**
* Log events related to MuseumPlus integration. Any information that can be useful (even a user trying to save a resource)
* 
* @param string $msg Log message. Max size 255 characters.
* @param array  $ctx Contextual data relevant to the event. Try namespacing the data if needed using keys (e.g http_request - and then 
*                    the body could contain different aspects: header, body, url etc.).
*                    IMPORTANT: make sure to never log sensitive information (e.g MuseumPlus authentication credentials)
* @param string $lvl Logging level (Options could be: Trace, Debug, Info, Warn, Error, Fatal). Max size 10 characters.
* 
* @return void
*/
function mplus_log_event(string $msg, array $ctx = array(), string $lvl = 'info')
    {
    global $userref, $username, $debug_log;

    // Information that should always be logged
    $ctx['user'] = array($userref => $username);

    // JSON encode the context. If it fails, attempt to log it in the debug log.
    $json_encoded_ctx = json_encode($ctx);
    if(json_last_error() !== JSON_ERROR_NONE)
        {
        $json_last_error_msg = json_last_error_msg();

        debug("[plugin][museumplus] mplus_log_event: [{$lvl}] {$msg}. This triggered the following JSON error: {$json_last_error_msg}");
        debug("[plugin][museumplus] mplus_log_event: JSON error when \$ctx = " . print_r($ctx, true));

        // Log instead the JSON error message
        $json_encoded_ctx = "Please check debug log (if enabled). The context triggered the following JSON error: {$json_last_error_msg}";
        }

    if(strtolower($lvl) != "debug" || $debug_log)
        {
        $q = "INSERT INTO museumplus_log (`level`, message, `context`) VALUES (?,?,?)";
        $params = ["s",sql_truncate_text_val(mb_strtolower($lvl), 10),"s",sql_truncate_text_val($msg, 255),"s",$json_encoded_ctx];
        ps_query($q,$params);
        }
    }


/**
* Validate a list of filter names that can be used by {@see mplus_resource_get_association_data()}.
* 
* @param array $f List of filters. Key is the filter name. The value of a filter is any data type required by that particular
*                 filters' parameter (e.g for "byref" filter, the input is a list of refs => array).
* 
* @return array
*/
function mplus_validate_resource_association_filters(array $f)
    {
    $valid_filters = [];

    // Key - filter name, Value - data type of the parameter of this filter
    $allowed_filters = [
        'new_and_changed_associations' => 'NULL',
        'byref' => 'array',
    ];

    foreach($f as $name => $value)
        {
        if(is_string($name) && isset($allowed_filters[$name]) && $allowed_filters[$name] === gettype($value))
            {
            $valid_filters[$name] = $value;
            }
        }

    return $valid_filters;
    }

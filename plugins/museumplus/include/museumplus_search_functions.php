<?php
/**
* Build the search XML for MuseumPlus API request body
* {@see http://docs.zetcom.com/ws/module/search/search_1_4.xsd}
* 
* @param string $fp   Field path used to identify the "linked" modules. This is essentially either the technical ID field (ie. __id)
*                     or another virtual field (e.g ObjObjectNumberVrt)
* @param array  $vals Module record IDs to search for in MuseumPlus. Hash table where key is resource ID and value is the MpID
* @param array  $sfs  MuseumPlus modules' fields to return back
* 
* @return DOMDocument Returns XML document with the search criteria
*/
function mplus_xml_search_by_fieldpath(string $fp, array $vals, array $sfs)
    {
    mplus_log_event('Called mplus_xml_search_by_fieldpath()',
        array(
            'expert_search_fieldPath' => $fp,
            'resource_mpid' => $vals,
        ),
        'debug');

    $xml = new DOMDocument('1.0', 'UTF-8');
    $search = $xml->createElement('search');
    $search->setAttribute('limit', count($vals));
    $search->setAttribute('offset', 0);
    $search = $xml->appendChild($search);

    // Select specific fields from the module
    $select = $xml->createElement('select');
    $select = $search->appendChild($select);
    $select_fields = array_merge($sfs, array('__lastModified')); # always select the systems' field "__lastModified"
    foreach($select_fields as $field_name)
        {
        $field = $xml->createElement('field');
        $field->setAttribute('fieldPath', $field_name);
        $field = $select->appendChild($field);
        }

    // Search criteria
    $expert = $xml->createElement('expert');
    $expert = $search->appendChild($expert);
    if(count($vals) > 1)
        {
        $expert = $expert->appendChild($xml->createElement('or'));
        }
    foreach($vals as $value)
        {
        $equalsField = $xml->createElement('equalsField');
        $equalsField->setAttribute('fieldPath', $fp);
        $equalsField->setAttribute('operand', $value);
        $equalsField = $expert->appendChild($equalsField);
        }

    return $xml;
    }


/**
* Run an ad-hoc module search using the provided expert search expression
* {@see http://docs.zetcom.com/ws/#Perform_an_ad-hoc_search_for_modules_items}
* 
* @param string      $module_name Module name
* @param DOMDocument $search      Expert search criteria. {@see mplus_xml_search_by_fieldpath()}
* 
* @return array
*/
function mplus_search(string $module_name, DOMDocument $search)
    {
    mplus_log_event('Called mplus_search()',
        array(
            'module_name' => $module_name,
            'search' => $search->saveXML(),
        ),
        'debug');

    $conn_data = mplus_get_connection_data();
    $search_node = $search->getElementsByTagName("search")->item(0);

    if(empty($conn_data) || trim($module_name) === '' || is_null($search_node))
        {
        mplus_log_event('mplus_search(): Bad arguments.', array(), 'warn');
        return array();
        }

    $basic_auth = "{$conn_data['username']}:{$conn_data['password']}";

    if ($conn_data['application'] != '')
        {
        # Older M+ URL format.
        $url = "{$conn_data['host']}/{$conn_data['application']}/ria-ws/application/module/{$module_name}/search/";
        }
    else
        {
        $url = "{$conn_data['host']}/ria-ws/application/module/{$module_name}/search/";
        }

    $xml = new DOMDocument('1.0', 'UTF-8');
    $application = $xml->createElement('application');
    $application->setAttribute('xmlns', 'http://www.zetcom.com/ria/ws/module/search');
    $application->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $application->setAttribute('xsi:schemaLocation', 'http://www.zetcom.com/ria/ws/module/search http://www.zetcom.com/ria/ws/module/search/search_1_1.xsd');
    $application = $xml->appendChild($application);
    $modules = $xml->createElement('modules');
    $modules = $application->appendChild($modules);
    $module = $xml->createElement('module');
    $module->setAttribute('name', $module_name);
    $module = $modules->appendChild($module);
    $search = $xml->importNode($search_node, true);
    $search = $module->appendChild($search);

    $request_xml = $xml->saveXML();
    if($request_xml === false)
        {
        mplus_log_event('mplus_search(): Failed to save XML', array(), 'warn');
        return array();
        }

    $result = do_http_request($url, $basic_auth, "application/xml", "POST", $request_xml);

    if($result['status_code'] != 200)
        {
        mplus_log_event('mplus_search(): Request failed! Response status code NOT 200.', array('status_code' => $result['status_code']), 'warn');
        return array();
        }

    return $result;
    }


/**
* Get XML response body received from MuseumPlus search request
* {@see http://docs.zetcom.com/ws/module/module_1_4.xsd}
* 
* @param array $result Search results as returned by {@see mplus_search()}
* 
* @return DOMDocument
*/
function mplus_get_response_xml(array $result)
    {
    $xml = new DOMDocument('1.0', 'UTF-8');

    if($result['headers']['content-type'][0]??"" == 'application/xml')
        {
        $xml->loadXML($result['result']);
        }

    return $xml;
    }


/**
* Synchronise (search & import) MuseumPlus module fields to the associated ("linked") resources.
* 
* @param  array  $ramc  Valid resources with an associated module configuration. {@see mplus_validate_association()}
* 
* @return void|array Returns NULL if sync finished (or had nothing to process) -or- list of errors caught during the 
*                    validation/sync process (NOTE: validation errors get carried forward). 
*/
function mplus_sync(array $ramc)
    {
    mplus_log_event('Called mplus_sync()', [], 'debug');

    global $lang, $museumplus_api_batch_chunk_size;

    $errors = (isset($ramc['errors']) && is_array($ramc['errors']) ? $ramc['errors'] : []);
    $module_items_data = [];

    $modules = mplus_flip_struct_by_module($ramc);
    foreach($modules as $module_name => $mdata)
        {
        if(empty($mdata['field_mappings']))
            {
            mplus_log_event('mplus_sync(): No field mappings configured for this module!', ['module_name' => $module_name], 'error');
            $errors[] = str_replace('%name', $module_name, $lang['museumplus_error_module_no_field_maps']);
            continue;
            }

        foreach(array_chunk($mdata['resources'], $museumplus_api_batch_chunk_size, true) as $resources_chunk)
            {
            $search_xml = mplus_xml_search_by_fieldpath(MPLUS_FIELD_ID, $resources_chunk, $mdata['field_mappings']);
            $mplus_search = mplus_search($module_name, $search_xml);
            if(empty($mplus_search))
                {
                // Search failed for some reason. Move to the next chunk silently (do not exit process).
                continue;
                }

            $mplus_search_xml = mplus_get_response_xml($mplus_search);

            // No module items found (if since last successful validation, the module item has been deleted in MuseumPlus)
            $module_node = $mplus_search_xml->getElementsByTagName('module')->item(0);
            if($module_node->hasAttributes() && $module_node->attributes->getNamedItem('totalSize')->value == 0)
                {
                mplus_log_event('mplus_sync(): Unable to find searched module items', [], 'error');
                // We don't send the error to the end user as this should be an exceptional scenario not caused or fixable by them
                continue;
                }

            foreach($mplus_search_xml->getElementsByTagName('moduleItem') as $module_item)
                {
                $mpid = $module_item->getAttribute('id');
                if($mpid === '' || !$module_item->hasChildNodes())
                    {
                    continue;
                    }

                foreach($module_item->childNodes as $child_node)
                    {
                    if(!in_array($child_node->tagName, ['systemField', 'dataField', 'virtualField']))
                        {
                        continue;
                        }

                    $attr_name = $child_node->getAttribute('name');
                    if(!in_array($attr_name, array_merge($mdata['field_mappings'], ['__lastModified'])))
                        {
                        continue;
                        }

                    $value = $child_node->getElementsByTagName('value')->item(0);
                    $module_items_data[$module_name][$mpid][$attr_name] = (!is_null($value) ? $value->nodeValue : '');
                    }
                }
            }
        }

    // Update resources' metadata fields
    foreach($ramc as $r_ref => $amc)
        {
        if(
            !is_numeric($r_ref)
            // Search didn't return results for the MpID on this module
            || !isset($module_items_data[$amc['module_name']][$amc[MPLUS_FIELD_ID]])
        )
            {
            continue;
            }

        $item_data = $module_items_data[$amc['module_name']][$amc[MPLUS_FIELD_ID]];

        foreach($amc['field_mappings'] as $fm)
            {
            $rtf_ref = $fm['rs_field'];
            $mapped_mplus_field = $fm['field_name'];

            if(!isset($item_data[$mapped_mplus_field]) || $item_data[$mapped_mplus_field] === '')
                {
                continue;
                }

            update_field($r_ref, $rtf_ref, $item_data[$mapped_mplus_field]);
            mplus_log_event('mplus_sync(): Updated resource metadata field', ['resource' => $r_ref, 'resource_type_field' => $rtf_ref]);
            }
        }

    if(!empty($errors))
        {
        return $errors;
        }
    }

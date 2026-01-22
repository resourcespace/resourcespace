<?php
function HookTms_linkEditEditbeforesectionhead()
    {
    global $lang,$baseurl,$tms_link_object_id_field, $ref,$resource,$tms_confirm_upload,$tms_link_resource_types;

    if($ref > 0)
        {
        return;
        }

    $resource_type_allowed = array();

    foreach(tms_link_get_modules_mappings() as $module_uid => $module)
        {
        if(!in_array($resource['resource_type'], $module['applicable_resource_types']))
            {
            continue;
            }

        $resource_type_allowed[] = true;
        $field_label = str_replace(
            array("%module_name", "%tms_uid_field"),
            array($module['module_name'], $module['tms_uid_field']),
            $lang["tms_link_uid_field"]);
        $input_identifier = "field_{$module_uid}_{$module['rs_uid_field']}";
        ?>
        <div class="Question">
            <label for="<?php echo $input_identifier; ?>"><?php echo $field_label; ?></label>
            <input id="<?php echo $input_identifier; ?>" name="<?php echo $input_identifier; ?>" type="text" value="<?php echo escape(get_data_by_field($ref, $module['rs_uid_field'])); ?>">
            <div class="clearerleft"></div>
        </div>
        <?php
        }

    if(!empty($resource_type_allowed) && isset($tms_confirm_upload) && $tms_confirm_upload)
        {
        ?>
        <div class="Question FieldSaveError" id="tms_confirm_upload">
            <label for="tms_confirm_upload"><?php echo escape($lang["tms_link_confirm_upload_nodata"]); ?></label>
            <input type="checkbox" id="tms_confirm_upload" name="tms_confirm_upload" value="true">
            <div class="clearerleft"></div>
        </div>
        <?php
        }
    }
    
function HookTMS_linkEditEdithidefield($field)
    {
    global $tms_link_object_id_field,$ref,$resource,$tms_link_resource_types;

    $field_ref_ok = false;
    $resource_type_allowed = false;

    if(tms_link_is_rs_uid_field($field['ref']) && $ref < 0)
        {
        $field_ref_ok = true;
        }

    foreach(tms_link_get_modules_mappings() as $module)
        {
        if(in_array($resource['resource_type'], $module['applicable_resource_types']))
            {
            $resource_type_allowed = true;
            break;
            }
        }

    if($field_ref_ok && $resource_type_allowed)
        {
        return true;
        }

    return false;
    }


function HookTms_linkAllAdditionalvalcheck($fields, $fieldsitem)
    {
    global $ref,$val,$tms_link_object_id_field,$resource,$tms_link_resource_types,$lang;

    if(!tms_link_is_rs_uid_field($fieldsitem['ref']))
        {
        return false;
        }

    foreach(tms_link_get_modules_mappings() as $module_uid => $module)
        {
        if(!in_array($resource['resource_type'], $module['applicable_resource_types']))
            {
            continue;
            }

        $input_identifier = ($ref < 0) ? "field_{$module_uid}_{$module['rs_uid_field']}" : "field_{$module['rs_uid_field']}";
        $tms_form_post_id = getval($input_identifier, 0, true);
        if($tms_form_post_id == 0)
            {
            continue;
            }

        $tms_object_id = intval($tms_form_post_id);
        
        global $tmsdata;
        $tmsdata = tms_link_get_tms_data('', $tms_object_id, '', $module['module_name']);

        // Make sure we actually do save this data, even if we return an error
        $result_update_field = update_field($ref, $module['rs_uid_field'], $tms_object_id);
        if ($result_update_field !== true)
            {
            return $result_update_field; // return error message
            }
        
        if(!is_array($tmsdata) && $ref < 0)
            {
            // We can't get any data from TMS for this new resource. Need to show warning if user has not already accepted this
            if(getval("tms_confirm_upload","")=="")
                {
                global $tms_confirm_upload, $lang;
                $tms_confirm_upload=true;
                return $lang["tms_link_upload_nodata"] . $tms_form_post_id . " " . $lang["tms_link_confirm_upload_nodata"];
                }
            }
        else
            {
            global $tms_link_import;

            $tms_link_import=true;
            }
        }

    return false;
    }

/**
* TMS plugin implementing the 'aftersaveresourcedata' hook
* IMPORTANT: 'aftersaveresourcedata' hook is called from both save_resource_data() and save_resource_data_multi()!
* 
* @param int|array $R Generic type for resource ID(s). It will be a resource ref when hook is called from 
*                     save_resource_data() -OR- a list of resource IDs when called from save_resource_data_multi().
* @param array $fields List of fields that was submitted with the request (contains stale data)
* @param array $updated_resources Map of field changes for each resource (if applicable)
* 
* @return array|bool Returns bool to show whether the hook ran or not -or- list of errors.
* See hook 'aftersaveresourcedata' in resource_functions.php for more info
*/
function HookTms_linkEditAftersaveresourcedata(int|array $R, $nodes_to_add, $nodes_to_remove, $autosave_field, array $fields, array $updated_resources): array|bool
{
    if (!(is_numeric($R) || is_array($R))) {
        return false;
    }

    $refs = is_array($R) ? $R : [$R];
    $can_use_updated_resources = $updated_resources !== [];

    foreach ($refs as $resourceref) {
        $resdata = get_resource_data($resourceref);
        // Use the most up to date metadata field values ($fields is stale at this point)
        $resource_rtfs = !$can_use_updated_resources ? get_resource_field_data($resourceref) : [];

        foreach (tms_link_get_modules_mappings() as $module) {
            if (
                !in_array($resdata['resource_type'], $module['applicable_resource_types'])
                // Batch editing a DKL field follows a path where we don't get access to the usual data
                || (
                    !$can_use_updated_resources
                    && (
                        $resource_rs_uid_field = array_values(array_filter(
                            $resource_rtfs,
                            static fn($V) => $V['resource_type_field'] == $module['rs_uid_field']
                        ))
                    )
                    && (
                        $resource_rs_uid_field === []
                        || (
                            $resource_rs_uid_field[0]['type'] === FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
                            && isset($resource_rs_uid_field[0]['nodes_values'])
                            && $resource_rs_uid_field[0]['nodes_values'] === []
                            )
                        )
                )
                || (
                    $can_use_updated_resources
                    && !isset($updated_resources[$resourceref], $updated_resources[$resourceref][$module['rs_uid_field']])
                )
            ) {
                continue;
            }

            $module_rs_uid_field_data = array_values(
                array_filter($fields, static fn($V) => $V['ref'] == $module['rs_uid_field'])
            );
            if ($module_rs_uid_field_data === []) {
                debug("Metadata field (ref={$module['rs_uid_field']}) not found");
                continue;
            }

            if ($module_rs_uid_field_data[0]['type'] === FIELD_TYPE_TEXT_BOX_SINGLE_LINE) {
                $tms_object_id = $updated_resources[$resourceref][$module['rs_uid_field']][0];
            } else if (
                !$can_use_updated_resources
                && $module_rs_uid_field_data[0]['type'] === FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
            ) {
                $tms_object_id = $resource_rs_uid_field[0]['nodes_values'];
            } else if (
                $can_use_updated_resources
                && $module_rs_uid_field_data[0]['type'] === FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
            ) {
                $tms_object_id = $updated_resources[$resourceref][$module['rs_uid_field']];
            } else {
                debug("Misconfiguration - unsupported metadata field type for the 'rs_uid_field' in '{$module['module_name']}' module");
                continue;
            }

            debug("tms_link: updating resource id #{$resourceref}");

            $tmsdata = tms_link_get_tms_data($resourceref, $tms_object_id, '', $module['module_name']);
            if (!is_array($tmsdata)) {
                return $tmsdata;
            }

            if(!array_key_exists($module['module_name'], $tmsdata))
                {
                continue;
                }

            // Multiple IDs? For details, see HookTms_linkAllUpdate_field comment (same variable name)
            $tms_module_data = end($tmsdata[$module['module_name']]);

            foreach($module['tms_rs_mappings'] as $tms_rs_mapping)
                {
                if($tms_rs_mapping['rs_field'] > 0 && $module['rs_uid_field'] != $tms_rs_mapping['rs_field'] && isset($tms_module_data[$tms_rs_mapping['tms_column']]))
                    {
                    update_field($resourceref, $tms_rs_mapping['rs_field'], $tms_module_data[$tms_rs_mapping['tms_column']]);
                    }
                elseif($resourceref > 0 && getval("field_{$module['rs_uid_field']}", '') == '')
                    {
                    update_field($resourceref, $tms_rs_mapping['rs_field'], '');
                    }
                }
        }
    }

    return true;
}

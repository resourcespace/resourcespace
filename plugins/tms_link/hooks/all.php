<?php
include_once __DIR__ . "/../include/tms_link_functions.php";


function HookTms_linkAllInitialise()
    {
    $tms_link_config = get_plugin_config('tms_link');
    if (isset($tms_link_config["tms_link_log_directory"])) {
        // Legacy config  - remove from plugin settings
        save_removed_ui_config('tms_link_log_directory');
        unset($tms_link_config["tms_link_log_directory"]);
        set_plugin_config('tms_link', $tms_link_config);
    }
    check_removed_ui_config("tms_link_log_directory");

    if(is_null($tms_link_config) || get_sysvar(TMS_LINK_MODULES_MIGRATED) !== false)
        {
        return;
        }

    $new_module = array(
        'tms_uid_field' => ''
    );
    $config_migration_map = array(
        'tms_link_table_name' => 'module_name',
        'tms_link_object_id_field' => 'rs_uid_field',
        'tms_link_checksum_field' => 'checksum_field',
        'tms_link_resource_types' => 'applicable_resource_types',
        
    );

    foreach($tms_link_config as $name => $value)
        {
        if(!array_key_exists($name, $config_migration_map))
            {
            continue;
            }

        $new_module[$config_migration_map[$name]] = $value;
        }

    $tms_rs_mappings = array();
    foreach(unserialize(base64_decode($GLOBALS['tms_link_field_mappings_saved'])) as $tms_column => $rs_field)
        {
        $new_column = array(
            'tms_column' => $tms_column,
            'rs_field' => $rs_field,
        );

        $new_column['encoding'] = 'UTF-8';
        if(in_array($tms_column, $GLOBALS['tms_link_text_columns']))
            {
            $new_column['encoding'] = 'UTF-16';
            }

        $tms_rs_mappings[] = $new_column;
        }
    $new_module['tms_rs_mappings'] = $tms_rs_mappings;
    $tms_link_modules_mappings[uniqid()] = $new_module;

    $tms_link_config['tms_link_modules_saved_mappings'] = base64_encode(serialize($tms_link_modules_mappings));

    set_plugin_config('tms_link', $tms_link_config);

    set_sysvar(TMS_LINK_MODULES_MIGRATED, 1);
    }


function HookTms_linkAllUpdate_field($resource, $field, $value, $existing)
    {
    if($resource < 0 || !tms_link_is_rs_uid_field($field))
        {
        return false;
        }
    $resdata = get_resource_data($resource);
    foreach(tms_link_get_modules_mappings() as $module)
        {
        if(!in_array($resdata['resource_type'], $module['applicable_resource_types']))
            {
            continue;
            }

        $tms_object_id = intval($value);
        debug("tms_link: updating resource id #" . $resource);

        $module_name = $module['module_name'];
        $tmsdata = tms_link_get_tms_data($resource, $tms_object_id,'', $module_name);

        // if call to tms_link_get_tms_data() does not return an array, error has occurred
        if (!is_array($tmsdata))
            {
            return $tmsdata;
            }

        if(!array_key_exists($module_name, $tmsdata))
            {
            continue;
            }

        /* When a module is configured to support multiple IDs (RS UID field is a dynamic keyword list or CSV of IDs)
        the resource will only sync the last record to its metadata fields since there's no logic to flatten or
        transform it. If that's needed, the TMS database admins should provide a module (table/view) with that data
        already flatten. */
        $tms_module_data = end($tmsdata[$module_name]);

        foreach ($tms_module_data as $tmsdata_key => $tmsdata_value) {
            if (is_null($tmsdata_value)) {
                // Consider null as blank to allow removal of old TMS data from RS if TMS no longer returns a value for the field.
                $tms_module_data[$tmsdata_key] = '';
            }
        }

        foreach($module['tms_rs_mappings'] as $tms_rs_mapping)
            {
            if($tms_rs_mapping['rs_field'] > 0 && $module['rs_uid_field'] != $tms_rs_mapping['rs_field'] && isset($tms_module_data[$tms_rs_mapping['tms_column']]))
                {
                debug("tms_link: updating field '{$field}' with data from column '{$tms_rs_mapping['tms_column']}' for resource id #{$resource}");

                update_field($resource, $tms_rs_mapping['rs_field'], $tms_module_data[$tms_rs_mapping['tms_column']]);
                }
            }
        }

    tms_link_check_preview($resource);

    return true;
    }


function HookTms_linkAllAfterpreviewcreation(int $ref, int $alternative = -1, bool $generate_all = false): void
{
    tms_link_check_preview($ref, $alternative);
}
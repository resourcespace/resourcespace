<?php
function HookMuseumplusAllInitialise()
    {
    // Check for previously saved config that is no longer set in UI
    $museumplus_config = get_plugin_config('museumplus');
    if (isset($museumplus_config["museumplus_log_directory"])) {
        // Legacy config  - remove from plugin settings
        save_removed_ui_config('museumplus_log_directory');
        unset($museumplus_config["museumplus_log_directory"]);
        set_plugin_config('museumplus', $museumplus_config);
    }
    check_removed_ui_config("museumplus_log_directory");

    $mplus_config = get_plugin_config('museumplus');
    $mplus_config = (is_null($mplus_config) ? [] : $mplus_config);

    // Migrating old plugin configuration (when it was only syncing from the Object module).
    if(
        isset($mplus_config['museumplus_search_mpid_field'])
        && isset($mplus_config['museumplus_mpid_field'])
        && isset($mplus_config['museumplus_resource_types'])
        && isset($mplus_config['museumplus_rs_saved_mappings'])
        && isset($mplus_config['museumplus_cms_url_form_part'])
    )
        {
        $old_config = $mplus_config;
        // Remove sensitive information
        unset($old_config['museumplus_host'], $old_config['museumplus_application'], $old_config['museumplus_api_user'], $old_config['museumplus_api_pass']);
        mplus_log_event('Migrating old MuseumPlus plugin configuration', array('old_mplus_plugin_config' => $old_config));

        $field_mappings = array();
        $museumplus_rs_saved_mappings = plugin_decode_complex_configs($mplus_config['museumplus_rs_saved_mappings']);
        foreach($museumplus_rs_saved_mappings as $field_name => $rs_field)
            {
            $field_mappings[] = array(
                'field_name' => $field_name,
                'rs_field' => $rs_field);
            }

        $module_configs = array(
            1 => array(
                'module_name' => 'Object',
                'mplus_id_field' => $mplus_config['museumplus_search_mpid_field'],
                'rs_uid_field' => $mplus_config['museumplus_mpid_field'],
                'applicable_resource_types' => $mplus_config['museumplus_resource_types'],
                'field_mappings' => $field_mappings,
            )
        );

        $mplus_config['museumplus_modules_saved_config'] = plugin_encode_complex_configs($module_configs);
        unset($mplus_config['museumplus_search_mpid_field']);
        unset($mplus_config['museumplus_mpid_field']);
        unset($mplus_config['museumplus_resource_types']);
        unset($mplus_config['museumplus_rs_saved_mappings']);
        unset($mplus_config['museumplus_cms_url_form_part']); # not migrated but no longer needed

        set_plugin_config('museumplus', $mplus_config);
        }


    $core_fields = [];
    $GLOBALS['museumplus_all_field_mappings_refs'] = [];
    foreach($mplus_config as $cfg_name => $cfg_value)
        {
        // Base plugin config options
        if(in_array($cfg_name, ['museumplus_module_name_field', 'museumplus_secondary_links_field']))
            {
            $core_fields[] = $cfg_value;
            }

        // Module setup config options
        if($cfg_name === 'museumplus_modules_saved_config')
            {
            foreach(plugin_decode_complex_configs($cfg_value) as $module_cfg)
                {
                $core_fields[] = $module_cfg['rs_uid_field'];

                $field_mappings_refs = array_column($module_cfg['field_mappings'], 'rs_field');
                $core_fields = array_merge($core_fields, $field_mappings_refs);
                $GLOBALS['museumplus_all_field_mappings_refs'] = array_merge($GLOBALS['museumplus_all_field_mappings_refs'], $field_mappings_refs);
                }
            }
        }
    $core_fields = array_values(array_unique($core_fields));
    $GLOBALS['museumplus_all_field_mappings_refs'] = array_values(array_unique($GLOBALS['museumplus_all_field_mappings_refs']));

    // Mark as core any plugin config option that relies on a metadata field to prevent them from being deleted if the plugin is in use.
    config_register_core_field_refs('museumplus', $core_fields);
    }


function HookMuseumplusAllAfter_setup_user()
    {
    // Modify the users' permissions and deny write access to the modules' mapped fields. Users should never have to
    // edit those manually. In addition, this avoids edit conflicts since the process will update the mapped fields of a module
    if(
        isset($GLOBALS['usergroup'], $GLOBALS['museumplus_ug_bypass_F_perm'], $GLOBALS['museumplus_all_field_mappings_refs'])
        && !in_array($GLOBALS['usergroup'], $GLOBALS['museumplus_ug_bypass_F_perm']))
        {
        $mapped_F_perms = array_map(build_permission('F'), $GLOBALS['museumplus_all_field_mappings_refs']);
        $GLOBALS['userpermissions'] = array_values(array_unique(array_merge($GLOBALS['userpermissions'], $mapped_F_perms)));
        }
    }


/* 
IMPORTANT: DO NOT USE the "update_field" hook! You can potentially end up in a processing loop.
The function is used in multiple places and won't be able to let the user know there were errors caused by validation/sync.
In addition, the "museumplus_script.php" can run every minute and pick up any remaining resources left unprocessed.
function HookMuseumplusAllUpdate_field($resource, $field, $value, $existing) {}
*/

/**
* MuseumPlus plugin attaching to the 'aftersaveresourcedata' hook
* IMPORTANT: 'aftersaveresourcedata' hook is called from both save_resource_data() and save_resource_data_multi()!
* 
* @param int|array $R Generic type for resource ID(s). It will be a resource ref when hook is called from 
*                     save_resource_data() -OR- a list of resource IDs when called from save_resource_data_multi().
* 
* @return boolean|array Returns FALSE to show hook didn't run or list of errors. See hook 'aftersaveresourcedata' in resource_functions.php for more info
*/
function HookMuseumplusAllAftersaveresourcedata($R)
    {
    mplus_log_event('Called HookMuseumplusAllAftersaveresourcedata()', ['resources' => $R], 'debug');

    if(!(is_numeric($R) || is_array($R)))
        {
        return false;
        }
    $refs = (is_array($R) ? $R : [$R]);

    $resources = mplus_resource_get_association_data(['byref' => $refs]);
    $ramcs = mplus_get_associated_module_conf($resources, true);
    // Filter resources - discard of the ones where the "module name - MpID" combination hasn't changed since resource association was last validated
    foreach(mplus_flip_struct_by_module($ramcs) as $module_name => $mdata)
        {
        $computed_md5s = mplus_compute_data_md5($mdata['resources'], $module_name);
        $resources_md5s = array_column(mplus_resource_get_data(array_keys($mdata['resources'])), 'museumplus_data_md5', 'ref');
        foreach(array_keys($mdata['resources']) as $r_ref)
            {
            if(isset($computed_md5s[$r_ref], $resources_md5s[$r_ref]) && $computed_md5s[$r_ref] === $resources_md5s[$r_ref])
                {
                unset($ramcs[$r_ref]);
                }
            }
        }

    if(empty($ramcs))
        {
        return false;
        }

    $refs_list = array_keys($ramcs);
    mplus_log_event('Running MuseumPlus process (i.e. validating "module name - MpID" combination and syncing data...', ['resources' => $refs_list]);
    mplus_resource_clear_metadata($refs_list);
    $errors = mplus_sync(mplus_validate_association($ramcs, false, false));

    if(is_array($errors) && !empty($errors))
        {
        return $errors;
        }

    return false;
    }

function HookMuseumplusAllAddspecialsearch($search,$select,$sql_join,$sql_filter)
    {
    if(substr($search, 0, 20) !== '!mplus_invalid_assoc') 
        {
        return false;
        }

    // @see mplus_validate_association() - this is where we decide if something is invalid
    $mplussql = new PreparedStatementQuery();
    $mplussql->sql = "SELECT DISTINCT r.hit_count score, museumplus_data_md5, museumplus_technical_id, $select->sql FROM resource r " . $sql_join->sql . " WHERE r.ref > 0 AND r.museumplus_data_md5 IS NOT NULL AND r.museumplus_technical_id IS NULL AND " . $sql_filter->sql . " GROUP BY r.ref";
    $mplussql->parameters = array_merge($select->parameters, $sql_join->parameters, $sql_filter->parameters);
    return $mplussql;
    }

function HookMuseumplusAllHandleuserref()
    {
    if(
        $GLOBALS['museumplus_top_nav']
        // Don't show to anonymous users, normally they won't be able to remediate the problem
        && !(isset($GLOBALS['anonymous_login'], $GLOBALS['username']) && $GLOBALS['username'] == $GLOBALS['anonymous_login'])
    )
        {
        global $lang, $custom_top_nav, $baseurl;
        $mplus_top_nav = [
            'title' => '<i aria-hidden="true" class="icon-link-2-off"></i>&nbsp;' . $lang['museumplus_top_menu_title'],
            'link' => "{$baseurl}/pages/search.php?search=%21mplus_invalid_assoc",
        ];
        $custom_top_nav[] = $mplus_top_nav;
        }
    }

function HookMuseumplusAllAfternewresource($to)
    {
    HookMuseumplusAllAftersaveresourcedata($to);
    }

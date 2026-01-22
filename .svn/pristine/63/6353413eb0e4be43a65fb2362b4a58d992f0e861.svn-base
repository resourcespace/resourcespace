<?php

if (!function_exists("rse_workflow_get_actions")) {
    /**
     * Fetch a list of actions from the rse_workflow plugin
     * Action text, name, and button text support i18n translation strings
     *
     * @param  string|int   $status Filter based on statusfrom, onle a single workflow state reference can be passed
     * @param  string|int   $ref    Reference ID of action to return a single specific action
     *
     * @return array            SQL results from workflow_actions table.
     *                          name, text, and buttontext values are translated using i18n translation
     */
    function rse_workflow_get_actions($status="",$ref="") : array
    {
        # Check if we are searching for actions specific to a status
        $condition = new PreparedStatementQuery();
        if ($status != "" && is_int($status)) {
            $condition = new PreparedStatementQuery('WHERE wa.statusfrom = ? ', ['i', $status]);
        }
        if ($ref != "") {
            $condition = new PreparedStatementQuery(
                sprintf(
                    '%s%s wa.ref = ? ',
                    $condition->sql,
                    $condition->sql === '' ? 'WHERE' : 'AND'
                ),
                array_merge($condition->parameters, ['i', $ref])
            );
        }

        $results = ps_query(
            "SELECT
                wa.ref,
                wa.text,
                wa.name,
                wa.buttontext,
                wa.statusfrom,
                wa.statusto,
                a.notify_group,
                a.name AS statusto_name,
                a.more_notes_flag,
                a.notify_user_flag,
                a.email_from,
                a.bcc_admin
            FROM workflow_actions AS wa
            LEFT OUTER JOIN archive_states AS a ON wa.statusto = a.code {$condition->sql}
            GROUP BY wa.ref
            ORDER BY wa.ref, wa.statusfrom, wa.statusto ASC",
            $condition->parameters
        );

        foreach ($results as &$row) {
            $row["text"]        = i18n_get_translated($row["text"]);
            $row["name"]        = i18n_get_translated($row["name"]);
            $row["buttontext"]  = i18n_get_translated($row["buttontext"],"workflow-actions");
        }

        return $results;
    }
}

if (!function_exists("rse_workflow_save_action")){
    function rse_workflow_save_action($ref="")
            {
            if($ref==""){$ref=getval("ref","");}
            $fromstate=getval("actionfrom","");
            $tostate=getval("actionto","");
            $name=getval("actionname","");
            $text=getval("actiontext","");

            # Check if we are searching for actions specific to a status
            ps_query("UPDATE workflow_actions SET name = ?, text = ?, buttontext = '' statusfrom = ?, statusto = ? WHERE ref = ?",array("s",$name,"s",$text,"i",$fromstate,"i",$tostate,"i",$ref));
            return true;
            }
    }

if (!function_exists("rse_workflow_delete_action")){
    function rse_workflow_delete_action($action)
        {
        ps_query("DELETE FROM workflow_actions WHERE ref = ?",array("i",$action));
        return true;
        }
    }

if (!function_exists("rse_workflow_get_archive_states")){
    function rse_workflow_get_archive_states()
            {
            $rawstates = ps_query('SELECT ' . columns_in('archive_states') . ' FROM archive_states ORDER BY code ASC;', array(), "workflow");

            global $additional_archive_states, $lang;
            $states=array();

            foreach($rawstates as $rawstate)
                {
                // Reformat into new array
                $states[$rawstate['code']]['name']=$rawstate['name'];
                $states[$rawstate['code']]['notify_group']=$rawstate['notify_group'];
                $states[$rawstate['code']]['more_notes_flag']=$rawstate['more_notes_flag'];
                $states[$rawstate['code']]['notify_user_flag']=$rawstate['notify_user_flag'];
                $states[$rawstate['code']]['rse_workflow_email_from']=$rawstate['email_from'];
                $states[$rawstate['code']]['rse_workflow_bcc_admin']=$rawstate['bcc_admin'];
                $states[$rawstate['code']]['simple_search_flag'] = $rawstate['simple_search_flag'];
                $states[$rawstate['code']]['icon'] = $rawstate['icon'] != "" ? $rawstate['icon'] : (WORKFLOW_DEFAULT_ICONS[$rawstate['code']] ?? WORKFLOW_DEFAULT_ICON);
                $states[$rawstate['code']]['skip_required_fields']=$rawstate['skip_required_fields'];
                // Identify states that are set in config.php and cannot be deleted from plugin
                if(in_array($rawstate['code'],$additional_archive_states))
                    {
                    $states[$rawstate['code']]['fixed']=true;
                    }
                else
                    {
                    $states[$rawstate['code']]['fixed']=false;
                    }
                }

            //Add $additional_archive_states from config.php to table so can be managed by plugin if deleted from config
            foreach($additional_archive_states as $additional_archive_state)
                {
                if (!isset($states[$additional_archive_state]))
                    {
                    // Set name of archive state (will just be the ref if not set in lang file)
                    $statename= $additional_archive_state;
                    if(isset($lang['status' . $additional_archive_state]))
                        {
                        $statename=$lang['status' . $additional_archive_state];
                        }
                    rse_workflow_create_state([
                        'code' => $additional_archive_state,
                        'name' => $statename,
                    ]);
                    clear_query_cache("workflow");
                    $states[$additional_archive_state]['name']=$lang['status' . $additional_archive_state];
                    $states[$additional_archive_state]['fixed']=true;
                    }
                }

            // Add default system states
            for($workflow_state = -2; $workflow_state <= 3; $workflow_state++)
                {
                $workflow_state_name = $lang["status{$workflow_state}"];

                if (!isset($states[$workflow_state]))
                    {
                    $simple_search_flag = ($workflow_state == 0 ? 1 : 0);

                    $icon = WORKFLOW_DEFAULT_ICONS[$workflow_state] ?? 'cog';
                    rse_workflow_create_state([
                        'code' => $workflow_state,
                        'name' => $workflow_state_name,
                        'simple_search_flag' => $simple_search_flag,
                        'icon' => $icon,
                    ]);
                    clear_query_cache("workflow");
                    }

                $states[$workflow_state]['name'] = $workflow_state_name;
                $states[$workflow_state]['fixed'] = true;
                }

            return $states;
            }
    }

if (!function_exists("rse_workflow_delete_state")){
    function rse_workflow_delete_state($state,$newstate)
        {
        ps_query("UPDATE resource SET archive = ? WHERE archive = ?",array("i",$newstate,"i",$state));
        ps_query("DELETE FROM archive_states WHERE code = ?",array("s",$state));
        clear_query_cache("workflow");
        return true;
        }
    }

/**
* Validate list of actions for a resource or a batch of resources. For a batch of
* resources, an action is valid only if using the 'wf' permission is set for that action.
*
* @param array $actions         List of workflow actions (@see rse_workflow_get_actions())
* @param bool  $use_perms_only  Validate actions using edit access (e perm) on the destination state -OR- 'wf' permissions
*
* @return array
*/
function rse_workflow_get_valid_actions(array $actions, $use_perms_only)
    {
    /** $resource, $edit_access are used on the view page to determine valid actions for a resource where we run a
    * proper action validation (@see rse_workflow_validate_action())
    */
    global $resource, $edit_access;

    if($use_perms_only)
        {
        return array_filter($actions, function($action)
            {
            return checkperm("e{$action['statusto']}") || checkperm("wf{$action['ref']}");
            });
        }

    return array_filter($actions, function($action) use ($resource, $edit_access)
        {
        $resource["edit_access"] = $edit_access;
        return rse_workflow_validate_action($action, $resource);
        });
    }

/**
* Validate a workflow action for a particular resource
*
* @param array $action   Workflow action structure (@see rse_workflow_get_actions())
* @param array $resource Resource structure (@see get_resource_data() or do_search())
*
* @return bool
*/
function rse_workflow_validate_action(array $action, array $resource)
    {
    if(empty($action) || empty($resource))
        {
        return false;
        }

    $resource_in_valid_state = in_array($resource['archive'], explode(',', $action['statusfrom']));

    // resource[edit_access] can be added by outside context if this information is already available to increase performance
    // if action is validated for a list of resources (3k+) that we had to iterate over
    if(!isset($resource["edit_access"]))
        {
        $edit_access = (get_edit_access($resource["ref"], $resource["archive"], $resource));
        }
    else
        {
        $edit_access = (is_bool($resource["edit_access"]) ? $resource["edit_access"] : false);
        }

    $check_edit_access = ($edit_access && checkperm("e{$action['statusto']}"));

    // Provide workflow action option if user has access to it without having edit access to resource
    // Use case: a particular user group doesn't have access to the archive state but still needs to be
    // able to move the resource to a different state.
    $checkperm_wf = checkperm("wf{$action['ref']}");

    return $resource_in_valid_state && ($check_edit_access || $checkperm_wf);
    }


/**
* Compile workflow actions for the unified dropdown actions. This will validate actions using only 'wf' permissions (@see rse_workflow_get_valid_actions)
*
* @param array $url_params Inject any url params if needed. Useful to pass along search params.
*
* @return array
*/
function rse_workflow_compile_actions(array $url_params): array
    {
    // Validate actions without going through all resources to not impact performance on huge sets
    $valid_actions = rse_workflow_get_valid_actions(rse_workflow_get_actions(), true);
    if(empty($valid_actions))
        {
        return array();
        }

    global $baseurl;

    $wf_actions = array();
    foreach($valid_actions as $action)
        {
        $option = array(
            "value" => "rse_workflow_move_to_workflow~" . $action["ref"],
            "label" => i18n_get_translated($action["buttontext"],"workflow-actions"),
            "data_attr" => array(
                "url" => generateURL(
                    "{$baseurl}/plugins/rse_workflow/pages/batch_action.php",
                    $url_params,
                    array(
                        "action" => $action["ref"],
                    )),
            ),
            "category" => ACTIONGROUP_EDIT
        );

        $wf_actions[] = $option;
        }

    return $wf_actions;
    }


/**
 * Create new workflow state
 *
 * @param array $data New workflow state data. Requires at least a 'name' property!
 *
 * @return boolean|array Returns false if it fails or the new state data.
 */
function rse_workflow_create_state(array $data)
    {
    $defaults = [
        'notify_group'       => 0,
        'more_notes_flag'    => 0,
        'notify_user_flag'   => 0,
        'email_from'         => '',
        'bcc_admin'          => 0,
        'simple_search_flag' => 0,
        'icon'               => 'cog',
    ];
    $new_state_data = array_map('trim', $data + $defaults);

    if(!(isset($new_state_data['name']) && $new_state_data['name'] !== ''))
        {
        return false;
        }

    if(!isset($new_state_data['code']))
        {
        // Get the current maximum code reference
        $code = ps_value('SELECT MAX(code) AS `value` FROM archive_states', [], 0);
        $new_state_data['code'] = ++$code;
        }

    ps_query(
        "INSERT INTO archive_states (code, name, notify_group, more_notes_flag, notify_user_flag, email_from, bcc_admin, simple_search_flag, icon)
              VALUES (" . ps_param_insert(9) . ")",
        array(
        "s",$new_state_data['code'],
        "s",$new_state_data['name'],
        "i",$new_state_data['notify_group'],
        "i",$new_state_data['more_notes_flag'],
        "i",$new_state_data['notify_user_flag'],
        "s",$new_state_data['email_from'],
        "i",$new_state_data['bcc_admin'],
        "i",$new_state_data['simple_search_flag'],
        "s",$new_state_data['icon']
        ));
    $new_state_data['ref'] = sql_insert_id();

    return $new_state_data;
    }

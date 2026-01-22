<?php
function HookRse_workflowAllInitialise()
     {
     include_once __DIR__."/../include/rse_workflow_functions.php";
     include_once __DIR__."/../../../include/language_functions.php";
     # Deny access to specific pages if RSE_KEY is not enabled and a valid key is not found.
     global $lang, $additional_archive_states, $fixed_archive_states, $wfstates, $searchstates, $workflowicons;
    
    # Update $archive_states and associated $lang variables with entries from database
    $searchstates = array();
    $wfstates=rse_workflow_get_archive_states();
    
    foreach($wfstates as $wfstateref=>$wfstate)
        {
        if (!$wfstate['fixed'])
            {
            $additional_archive_states[]=$wfstateref;
            }
        else
            {
            // Save for later so we know which are editable
            $fixed_archive_states[] = $wfstateref;
            }
        if((isset($wfstate['simple_search_flag']) && $wfstate['simple_search_flag'] != 0) || $wfstateref == 0) // Always include active state
            {
            $searchstates[] = $wfstateref;
            }
        $lang["status" . $wfstateref] =  i18n_get_translated($wfstate["name"]);
        if(isset($wfstate['icon']) && trim($wfstate['icon']) != "")
            {
            $workflowicons[$wfstateref] = trim($wfstate['icon']);
            }
        }
    natsort($additional_archive_states);         
    }
    
function HookRse_workflowAllAfter_update_archive_status($resource, $archive, $existingstates)
    {
    global  $baseurl, $lang, $userref, $wfstates, $applicationname, $use_phpmailer;

    $workflowaction = getval('workflowaction','');

    // Set message text and URL to link to resources
    // The field 'more_workflow_action' is a hidden field which carries input text on the action specific form
    // A textarea named 'more_for_workflow_action' is effectively bound to and copies any keyboard input to 'more_workflow_action' 
    $message = $lang["rse_workflow_state_notify_message"] . $lang["status" . $archive];

    if(getval('more_workflow_action_' . $workflowaction,'') != '')
        {
        $message .= "\n\n" . $lang["rse_workflow_more_notes_title"];
        $message .= "\n\n" . getval('more_workflow_action_' . $workflowaction, '');
        }

    if(count($resource) > 200)
        {
        // Too many resources to link to directly
        $linkurl = $baseurl . "/pages/search.php?search=archive" . $archive;
        }
     else
        {
        $linkurl = $baseurl . "/pages/search.php?search=!list" . implode(":",$resource);
        }

    /***** NOTIFY GROUP SUPPORT IS NOW HANDLED BY ACTIONS *****/    

    /*****NOTIFY CONTRIBUTOR*****/
    if(isset($wfstates[$archive]['notify_user_flag']) && $wfstates[$archive]['notify_user_flag'] == 1)
        {
        $cntrb_arr = array();
        foreach($resource as $resourceref)
            { 
            $resdata = get_resource_data($resourceref);
            if(isset($resdata['created_by']) && is_numeric($resdata['created_by']))
                {
                $contuser = get_user($resdata['created_by']);
                if(!$contuser)
                    {
                    // No contributor listed
                    debug("No valid contributor listed for resource " . $resourceref);
                    continue;
                    }

                if(!isset($cntrb_arr[$contuser["ref"]]))
                    {
                    // This contributor needs to be added to the array of users to notify
                    $cntrb_arr[$contuser["ref"]] = array();
                    $cntrb_arr[$contuser["ref"]]["resources"] = array();
                    $cntrb_arr[$contuser["ref"]]["email"] = $contuser["email"];
                    $cntrb_arr[$contuser["ref"]]["username"] = $contuser["username"];
                    }
                $cntrb_arr[$contuser["ref"]]["resources"][] = $resourceref;
                }
            }
        // Construct messages for each user    
        foreach($cntrb_arr as $cntrb_user => $cntrb_detail)
            {
            debug("processing notification for contributing user " . $cntrb_user);
            $message = new ResourceSpaceUserNotification;
            $message->set_subject($applicationname . ": ");
            $message->append_subject("lang_status" . $archive);
            $message->set_text("lang_userresources_status_change");
            $message->append_text("lang_status" . $archive);
            if(getval('more_workflow_action_' . $workflowaction,'') != '')
                {
                $message->append_text("<br/><br/>");
                $message->append_text("lang_rse_workflow_more_notes_title");
                $message->append_text("<br/>" . getval('more_workflow_action_' . $workflowaction, ''));
                }
            if(count($cntrb_detail["resources"]) > 200)
                {
                // Too many resources to link to directly
                $linkurl = $baseurl . "/pages/search.php?search=archive" . $archive;
                }
             else
                {
                $linkurl = $baseurl . "/pages/search.php?search=!list" . implode(":",$cntrb_detail["resources"]);
                }
            $message->url = $linkurl;
            send_user_notification([$cntrb_user],$message);

            if($wfstates[$archive]["rse_workflow_bcc_admin"]==1)
                {
                debug("processing bcc notifications");
                $bccmessage = clone $message;
                $bccmessage->set_text("lang_user");                
                $bccmessage->append_text(": " . $cntrb_detail["username"] . " (#" . $cntrb_user . ")<br/>");
                $bccmessage->append_text_multi($message->get_text(true));
                $bccadmin_users = get_notification_users("SYSTEM_ADMIN");
                send_user_notification($bccadmin_users,$bccmessage);
                }
            }
        }
    /*****END OF NOTIFY CONTRIBUTOR*****/    
    }


function HookRse_workflowAllRender_actions_add_collection_option($top_actions, array $options, $collection_data, $urlparams)
    {
    global $baseurl_short, $lang, $pagename, $count_result;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }
        
    // On special search !collection the actions will be added from HookRse_workflowSearchRender_search_actions_add_option
    if($pagename != "collections" || $count_result == 0)
        {
        return false;
        }

    $wf_actions_options = rse_workflow_compile_actions($urlparams);

    return array_merge($options, $wf_actions_options);
    }

function HookRse_workflowAllRender_search_actions_add_option(array $options, array $urlparams)
    {
    global $internal_share_access;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if(isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"]))
        {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
        }

    $k = trim((isset($urlparams["k"]) ? $urlparams["k"] : ""));

    if($k != "" && $internal_share_access === false)
        {
        return false;
        }

    $wf_actions_options = rse_workflow_compile_actions($urlparams);

    // Append to the current allow list of render_actions_filter (for the selection collection)
    $current_render_actions_filter = $GLOBALS['render_actions_filter'] ?? fn($action) => true;
    $GLOBALS['render_actions_filter'] = function($action) use ($current_render_actions_filter, $wf_actions_options)
        {
        return $current_render_actions_filter($action)
            || in_array($action['value'], array_column($wf_actions_options, 'value'));
        };

    return array_merge($options, $wf_actions_options);
    }

function HookRse_workflowAllRender_actions_add_option_js_case($action_selection_id)
    {
    ?>
    case 'rse_workflow_move_to_workflow':
        var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
        ModalLoad(option_url, true, true);
        break;
    <?php
    }


function HookRse_workflowAllAfter_setup_user()
    {
    // Replaces notify group messaging - now replaced by actions
    global $userref, $usergroup;
    
    get_config_option(['user' => $userref, 'usergroup' => $usergroup],'user_pref_resource_notifications', $addwfactions);
    if (!$addwfactions)
        {
        // No notifications were sent so actions shouldn't appear either
        return false;
        }

    $extra_notify_states = [];
    $wfstates=rse_workflow_get_archive_states();
    foreach($wfstates as $wfstateref=>$wfstate)
        {
        if(isset($wfstate['notify_group']) &&  (int)$wfstate['notify_group'] == $usergroup && !checkperm("z" . $wfstateref))
            {
            $extra_notify_states[] = $wfstateref;
            }
        }
    if(count($extra_notify_states) > 0)
        {
        $GLOBALS['actions_notify_states'] .= "," . implode(",",$extra_notify_states);
        }
    }

/**
 * Check if supplied archive state code (e.g. 0 = Active) allows required field checking to be skipped when a resource is
 * moved to this archive state.
 *
 * @param  int  $archive_state   Integer of destination archive state i.e. the state the resource will be moved to.

 */
function HookRse_workflowAllArchive_skip_required_fields($archive_state)
{
    return ps_value('SELECT skip_required_fields AS `value` FROM archive_states WHERE code = ?;', array('i', $archive_state), 0, 'workflow') == 1;
}
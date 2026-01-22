<?php

function HookRse_workflowViewPageevaluation()
    {
    include_once __DIR__ . "/../include/rse_workflow_functions.php";
    global $lang;
    global $ref;
    global $resource;
    global $baseurl;
    global $search;
    global $offset;
    global $order_by;
    global $archive;
    global $sort;
    global $k;
    global $userref;
    # Retrieve list of existing defined actions 
    $workflowactions = rse_workflow_get_actions();
      
    foreach ($workflowactions as $workflowaction)
        {
        if(getval("rse_workflow_action_" . $workflowaction["ref"],"")!="" && enforcePostRequest(false))
            {
            // Check if resource status has already been changed between form being loaded and submitted
            $resource_status_check_name = "resource_status_check_" . $workflowaction["ref"];
            $resource_status_check = getval($resource_status_check_name,"");
            if($resource_status_check != "" && $resource_status_check != $resource["archive"])
                {
                $errors["status"] = $lang["status"] . ': ' . $lang["save-conflict-error"];
                echo "<div class=\"PageInformal\">" . $lang["error"] . ": " . $lang["status"] . " - " . $lang["save-conflict-error"] . "</div>";
                }
            else
                {
                $validstates = explode(',', $workflowaction['statusfrom']);
                $edit_access = get_edit_access($ref,$resource['archive'], $resource);
    
                if('' != $k || ($resource["lock_user"] > 0 && $resource["lock_user"] != $userref))
                    {
                    $edit_access = 0;
                    }
                    
                if(
                    in_array($resource['archive'], $validstates)
                    && (
                            (
                                $edit_access
                                && checkperm("e{$workflowaction['statusto']}")
                            )
                            || checkperm("wf{$workflowaction['ref']}")
                       )
                    )
                    {
                    // Check whether More notes are present
                    $more_notes_text = getval("more_workflow_action_" . $workflowaction["ref"],"");

                    // Prevent workflow state change if required metadata fields are empty.
                    $result = update_archive_required_fields_check($ref, $workflowaction["statusto"]);
                    if (is_array($result) && count($result) > 0) {
                        echo "<div class=\"PageInformal\">" . escape(str_replace(array('%%ARCHIVE%%', '%%FIELDS%%'), array($lang["status" . $workflowaction["statusto"]], implode(', ', array_column($result, 'title'))), $lang['rse_workflow_state_change_failed_required_fields'])) . "</div>";
                        return;
                    }

                    update_archive_status($ref, $workflowaction["statusto"], $resource["archive"], 0, $more_notes_text);

                    hook("rse_wf_archivechange","",array($ref,$resource["archive"],$workflowaction["statusto"]));
                                                
                    if (checkperm("z" . $workflowaction["statusto"]))
                        {
                        ?>
                        <script type="text/javascript">
                        styledalert('<?php echo escape($lang["success"]); ?>','<?php echo escape($lang["rse_workflow_saved"]) . "&nbsp;" . escape($lang["status" . $workflowaction["statusto"]]);?>');
                        if(jQuery("#modal").is(":visible"))
                            {
                            ModalClose();
                            }
                        else
                            {
                            window.setTimeout(function(){CentralSpaceLoad(baseurl_short);},1000);
                            }
                        </script>
                        <?php
                        exit();
                        }
                    else
                        { 
                        echo "<div class=\"PageInformal\">" . $lang["rse_workflow_saved"] . " " . $lang["status" . $workflowaction["statusto"]] . "</div>";
                        $resource["archive"]=$workflowaction["statusto"];
                        }
                    } 
                }
            }
        }
    }

function HookRse_workflowViewAdditionaldownloadtabs()
    {
    include_once __DIR__ . "/../include/rse_workflow_functions.php";

    global $lang, $ref, $resource, $baseurl_short, $search, $offset, $order_by, $archive, $sort, $edit_access, $curpos,
           $userref, $k, $internal_share_access,$modal;

    if(!empty($resource["lock_user"]) && $resource["lock_user"] != 0 && $resource["lock_user"] != $userref)
        {
        return false;
        }

    if($k != "" && $internal_share_access === false)
        {
        return false;
        }

    $validactions = rse_workflow_get_valid_actions(rse_workflow_get_actions(), false);

    if(count($validactions)>0)
        {?>
        <div class="RecordDownloadSpace" id="ResourceWorkflowActions" style="display:none;">
        <p><?php echo escape($lang['rse_workflow_user_info']); ?></p>
        <script type="text/javascript">
        function open_notes(action_ref) {
            var workflow_action = jQuery('#rse_workflow_action_' + action_ref);
            var more_link = jQuery('#more_link_' + action_ref);

            // Populate textarea with any text there may already be present
            var more_text_hidden = jQuery('#more_workflow_action_' + action_ref).val();

            more_link.after('<textarea id="more_for_workflow_action_' + action_ref 
                + '" name="more_for_workflow_action_' + action_ref 
                + '" style="width: 100%; resize: none;" rows="6">' + more_text_hidden + '</textarea>');
            more_link.after('<p id="notes_for_workflow_action_' + action_ref + '"><?php echo escape($lang["rse_workflow_more_notes_title"]); ?></p>');

            more_link.text('<?php echo escape($lang["rse_workflow_link_close"]); ?>');
            more_link.attr('onClick', 'close_notes(' + action_ref + ');');

            // Bind the input textarea 'more_for_workflow_action' value to the hidden 'more_workflow_action' field
            jQuery('#more_for_workflow_action_' + action_ref).keyup(function (event) {
                var notes = this.value;
                jQuery('#more_workflow_action_' + action_ref).val(notes);
            });
        }

        function close_notes(action_ref) {

            var more_link = jQuery('#more_link_' + action_ref);
            var notes_title = jQuery('#notes_for_workflow_action_' + action_ref);
            var notes_textarea = jQuery('#more_for_workflow_action_' + action_ref);

            // Remove Notes title and textarea from DOM:
            notes_title.remove();
            notes_textarea.remove();

            more_link.text('<?php echo escape($lang["rse_workflow_link_open"]); ?>');
            more_link.attr('onClick', 'open_notes(' + action_ref + ');');

        }
        </script>
        <table cellpadding="0" cellspacing="0" id="ResourceWorkflowTable">
            <tbody>
            <?php
         
        foreach($validactions as $validaction)
            {
                $show_more_link = false;
                if(!empty($validaction['more_notes_flag']) && $validaction['more_notes_flag'] == 1) {
                    $show_more_link = true;
                }
            ?>
             <tr class="DownloadDBlend">
                <td><?php echo escape(i18n_get_translated($validaction["text"],"workflow-actions")); if($show_more_link) { ?><a href="#" id="more_link_<?php echo $validaction["ref"]; ?>" onClick="open_notes(<?php echo $validaction["ref"]; ?>);" style="float: right;"><?php echo escape($lang['rse_workflow_link_open']); ?></a><?php } ?></td>
                <td>
                    <form action="<?php echo $baseurl_short?>pages/view.php?ref=<?php echo urlencode($ref)?>&search=<?php echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php echo urlencode($archive)?>&curpos=<?php echo urlencode($curpos)?>&workflowaction=<?php echo urlencode($validaction["ref"])?>" 
                          id="resource_<?php echo $ref; ?>_workflowaction<?php echo $validaction['ref']; ?>">
                    <input id='resource_status_checksum_<?php echo $validaction["ref"]; ?>' name='resource_status_check_<?php echo $validaction["ref"]; ?>' type='hidden' value='<?php echo $resource["archive"]; ?>'>
                    <?php
                if(isset($modal) && $modal=="true")
                    {
                    ?>
                    <input type="hidden" name="modal" id="rse_workflow_modal_<?php echo $validaction["ref"]; ?>" value="true" >
                    <?php
                    }
                    ?>
                    <input type="hidden" name="rse_workflow_action_<?php echo $validaction["ref"]; ?>" id="rse_workflow_action_<?php echo $validaction["ref"]; ?>" value="true" >
                    <input type="hidden" name="more_workflow_action_<?php echo $validaction["ref"]; ?>" id="more_workflow_action_<?php echo $validaction["ref"]; ?>" value="" >       
                    <input type="submit" name="rse_workflow_action_submit_<?php echo $validaction["ref"]; ?>" id="rse_workflow_action_submit_<?php echo $validaction["ref"]; ?>" value="&nbsp;<?php echo escape(i18n_get_translated($validaction["buttontext"],"workflow-actions")); ?>&nbsp;" onClick="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('resource_<?php echo $ref; ?>_workflowaction<?php echo $validaction['ref']; ?>'), true);" >
                    <?php
                    generateFormToken("resource_{$ref}_workflowaction{$validaction['ref']}");
                    hook("rse_wf_formend","",array($resource["archive"],$validaction["statusto"]));
                    ?>
                </form>
                </td>
            </tr>                               
            
            
            
            <?php
            }?>
        </tbody></table>
        </div><!-- End of RecordDownloadSpace-->
        <?php
        }
    }
    
function HookRse_workflowViewAdditionaldownloadtabbuttons()
    {
    global $lang, $modal;

    $validactions = rse_workflow_get_valid_actions(rse_workflow_get_actions(), false);

    if (count($validactions) > 0)
        {
        ?>
        <div class="Tab" id="ResourceWorkflowActionsButton">
            <a href="#" onclick="selectDownloadTab('ResourceWorkflowActions',<?php echo $modal ? 'true' : 'false'; ?>);">
                <?php echo escape($lang["rse_workflow_actions_heading"]) ?>
            </a>
        </div>
        <?php
        }
    }

function HookRse_workflowViewReplacetitleprefix($state)
    {
    global $lang,$additional_archive_states;

    if ($state<=3) {return false;} # For custom states only.

    $name=ps_value("SELECT name value FROM archive_states WHERE code = ?",["i",$state],"");
    
    ?><span class="ResourceTitleWorkflow<?php echo $state ?>"><?php echo i18n_get_translated($name) ?>:</span>&nbsp;<?php
    return true;
    }
    
    

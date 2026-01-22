<?php
#
# rse_workflow actions setup page, requires System Setup permission
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);} 
include_once '../include/rse_workflow_functions.php';

# Retrieve list of existing defined actions 
$workflowactions = rse_workflow_get_actions();

$filterstate=getval("filterstate","all");
if ($filterstate!="all")
    {
    $a=count($workflowactions);
    for($n=0;$n<$a;$n++)
        {
        $fromactions=explode(",",$workflowactions[$n]["statusfrom"]);       
        if (!in_array($filterstate,$fromactions))
            {           
            unset($workflowactions[$n]);
            }
        }   
    }   
$delete=getval("delete","");
if ($delete!="")
    {
    # Delete action
    $deleted=rse_workflow_delete_action($delete);
    if($deleted){$noticetext=$lang['rse_workflow_action_deleted'];}
    else{$noticetext=$lang['error'];}
    }
    

    
include '../../../include/header.php';

if (isset($noticetext))
    {
    echo "<div class=\"PageInformal\">" . $noticetext . "</div>";   
    }

?>
<script>
        
function deleteaction(ref)
        {
        event.preventDefault();
        event.stopPropagation();        
        
        if(confirm('<?php echo escape($lang["rse_workflow_confirm_action_delete"]); ?>'))
                {
                CentralSpaceLoad("<?php echo $baseurl?>/plugins/rse_workflow/pages/edit_workflow_actions.php?delete=" + ref, true);         
                }
        return true;
        }
                
        
</script>


<div class="BasicsBox">
<h1><?php echo escape($lang["rse_workflow_manage_actions"]); ?></h1>
<?php
$links_trail = array(
    array(
        'title' => $lang["systemsetup"],
        'href'  => $baseurl_short . "pages/admin/admin_home.php",
        'menu' =>  true
    ),
    array(
        'title' => $lang["rse_workflow_manage_workflow"],
        'href'  => $baseurl_short . "plugins/rse_workflow/pages/edit_workflow.php"
    ),
    array(
        'title' => $lang["rse_workflow_manage_actions"]
    )
);

renderBreadcrumbs($links_trail);
?>
<div class="clearerleft" ></div>

<div class="BasicsBox">
    <form method="post" name="form_filter_action" id="form_filter_action" action="<?php echo $baseurl_short?>plugins/rse_workflow/pages/edit_workflow_actions.php">
        <?php generateFormToken("form_filter_action"); ?>
        <div class="Question">
            <label for="filterstate"><?php echo escape($lang["rse_workflow_action_filter"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <select class="stdwidth" name="filterstate" id="filterstate" >
                    <option value="all" <?php if($filterstate=="all"){echo " selected";}?>><?php echo escape($lang["all"]); ?></option>
                    <?php
                    for ($n=-2;$n<=3;$n++)
                        {
                        echo "<option value=\"" . $n ."\" " . (($n==$filterstate && is_numeric($filterstate))?" selected":"") .  ">" . $lang["status" . $n] . "</option>"; 
                        }
                    foreach ($additional_archive_states as $additional_archive_state)
                        {
                        echo "<option value=\"" . $additional_archive_state . "\"" . (($additional_archive_state==$filterstate)?" selected":"") .  ">" . ((isset($lang["status" . $additional_archive_state]))?$lang["status" . $additional_archive_state]:$additional_archive_state) . "</option>";
                        }   
                    ?>
                    </select>
             </div>
             <div class="Inline"><input name="filtersubmit" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["searchbutton"]); ?>&nbsp;&nbsp;" onclick="preventDefault();CentralSpacePost(document.getElementById('form_filter_action'),false);"></div>
            </div>
        <div class="clearerleft"> </div>
        </div>
    </form>
</div>
<h2><?php echo escape($lang['rse_workflow_status_heading']); ?></h2>
<div class="BasicsBox">
<div class="Listview">
        <table class="ListviewStyle rse_workflow_table" id='rse_workflow_table'>
            <tr class="ListviewTitleStyle">
                <th>
                <?php echo escape($lang['rse_workflow_action_name']); ?>
                </th><th>
                <?php echo escape($lang['rse_workflow_action_text']); ?>
                </th><th>
                <?php echo escape($lang['rse_workflow_button_text']); ?>
                </th><th>               
                <?php echo escape($lang['rse_workflow_action_status_from']); ?>
                </th><th>
                <?php echo escape($lang['rse_workflow_action_status_to']); ?>
                </th><th>
                <?php echo escape($lang['rse_workflow_action_reference']); ?>
                </th><th>
                <?php echo escape($lang['tools']); ?>
                </th>
            </tr>

<?php

if (count($workflowactions)==0)
    {
    echo "<tr><td colspan='7'>" . $lang["rse_workflow_action_none_defined"] . "</td></tr>";
    }
else
    {
    foreach ($workflowactions as $workflowaction)
        {
        # Show actions relevant to this status
        if($workflowaction["ref"]==$delete){continue;}
        echo "<tr class=\"rse_workflow_link\" onclick=\"CentralSpaceLoad('" .  $baseurl . "/plugins/rse_workflow/pages/edit_action.php?ref=" . $workflowaction["ref"] . "',true);\">";
        ?>
            <td><div class="ListTitle"><?php echo escape($workflowaction["name"]); ?></div>
            </td>
            <td><?php echo escape($workflowaction["text"]); ?>
            </td>
            <td><?php echo escape($workflowaction["buttontext"]); ?>
            </td>
            <td><?php
                $fromstates=explode(",",$workflowaction["statusfrom"]);
                $fromstatetext="";
                foreach ($fromstates as $fromstate)
                    {
                    if(!isset($lang["status" . $fromstate])){continue;} //This state has been deleted
                    if($fromstatetext!=""){$fromstatetext.=", ";}
                    $fromstatetext.=$lang["status" . $fromstate]; 
                    }
                if($fromstatetext==""){$fromstatetext=$lang["rse_workflow_err_missing_wfstate"];}
                echo $fromstatetext; 
                ?>
            </td>
            <td><?php echo ( isset($lang["status".$workflowaction["statusto"]]) ) ? $lang["status".$workflowaction["statusto"]] : $workflowaction["statusto"]; ?>
            </td>
            <td>wf<?php echo $workflowaction["ref"]; ?>
            </td>
            <td class="ListTools">
            <a href="<?php echo $baseurl?>/plugins/rse_workflow/pages/edit_action.php?ref=<?php echo $workflowaction["ref"]; ?>" onclick="return CentralSpaceLoad(this,true);"><i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?> </a>
            <a href="<?php echo $baseurl?>/plugins/rse_workflow/pages/edit_workflow_actions.php?delete=<?php echo $workflowaction["ref"]; ?>" class="deleteaction" onClick="deleteaction(<?php echo $workflowaction["ref"]; ?>,true);"><i class="icon-trash-2"></i>&nbsp;<?php echo escape($lang["action-delete"]); ?> </a>
            </td>
        </tr>
        <?php	
        }
    
    }
?>
</table>
</div>


<a href="<?php echo $baseurl_short?>plugins/rse_workflow/pages/edit_action.php?ref=new" onclick="event.preventDefault();CentralSpaceLoad(this,true);"><?php echo LINK_CARET . $lang["rse_workflow_action_new"]; ?></a>


</div>
</div>
<?php

include '../../../include/footer.php';

<?php
#
# rse_workflow edit workflow states page, requires System Setup permission
#
include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}
include_once '../include/rse_workflow_functions.php';

$wfstates=rse_workflow_get_archive_states();

$delete=getval("delete","",true);
$saveerror=false;
if($delete!="" && (in_array($delete,$fixed_archive_states) || ($delete>-3 && $delete <4) || !isset($wfstates[$delete]))){$saveerror=true;$noticetext=$lang["rse_workflow_state_not_editable"];}

$deletenewstate=getval("deletenewstate","",true);
if($delete!="" && ($deletenewstate=="" || $deletenewstate==$delete))
   {
    $saveerror=true;
    $noticetext=$lang["rse_workflow_state_need_target"];
   }

if ($delete!="" &&  !$saveerror)
    {
    # Delete state
    $deleted=rse_workflow_delete_state($delete,$deletenewstate);
    if($deleted){$noticetext=$lang['rse_workflow_state_deleted'];unset($wfstates[$delete]);}
    else{$noticetext=$lang['error'];}
    }

include '../../../include/header.php';

if (isset($noticetext))
    {
    echo "<div class=\"PageInformal\">" . $noticetext . "</div>";   
    }

?>

<script>
        
function deletestate(code)
        {
        if(code === undefined || code == '')
            {
            code = jQuery('#deletecode').val();
            }

        event.preventDefault();
        event.stopPropagation();

        if (jQuery('#form_delete_state').is(':hidden'))
            {   
            jQuery('#deletecode').val(code);
            jQuery('#form_delete_state').slideDown();
            return true;
            }

        if (jQuery('#deletecode').val() != code)
            {
            jQuery('#deletecode').val(code);
            jQuery('#deletenewstate').val("");  
            return true;
            }
                
        if(confirm('<?php echo escape($lang["rse_workflow_confirm_state_delete"]); ?>'))
            {
            CentralSpacePost(document.getElementById('form_delete_state'),true)         
            }
        return true;
        }
                
        
</script>


<div class="BasicsBox">
<h1><?php echo escape($lang["rse_workflow_manage_states"]); ?></h1>
<div class="clearerleft" ></div>

<form style="display:none" id="form_delete_state" name="form_delete_state" method="post" action="<?php echo $baseurl_short?>plugins/rse_workflow/pages/edit_workflow_states.php">
    <?php generateFormToken("form_delete_state"); ?>    
    <input type="hidden" id="deletecode" name="delete" value="">
    <div id="status_name_question">
    <?php echo escape($lang["rse_workflow_state_need_target"]); ?>
    <br><br>
    <select class="stdwidth" name="deletenewstate" id="deletenewstate" >
    <option value="">&nbsp;</option>
    <?php
    foreach ($wfstates as $wfstate=>$wfstateinfo)
        {
        echo "<option value=\"" . $wfstate . "\">" . ((isset($lang["status" . $wfstate]))?$lang["status" . $wfstate]:$wfstate) . "</option>";
        }   
    ?>
    </select>
    <div class="clearerleft"> </div>
    </div>
    <br>
    <input name="deletebutton" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["action-delete"]); ?>&nbsp;&nbsp;" onclick="event.preventDefault();deletestate();"/>
    
</form>

<div>
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
        'title' => $lang["rse_workflow_manage_states"]
    )
);

renderBreadcrumbs($links_trail);
?>
<div class="Listview">
        <table class="ListviewStyle rse_workflow_table" id='rse_workflow_table'>
            <tr class="ListviewTitleStyle">
                <th>
                <?php echo escape($lang['rse_workflow_state_name']); ?>
                </th><th>
                <?php echo escape($lang['rse_workflow_state_reference']); ?>
                </th><th>
                <?php echo escape($lang['tools']); ?>
                </th>
            </tr>

<?php
foreach ($wfstates as $wfstate=>$wfstateinfo)
    {
    # Show actions relevant to this status
                                                     
    echo "<tr class=\"rse_workflow_link\" onclick=\"CentralSpaceLoad('" .  $baseurl . "/plugins/rse_workflow/pages/edit_state.php?code=" . $wfstate . "',true);\">";
    ?>
        <td><?php echo escape($wfstateinfo["name"]); ?>
        </td>
        <td><?php echo $wfstate; ?>
        </td>                   
        <td class="ListTools">
        <a href="<?php echo $baseurl?>/plugins/rse_workflow/pages/edit_state.php?code=<?php echo $wfstate ?>" onclick="return CentralSpaceLoad(this,true);" ><i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?> </a>
        <?php
        if(!in_array($wfstate,$fixed_archive_states))
            {?>
            <a href="<?php echo $baseurl?>/plugins/rse_workflow/pages/edit_workflow_states.php?delete=<?php echo $wfstate ?>" class="deletestate" onClick="deletestate(<?php echo $wfstate ?>);"><i class="icon-trash-2"></i>&nbsp;<?php echo escape($lang["action-delete"]); ?> </a>
            <?php
            }
            ?>
        </td>
    </tr>
    <?php	
    }
    
?>
</table>
</div>


<a href="<?php echo $baseurl_short?>plugins/rse_workflow/pages/edit_state.php?code=new" onclick="event.preventDefault();return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . $lang["rse_workflow_state_new"]; ?></a>


</div>
</div>
<?php

include '../../../include/footer.php';

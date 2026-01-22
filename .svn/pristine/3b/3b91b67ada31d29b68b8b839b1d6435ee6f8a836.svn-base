<?php
#
# rse_workflow edit action page, requires System Setup permission
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}
include_once '../include/rse_workflow_functions.php';

$ref=getval("ref","");

if($ref==""){exit($lang["rse_workflow_action_none_specified"]);}
# Retrieve action
if($ref=="new")
    {
    $workflowaction["statusfrom"]="";
    $workflowaction["statusto"]="";
    $workflowaction["text"]="";
    $workflowaction["buttontext"]="";
    $workflowaction["name"]="";
    }
else
    {
    $workflowaction = rse_workflow_get_actions("",$ref);
    $workflowaction=$workflowaction[0];
    }

if (getval("submitted","")!="" && enforcePostRequest(false))
    {
    $saveerror  = false;
    $ref        = getval("ref","");
    $tostate    = getval("actionto",999,true);
    $name       = getval("actionname","");
    $text       = getval("actiontext","");
    $buttontext = getval("buttontext","");  
    
    # construct a list of from states from the ticked boxes
    $fromstatesstring="";
    for ($n=-2;$n<=3;$n++)
        {
        if (getval("from" . $n,"")=="yes")
            {
            if($n==$tostate){$saveerror=true;}
            if ($fromstatesstring!="") {$fromstatesstring.=",";}
            $fromstatesstring.=$n;
            }
        }
    foreach ($additional_archive_states as $additional_archive_state)
        {
        if (getval("from" . $additional_archive_state,"")=="yes")
            {
            if($additional_archive_state==$tostate){$saveerror=true;}
            if ($fromstatesstring!="") {$fromstatesstring.=",";}
            $fromstatesstring.=$additional_archive_state;
            }
        }   
            
            
    if(!$saveerror)
        {
        if($ref=="new")
            {
            ps_query("INSERT INTO workflow_actions (name,text,buttontext,statusfrom,statusto) VALUES (?,?,?,?,?)",
                [
                "s",$name,
                "s",$text,
                "s",$buttontext,
                "s",$fromstatesstring,
                "i",$tostate
                ]);
            }
        else
            {
            ps_query("UPDATE workflow_actions SET name = ?, text = ?, buttontext = ?, statusfrom = ?, statusto = ? where ref = ?",
                [
                "s",$name,
                "s",$text,
                "s",$buttontext,
                "s",$fromstatesstring,
                "i",$tostate,
                "i",$ref
                ]
                );
            }
        }
    $workflowaction["statusfrom"]=$fromstatesstring;
    $workflowaction["statusto"]=$tostate;
    $workflowaction["text"]=$text;
    $workflowaction["buttontext"]=$buttontext;
    $workflowaction["name"]=$name;
    }
    

include '../../../include/header.php';

?>

<script>

function SaveWorkflowAction(){
    if(jQuery('#actionsfrom').val()==jQuery('#actionto').val())
        {
        alert('<?php echo escape($lang["rse_workflow_action_check_fields"]); ?>');
        return false;
        }
    CentralSpacePost(document.getElementById('form_workflow_action'),false);
    }

</script>

<?php

if (isset($saveerror)) {
    if($saveerror) {
        toast_notification(ToastNotificationType::Error, $lang['rse_workflow_action_check_fields']);
    }
    else {
        toast_notification(ToastNotificationType::Success, $lang['saved']);
    }
}

?>
        
<div class="BasicsBox">
<h1><?php echo escape($lang["rse_workflow_action_edit_action"]); ?></h1>
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
        'title' => $lang["rse_workflow_manage_actions"],
        'href'  => $baseurl_short . "plugins/rse_workflow/pages/edit_workflow_actions.php"
    ),
    array(
        'title' => $lang["rse_workflow_action_edit_action"]
    )
);

renderBreadcrumbs($links_trail);
$workflowaction_url = generateURL($baseurl_short . "plugins/rse_workflow/pages/edit_action.php",['ref'=>$ref]);
?>

<form id="form_workflow_action" name="form_workflow_action" method="post" action="<?php echo $workflowaction_url;?>">
    <?php generateFormToken("form_workflow_action"); ?>
    <input type="hidden" name="ref" id="actionref" value="<?php echo escape($ref) ?>" />
    <input type="hidden" name="submitted" value="true">
        
    <div class="Question" id="actionname_question">
    <label for="actionname"><?php echo escape($lang["rse_workflow_action_name"]); ?></label>
    <input class="stdwidth" type="text" name="actionname" id="actionname" value="<?php echo escape($workflowaction["name"]);  ?>" />
    <div class="clearerleft"> </div>
    </div>

    
    <div class="Question" id="actiontext_question">
    <label for="actiontext"><?php echo escape($lang["rse_workflow_action_text"]); ?></label>
    <input class="stdwidth" type="text" name="actiontext" id="actiontext" value="<?php echo escape($workflowaction["text"]);  ?>" />
    <div class="clearerleft"> </div>
    </div>
    
    <div class="Question" id="buttontext_question">
    <label for="buttontext"><?php echo escape($lang["rse_workflow_button_text"]); ?></label>
    <input
        class="stdwidth"
        type="text"
        name="buttontext"
        id="buttontext"
        value="<?php echo escape($workflowaction["buttontext"]);  ?>"
    />
    <div class="clearerleft"> </div>
    </div>
    
    <div class="Question" id="actionfrom_question">
    <label for="actionfrom"><?php echo escape($lang["rse_workflow_action_status_from"]); ?></label>
    
    <table cellpadding=2 cellspacing=0>
    <?php
    $fromstates=explode(",",$workflowaction["statusfrom"]); 
    for ($n=-2;$n<=3;$n++)
        {?>
        <tr><td width="1"><input type="checkbox" name="from<?php echo $n?>" value="yes" <?php if (in_array($n,$fromstates)) {?>checked<?php } ?>/></td><td><?php echo escape($lang["status" . $n]); ?>&nbsp;</td></tr>
        <?php
        }
    foreach ($additional_archive_states as $additional_archive_state)
        {?>
        <tr><td width="1"><input type="checkbox" name="from<?php echo $additional_archive_state?>" value="yes" <?php if (in_array($additional_archive_state,$fromstates)) {?>checked<?php } ?>/></td><td><?php echo escape($lang["status" . $additional_archive_state]); ?>&nbsp;</td></tr>
        <?php	  
        }
    ?></tr></table> 
    <div class="clearerleft"> </div>
    </div>
    
    <div class="Question" id="actionto_question">
    <label for="actionto"><?php echo escape($lang["rse_workflow_action_status_to"]); ?></label>
    <select class="stdwidth" name="actionto" id="actionto" >
    <?php
    for ($n=-2;$n<=3;$n++)
        {?>
        <option value="<?php echo $n ?>" <?php if ($n==$workflowaction["statusto"]) {echo " selected";} ?>><?php echo escape($lang["status" . $n]); ?></option>';<?php
        }
    foreach ($additional_archive_states as $additional_archive_state)
        {?>
        <option value="<?php echo (int)$additional_archive_state ?>" <?php if ($additional_archive_state==$workflowaction["statusto"]) {echo " selected";} ?>><?php echo escape($lang["status" . $additional_archive_state]); ?></option>';<?php
        }   
    ?>
    </select>
    <div class="clearerleft"> </div>
    </div>
    
    <div class="Question" id="QuestionSubmit">
    <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;" onclick="event.preventDefault();SaveWorkflowAction();"/>
    </div>
</form>
    <div class="clearerleft"> </div>
</div>
<?php

include '../../../include/footer.php';

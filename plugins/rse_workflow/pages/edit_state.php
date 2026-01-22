<?php
#
# rse_workflow edit archive/workflow states page, requires System Setup permission
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}
unset($additional_archive_states);$additional_archive_states=array();
include_once '../include/rse_workflow_functions.php';
include __DIR__ . "/../../../lib/lucide/icon_classes.php";

$code=getval("code","");
$name=getval("name","");
$notify_group=getval("notify_group",0, true);
$more_notes = getval('more_notes', 0, true);
$notify_user = getval('notify_user', 0, true);
$rse_workflow_email_from = getval('rse_workflow_email_from', '');
$rse_workflow_bcc_admin = getval('rse_workflow_bcc_admin', 0, true);
$simple_search = getval('simple_search', 0, true);
$icon = getval('icon', '');
$skip_required_fields = getval('skip_required_fields', 0, true);

// Check valid icon
if (!in_array($icon, $lucide_icons)) {
    $icon = WORKFLOW_DEFAULT_ICON;
}

$errortext="";
$saved=false;

global $additional_archive_states;
# Get state details
$fixedstate=false;
        
if($code=="new")
    {
    $workflowstate["fixed"]="false";
    $workflowstate["name"]="";
    $workflowstate["notify_group"]="";
    $workflowstate['more_notes_flag'] = 0;
    $workflowstate['notify_user_flag'] = 0;
    $workflowstate["rse_workflow_email_from"] = "";
    $workflowstate["rse_workflow_bcc_admin"] = 0;
    $workflowstate["simple_search_flag"] = 0;
    $workflowstate["icon"] = WORKFLOW_DEFAULT_ICON;
    $workflowstate["skip_required_fields"] = 0;
    }
else
    {   
    $workflowstates = rse_workflow_get_archive_states();
    // Check that this is a valid state to edit
    if($code=="" || in_array($code,$additional_archive_states) || ($code>-3 && $code <4) || !isset($workflowstates[$code]))
        {
        $fixedstate=true;
        }
    $workflowstate=$workflowstates[$code];
    }


if (getval("submitted","")!="" && enforcePostRequest(getval("ajax", false)))
    {
    if ($name=="")
        {
        $errortext=$lang["rse_workflow_state_check_fields"];
        }
    if($errortext=="")
        {
        if($code=="new")
            {
            rse_workflow_create_state([
                'name' => $name,
                'notify_group' => $notify_group,
                'more_notes_flag' => $more_notes,
                'notify_user_flag' => $notify_user,
                'email_from' => '',
                'bcc_admin' => $rse_workflow_bcc_admin,
                'simple_search_flag' => $simple_search,
                'icon' => $icon,
                'skip_required_fields' => $skip_required_fields
            ]);
            }
        else
            {
            ps_query("
                UPDATE archive_states
                   SET name = ?,
                       notify_group = ?,
                       more_notes_flag = ?,
                       notify_user_flag = ?,
                       email_from = '',
                       bcc_admin = ?,
                       simple_search_flag = ?,
                       icon = ?,
                       skip_required_fields = ?
                 WHERE code = ?",
                    [
                    "s", $name,
                    "i", $notify_group,
                    "i", $more_notes,
                    "i", $notify_user,
                    "i", $rse_workflow_bcc_admin,
                    "i", $simple_search,
                    "s", $icon,
                    "i", $skip_required_fields,
                    "i", $code,
                    ]
                );
            }
        
        clear_query_cache("workflow");
        $saved=true;
        }
    
    $workflowstate["name"]=$name;
    $workflowstate["notify_group"]=$notify_group;
    $workflowstate['more_notes_flag']=$more_notes;
    $workflowstate['notify_user_flag']=$notify_user;
    $workflowstate["rse_workflow_email_from"]='';
    $workflowstate["rse_workflow_bcc_admin"]=$rse_workflow_bcc_admin;
    $workflowstate["simple_search_flag"] = $simple_search;
    $workflowstate["icon"] = $icon;
    $workflowstate["skip_required_fields"] = $skip_required_fields;
    }   
    
    

include '../../../include/header.php';

?>

<div class="BasicsBox">
<h1><?php echo escape($lang["rse_workflow_edit_state"]); render_help_link("plugins/workflow");?></h1> 
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
        'title' => $lang["rse_workflow_manage_states"],
        'href'  => $baseurl_short . "plugins/rse_workflow/pages/edit_workflow_states.php"
    ),
    array(
        'title' => $lang["rse_workflow_edit_state"]
    )
);

renderBreadcrumbs($links_trail);
?>
</div>

<?php
if($errortext!="") {
    toast_notification(ToastNotificationType::Error, $lang['rse_workflow_state_check_fields']);
}
elseif ($saved) {
    toast_notification(ToastNotificationType::Success, $lang['saved']);
}

$workflowstate_url = generateURL($baseurl_short."plugins/rse_workflow/pages/edit_state.php",["code"=>$code]);
?>
<div class="BasicsBox">

<form id="form_workflow_state" name="form_workflow_state" method="post" action="<?php echo $workflowstate_url;?>">
    <?php generateFormToken("form_workflow_state"); ?>
    <input type="hidden" name="submitted" value="true">
    <input type="hidden" name="code" value="<?php echo escape($code);  ?>">
        
    <div class="Question" id="status_name_question">
    <label for="name"><?php echo escape($lang["rse_workflow_state_name"]); ?></label>
    <?php if ($fixedstate)
        {?>
        <div class="Fixed"><?php echo escape($workflowstate["name"]); ?></div>
        <input type="hidden" name="name" value="<?php echo escape($workflowstate["name"]);  ?>">
        <?php
        }
    else
        {?>
        <input type="text" class="stdwidth" name="name" id="name" value="<?php echo escape($workflowstate["name"]);  ?>" />
        <?php
        }
        ?>
    <div class="clearerleft"> </div>
    </div>

        <div class="Question" id="status_notify_group_question">
    <label for="notify_group"><?php echo escape($lang["rse_workflow_state_notify_group"]); ?></label>
        <select class="stdwidth" name="notify_group" id="notify_group">
        <option value=""></option>
        <?php
        $groups=get_usergroups(true);
        for ($n=0;$n<count($groups);$n++)
                {
                ?>
                <option value="<?php echo $groups[$n]["ref"]; ?>" <?php if ($workflowstate["notify_group"]==$groups[$n]["ref"]) {?>selected<?php } ?>><?php echo $groups[$n]["name"]; ?></option>
                <?php
                }?>
        </select>
        <div class="FormHelp" >
            <div class="FormHelpInner"><?php echo escape($lang["rse_workflow_state_notify_help"]); ?></div>
        </div>
        <div class="clearerleft"> </div></div>

        <div class="Question" id="more_notes_question">
            <label for="more_notes"><?php echo escape($lang['rse_workflow_more_notes_label']); ?></label>
            <?php
                $more_notes_checked = '';
                if($workflowstate['more_notes_flag'] == 1) {
                    $more_notes_checked = 'checked';
                }
            ?>
            <input id="more_notes" type="checkbox" name="more_notes" value="1" <?php echo $more_notes_checked; ?>>
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="notify_user_question">
            <label for="notify_user"><?php echo escape($lang['rse_workflow_notify_user_label']); ?></label>
            <?php
                $notify_user_checked = '';
                if($workflowstate['notify_user_flag'] == 1) {
                    $notify_user_checked = 'checked';
                }
            ?>
            <input id="notify_user" type="checkbox" name="notify_user" value="1" <?php echo $notify_user_checked; ?>>
            <div class="clearerleft"></div>
        </div>
        
        <div class="Question" id="bcc_admin_question">
            <label for="bcc_admin"><?php echo str_replace("%ADMINEMAIL%",$email_notify,$lang['rse_workflow_bcc_admin']); ?></label>
            <?php
                $bcc_admin = '';
                if($workflowstate['rse_workflow_bcc_admin'] == 1)
                    {
                    $bcc_admin = 'checked';
                    }
            ?>
            <input id="rse_workflow_bcc_admin" type="checkbox" name="rse_workflow_bcc_admin" value="1" <?php echo $bcc_admin; ?>>
            <div class="clearerleft"></div>
        </div>

        <?php
        if($search_all_workflow_states || $code==0)
            {
            echo "<input id='simple_search' type='hidden' value='1' />";
            }
        else{
            ?>
            <div class="Question" id="simple_search_question">
                <label for="simple_search"><?php echo escape($lang['rse_workflow_simple_search_label']); ?></label>
                <?php
                    $simple_search_checked = '';
                    if($workflowstate['simple_search_flag'] == 1) {
                        $simple_search_checked = 'checked';
                    }
                ?>
                <input id="simple_search" type="checkbox" name="simple_search" value="1" <?php echo $simple_search_checked; ?>>
                <div class="clearerleft"></div>
            </div>
            <?php
            }

        render_lucide_icon_selector($lang["property-icon"],"icon",$workflowstate["icon"]);

        if (!in_array((int) $code, array(-2, $resource_deletion_state))) {
            ?>
            <div class="Question" id="skip_required_fields_question">
                <label for="skip_required_fields"><?php echo escape($lang['rse_workflow_skip_required_fields']); ?></label>
                <?php
                    $skip_required_fields = '';
                    if($workflowstate['skip_required_fields'] == 1) {
                        $skip_required_fields = 'checked';
                    }
                ?>
                <input id="skip_required_fields" type="checkbox" name="skip_required_fields" value="1" <?php echo $skip_required_fields; ?>>
                <div class="clearerleft"></div>
            </div>
            <?php
        }

    ?>
    <div class="Question" id="QuestionSubmit">
        <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;" onclick="event.preventDefault();CentralSpacePost(document.getElementById('form_workflow_state'),true);"/>
    </div>
</form>
</div>
<?php

include '../../../include/footer.php';
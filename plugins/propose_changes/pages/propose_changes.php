<?php

use SimpleSAML\Console\Application;

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';
include_once __DIR__ . '/../include/propose_changes_functions.php';

$ref=getval("ref","",true);
# Fetch search details (for next/back browsing and forwarding of search params)
$search=getval("search","");
$order_by=getval("order_by","relevance");
$offset=getval("offset",0,true);
$restypes=getval("restypes","");
if (strpos($search,"!")!==false) {$restypes="";}
$default_sort_direction="DESC";
if (substr($order_by,0,5)=="field"){$default_sort_direction="ASC";}
$sort=getval("sort",$default_sort_direction);
$archive=getval("archive",0,true);
$modal=(getval("modal","")=="true");

$errors=array(); # The results of the save operation (e.g. required field messages)
$editaccess=get_edit_access($ref);

if(isset($propose_changes_always_allow))
    {
    if(!$propose_changes_always_allow)
        {
        # Check user has permission.
        $parameters=array("i",$ref, "i",$userref);
        $proposeallowed=ps_value("SELECT r.ref value from resource r 
                left join collection_resource cr on r.ref=? and cr.resource=r.ref 
                left join user_collection uc on uc.user=? and uc.collection=cr.collection 
                left join collection c on c.ref=uc.collection where c.propose_changes=1", $parameters, "");
        if($proposeallowed=="" && $propose_changes_allow_open)
            {
            include_once '../../../include/do_search.php';
            $proposeallowed=(get_resource_access($ref)==0)?$ref:"";
            }
        }
    }
else
    {
    $error=$lang["error-plugin-not-activated"];
    header("Location:" . $baseurl . "/pages/user/user_messages.php");
    exit();
    }

if(!$propose_changes_always_allow && $proposeallowed=="" && !$editaccess)
    {
    # The user is not allowed to edit this resource or the resource doesn't exist.
    $error=$lang['error-permissiondenied'];
    header("Location:" . $baseurl . "/pages/user/user_messages.php");
    exit();
    }

if($editaccess)
    {
    $view_user = getval("proposeuser",0);

    if(getval("resetform","") != "" && enforcePostRequest(false))
        {
        delete_proposed_changes($ref, $view_user);
        }

    $userproposals = ps_query("SELECT pc.user, u.username from propose_changes_data pc 
            left join user u on u.ref=pc.user where resource=? 
            group by pc.user order by u.username asc", array("i",$ref));
    if(!in_array($view_user,array_column($userproposals,"user")) && count($userproposals) > 0)
        {
        $view_user = $userproposals[0]["user"];
        }
    $proposed_changes=get_proposed_changes($ref, $view_user);
    }
else
    {
     if(getval("resetform","") != "" && enforcePostRequest(false))
        {
        delete_proposed_changes($ref, $userref);
        }
    $proposed_changes=get_proposed_changes($ref, $userref);
    }

$arr_fields = get_resource_type_fields();
$fieldrestypes = [];
foreach($arr_fields as $arr_field)
    {
    $valid_restypes = explode(",",(string)$arr_field["resource_types"]);
    $fieldrestypes[$arr_field["ref"]] = $valid_restypes;
    }


$allfields = [];
foreach($arr_fields as $arr_field)
    {
    $allfields[$arr_field["ref"]] = $arr_field;
    }

# Fetch resource data.
$resource=get_resource_data($ref);

# Load resource data
$proposefields=get_resource_field_data($ref,false,true);

// Save data
if(
    (
        getval("save", "") != ""
        || (getval("submitted", "") != "" && getval("resetform", "") == "")
    )
    && enforcePostRequest(false)
    )
    {
    if($editaccess)
        {
        // Set a list of the fields we actually want to change - otherwise any fields we don't submit will get wiped
        $acceptedfields=array();
        foreach($proposed_changes as $proposed_change)
            {
            if(getval("accept_change_" . $proposed_change["resource_type_field"],"")=="on" && !getval("delete_change_" . $proposed_change["resource_type_field"],"")=="on")
                {
                $acceptedfields[]=$proposed_change["resource_type_field"];
                }
            }
        $proposed_changes_fields = array_values(array_filter(array_column($proposed_changes, 'resource_type_field')));
        // Actually save the data
        save_resource_data($ref,false,$acceptedfields);
        daily_stat("Resource edit",$ref);
        
        // send email to change  proposer with link
        $acceptedchanges=array();
        $acceptedchangescount=0;
        $deletedchanges=array();
        $deletedchangescount=0;
        
        $proposefields = get_resource_field_data($ref, false, true);
        for ($n=0;$n<count($proposefields);$n++)
            {
            node_field_options_override($proposefields[$n]);

            # Has this field been accepted?
            if (getval("accept_change_" . $proposefields[$n]["ref"],"")!="" && in_array($proposefields[$n]["ref"],$proposed_changes_fields))
                {
                debug("propose_changes - accepted proposed change for field " . $proposefields[$n]["title"]);
                $acceptedchanges[$acceptedchangescount]["field"]=$proposefields[$n]["title"];
                $acceptedchanges[$acceptedchangescount]["value"]=$proposefields[$n]["value"];
                $acceptedchangescount++;

                // remove this from the list of proposed changes
                $parameters=array("i",$view_user, "i",$proposefields[$n]['ref'], "i",$ref);
                ps_query("DELETE FROM propose_changes_data 
                    WHERE user = ? AND resource_type_field = ? AND resource = ?", $parameters);
                }

            # Has this field been deleted?
            if (getval("delete_change_" . $proposefields[$n]["ref"],"")!="" && in_array($proposefields[$n]["ref"],$proposed_changes_fields))
                {
                debug("propose_changes - deleted proposed change for field " . $proposefields[$n]["title"]);
                foreach($proposed_changes as $proposed_change)
                    {
                    if($proposed_change["resource_type_field"]==$proposefields[$n]["ref"])
                        {
                        if (in_array($proposed_change['type'], $FIXED_LIST_FIELD_TYPES)) {
                            $deleted_proposed_value = implode(
                                ', ',
                                array_intersect_key(
                                    array_column($proposefields[$n]['node_options'], 'translated_name', 'ref'),
                                    array_flip(array_filter(explode(', ', $proposed_change['value'])))
                                )
                            );

                        } else {
                            $deleted_proposed_value = $proposed_change['value'];
                        }

                        $deletedchanges[$deletedchangescount]["field"]=$proposefields[$n]["title"];
                        $deletedchanges[$deletedchangescount]["value"] = escape($deleted_proposed_value);
                        $deletedchangescount++;
                        }
                    }

                // remove this from the list of proposed changes
                $parameters=array("i",$view_user, "i",$proposefields[$n]['ref'], "i",$ref);
                ps_query("DELETE FROM propose_changes_data 
                    WHERE user = ? AND resource_type_field = ? AND resource = ?", $parameters);
                }
            }

        $templatevars['ref'] = $ref;
        $message = new ResourceSpaceUserNotification;
        $message->set_text("lang_propose_changes_proposed_changes_reviewed");
        $message->append_text($templatevars['ref'] . "<br/>");

        $changesummary = new ResourceSpaceUserNotification;
        $changesummary->set_text("lang_propose_changes_summary_changes");
        $changesummary->append_text("<br/><br/>");
        if($acceptedchangescount>0)
            {
            $changesummary->append_text("lang_propose_changes_proposed_changes_accepted");
            $changesummary->append_text("<br/>");
            }
        for($n=0;$n<$acceptedchangescount;$n++)
            {
            $changesummary->append_text($acceptedchanges[$n]["field"] . " : " . $acceptedchanges[$n]["value"] . "<br/>");
            }
        if($deletedchangescount>0)
            {
            $changesummary->append_text("<br/>");
            $changesummary->append_text("lang_propose_changes_proposed_changes_rejected");
            $changesummary->append_text("<br/><br/>");
            }
        for($n=0;$n<$deletedchangescount;$n++)
            {
            $changesummary->append_text($deletedchanges[$n]["field"] . " : " . escape($deletedchanges[$n]["value"]) . "<br/>");
            }

        $templatevars['changesummary']=$changesummary->get_text();
        $templatevars['url'] = generateURL($baseurl . "/pages/view.php",["ref"=> $ref]); 

        $message->append_text_multi($changesummary->get_text(true));
        $message->set_subject("lang_propose_changes_proposed_changes_reviewed");
        $message->url = $templatevars['url'];
        $message->template = "propose_changes_emailreviewed";
        $message->templatevars = $templatevars;

        send_user_notification([$view_user],$message);
        if(!$modal)
            {
            redirect($baseurl_short."pages/view.php?ref=" . $ref . "&search=" . urlencode($search) . "&offset=" . $offset . "&order_by=" . $order_by . "&sort=".$sort."&archive=" . $archive . "&refreshcollectionframe=true");
            exit();
            }
        else
            {
            $resulttext=$lang["changessaved"];
            }
        }
    else
        {
        // No edit access, save the proposed changes
        $save_errors=save_proposed_changes($ref);
        $submittedchanges=array();
        $submittedchangescount=0;
        if ($save_errors===true)
            {
            $proposed_changes=get_proposed_changes($ref, $userref);
            for ($n=0;$n<count($proposefields);$n++)
                {
                # Has a change to this field been proposed?
                foreach($proposed_changes as $proposed_change)
                    {
                    if($proposed_change['resource_type_field'] != $proposefields[$n]['ref'])
                        {
                        continue;
                        }

                    $proposed_change_value = $proposed_change['value'];
                    if(in_array($proposed_change['type'], $FIXED_LIST_FIELD_TYPES) && '' != $proposed_change_value)
                        {
                        $field_node_options    = extract_node_options(get_nodes($proposefields[$n]['ref'], null, true));
                        $proposed_change_value = array();

                        foreach(explode(', ', $proposed_change['value']) as $proposed_change_node_id)
                            {
                            if('' == $proposed_change_node_id)
                                {
                                continue;
                                }

                            $proposed_change_value[] = $field_node_options[$proposed_change_node_id];
                            }

                        if(is_array($proposed_change_value) && 0 < count($proposed_change_value))
                            {
                            $proposed_change_value = implode(', ', $proposed_change_value);
                            }
                        }
                    $submittedchanges[$submittedchangescount]["field"] = $proposefields[$n]["title"];
                    $submittedchanges[$submittedchangescount]["value"] = escape($proposed_change_value);
                    $submittedchangescount++;
                    }
                }

            // send email to admin/resource owner with link
    
            $changesummary = new ResourceSpaceUserNotification;
            $changesummary->set_text("lang_propose_changes_summary_changes");
            $changesummary->append_text("<br/><br/>");
            for($n=0;$n<$submittedchangescount;$n++)
                {
                $changesummary->append_text($submittedchanges[$n]["field"] . " : " . escape($submittedchanges[$n]["value"]) . "<br/>");
                }

            $templatevars['proposer']=(($username=="") ? $username : $userfullname);
            $templatevars['url'] = generateURL($baseurl . "/plugins/propose_changes/pages/propose_changes.php",["ref"=> $ref,"proposeuser" => $userref]); 

            $message = new ResourceSpaceUserNotification;
            $message->set_text("lang_propose_changes_proposed_changes_submitted");
            $message->append_text("<br/><br/>");
            $message->append_text($templatevars['proposer']);
            $message->append_text("lang_propose_changes_proposed_changes_submitted_text");
            $message->append_text($ref . "<br/><br/>");
            $message->append_text_multi($changesummary->get_text(true));
            $message->set_subject("lang_propose_changes_proposed_changes_submitted");
            $message->url = $templatevars["url"];
            $message->template = "propose_changes_emailproposedchanges";
            $message->templatevars = $templatevars;

            if($propose_changes_notify_admin)
                {
                debug("propose_changes: sending notifications to admins");
                $resource_admins = get_notification_users("RESOURCE_ADMIN");
                send_user_notification($resource_admins,$message);
                }

            if($propose_changes_notify_contributor)
                {
                $notify_user=get_user($resource["created_by"]);
                if($notify_user)
                    {
                    debug("propose_changes: sending notification to resource contributor, " . $notify_user['username'] . ", user id#" . $notify_user['ref'] . " (" . $notify_user['email'] . ")");
                    send_user_notification([$notify_user],$message);
                    }
                }

            foreach($propose_changes_notify_addresses as $propose_changes_notify_address)
                {
                if(filter_var($propose_changes_notify_address, FILTER_VALIDATE_EMAIL))
                    {
                    debug("propose_changes: sending submitted email to : ". $propose_changes_notify_address);
                    send_mail($propose_changes_notify_address, $applicationname . ": " . $lang["propose_changes_proposed_changes_submitted"], $message->get_text(), "", "", "emailproposedchanges", $templatevars);
                    }
                }

            $resulttext=$lang["propose_changes_proposed_changes_submitted"];
            }
        }
    }

include "../../../include/header.php";


if (isset($resulttext))
    {
    echo "<div class=\"PageInformal \">" . $resulttext . "</div>";
    }

$searchparams = get_search_params();
if(!$modal)
    {
    ?>
    <p><a href="<?php echo generateURL($baseurl . "/pages/view.php",$searchparams,["ref" => $ref]); ?>" onClick="return  <?php echo $modal ? "Modal" : "CentralSpace"; ?>Load(this,true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["backtoresourceview"]); ?></a></p>
    <?php
    }
    ?>
<div class="BasicsBox" id="propose_changes_box">
<h1 id="editresource">
<?php
if(!$editaccess)
    { 
    echo escape($lang['propose_changes_short']);
    }
else
    {
    echo escape($lang['propose_changes_review_proposed_changes']);
    }
?>
</h1>
<p>
<?php
if(!$editaccess)
    {
    echo escape($lang['propose_changes_text']);
    }
?>
</p>
    <?php
    if ((int) $resource["has_image"] !== RESOURCE_PREVIEWS_NONE)
        {
        ?><img alt="<?php echo escape(i18n_get_translated($resource['field'.$view_title_field] ?? ""));?>"
        src="<?php echo get_resource_path($ref,false,"thm",false,$resource["preview_extension"],-1,1,checkperm("w"))?>" class="ImageBorder" style="margin-right:10px;"/>
        <?php
        }
    else
        {
        # Show the no-preview icon
        echo get_nopreview_html((string) $resource["file_extension"], $resource["resource_type"]);
        }
    ?>

    <div class="Question" id="resource_ref_div" style="border-top:none;">
        <label><?php echo escape($lang["resourceid"]); ?></label>
        <div class="Fixed"><?php echo urlencode($ref) ?></div>
        <div class="clearerleft"> </div>
    </div>
    <?php

    if($editaccess && count($userproposals)>0)
        {
        ?>
        <div class="Question" id="ProposeChangesUsers">
        <form id="propose_changes_select_user_form" method="post" action="<?php echo generateURL($baseurl . "/plugins/propose_changes/pages/propose_changes.php",$searchparams,["ref" => $ref]); ?>" onsubmit="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(this,true);">
            <?php generateFormToken("propose_changes_select_user_form"); ?>
            <label><?php echo escape($lang["propose_changes_view_user"]); ?></label>
            <?php
            if(count($userproposals) > 1)
                {?>
                <select class="stdwidth" name="proposeuser" id="proposeuser" onchange="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('propose_changes_select_user_form'),false);">
                <?php 
                foreach ($userproposals as $userproposal)
                    {
                    echo  "<option value=" . $userproposal["user"] . " " . (($view_user==$userproposal["user"])?"selected":"") . ">" . escape($userproposal["username"]) . "</option>";
                    }
                ?>
                </select>
                <?php
                }
            else
                {
                ?>
                <div class="Fixed"><?php echo escape($userproposals[0]["username"]) ?></div>
                <?php
                }
                ?>
        </form>
        <div class="clearerleft"> </div>
        </div>
        <?php
        }

    $display_any_fields=false;
    $fieldcount=0;
    for ($n=0;$n<count($proposefields);$n++)
        {
        node_field_options_override($proposefields[$n]);
        if (
            $proposefields[$n]["active"] == 1 &&
            ($proposefields[$n]["global"]==1 || in_array($resource["resource_type"],$fieldrestypes[$proposefields[$n]["ref"]])) &&
            metadata_field_view_access($proposefields[$n]["ref"])
            )
            {
            $proposefields[$n]["display"]=true;
            $display_any_fields=true;
            break;
            }
        }
    if ($display_any_fields)
        {
        ?>
        
    <form id="propose_changes_form" method="post" action="<?php echo generateURL($baseurl . "/plugins/propose_changes/pages/propose_changes.php",$searchparams,["ref" => $ref]); ?>"  onsubmit="return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(this,true);">
    <?php generateFormToken("propose_changes_form"); ?>
    <h2 id="ProposeChangesHead"><?php echo escape($lang["propose_changes_proposed_changes"]); ?></h2><?php
        ?><div id="ProposeChangesSection">
                <div class="Question ProposeChangesQuestion" id="propose_changes_field_header" >
                        
                <div class="ProposeChangesTitle ProposeChangesLabel" ><?php echo escape($lang["propose_changes_field_name"]); ?></div>                
                <div class="ProposeChangesTitle ProposeChangesCurrent"><?php echo escape($lang["propose_changes_current_value"]); ?></div>
                <div class="ProposeChangesTitle ProposeChangesProposed" ><?php echo escape($lang["propose_changes_proposed_value"]); ?></div>
                
                <?php
                if($editaccess)
                    {
                    ?> 
                    <div class="ProposeChangesTitle ProposeChangesAcceptDeleteColumn" id="ProposeChangesAcceptDeleteColumn">
                    <table>
                    <tr>
                    <td>
                    <input id="ProposeChangesAcceptAllCheckbox" class="ProposeChangesAcceptCheckbox" type="checkbox" name="accept_all_changes" onClick="ProposeChangesUpdateAll(this);" checked ><?php echo escape($lang["propose_changes_accept_change"]); ?>
                    </td>
                    <td>
                    <input id="ProposeChangesDeleteAllCheckbox" class="ProposeChangesDeleteCheckbox" type="checkbox" name="delete_all_changes" onClick="ProposeChangesDeleteAll(this);" ><?php echo escape($lang["action-delete"]); ?>
                    </td>
                    </tr>
                    </table>
                    </div>
                    <?php
                    }
                ?>              
                <div class="clearerleft"> </div>
                </div><!-- End of propose_changes_field_header -->
        <?php
        }

    for ($n=0;$n<count($proposefields);$n++)
        {
        node_field_options_override($proposefields[$n]);

        # Should this field be displayed?
        if ((isset($proposefields[$n]["display"]) && $proposefields[$n]["display"]) ||
            ($proposefields[$n]["active"] == 1 &&
            ($proposefields[$n]["global"]==1 || in_array($resource["resource_type"],$fieldrestypes[$proposefields[$n]["ref"]])) &&
            metadata_field_view_access($proposefields[$n]["ref"])
            )
            )
            {   
            $fieldcount++;
            propose_changes_display_field($n, $proposefields[$n]);
            }
        }   

    // Let admin know there are no proposed changes anymore for this resources
    // Can happen when another admin already reviewed the changes.
    $changes_to_review_counter = 0;
    foreach($proposefields as $propose_field)
        {
        foreach($proposed_changes as $proposed_change)
            {
            if($proposed_change['resource_type_field'] == $propose_field['ref'])
                {
                $changes_to_review_counter++;
                }
            }
        }

    if($editaccess && empty($propose_changes) && $changes_to_review_counter == 0)
        {
        ?>
        <div id="message" class="Question ProposeChangesQuestion">
            <?php echo strip_tags_and_attributes($lang['propose_changes_no_changes_to_review']); ?>
        </div>
        <?php
        }?>

    <div class="QuestionSubmit">
        <input id="resetform" name="resetform" type="hidden" value=""/>
        <input id="save"  name="submitted" type="hidden" value="" />
        <input name="proposeuser" type="hidden" value="<?php echo isset($view_user) ? escape($view_user) : ""?>" />
        <input name="resetform" type="submit" value="<?php echo escape($lang["clearbutton"]); ?>" onClick="return jQuery('#resetform').val('true');"/>&nbsp;
            <?php if($editaccess)
                {?>
                <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["propose_changes_save_changes"]); ?>&nbsp;&nbsp;" onClick="return jQuery('#save').val('true');"/><br />
                <?php
                }
            else
                {?>
                <input name="save" type="submit" value="&nbsp;&nbsp;<?php echo escape($lang["save"]); ?>&nbsp;&nbsp;"  onClick="return jQuery('#save').val('true');"/><br />
                <?php
                }
                    ?>
        <div class="clearerleft"> </div>
    </div>

</form><!-- End of propose_changes_form -->

</div><!-- End of propose_changes_box -->
</div><!-- End of BasicsBox -->
<script>

function ShowProposeChanges(fieldref)
    {
    jQuery('#proposed_change_' + fieldref).show();
    jQuery('#propose_change_button_' + fieldref).hide();
    return false;
    }

function UpdateProposals(checkbox, fieldref)
    {
    if (checkbox.checked)
        {
        jQuery('#field_' + fieldref).prop('disabled',false); 
        jQuery('#propose_change_' + fieldref).prop('disabled',false);
        checkprefix="input[id^=" + fieldref + "_]";     
        jQuery(checkprefix).prop('disabled',false); // Enable checkboxes
        }
    else
        {
        jQuery('#field_' + fieldref).prop('disabled',true);
        jQuery('#propose_change_' + fieldref).prop('disabled',true);
        }
    }

function DeleteProposal(checkbox, fieldref)
    {
    if (checkbox.checked)
        {
        jQuery('#field_' + fieldref).prop('disabled',true);
        checkprefix="input[id^=" + fieldref + "_]";
        jQuery(checkprefix).prop('disabled',true); // Disable checkboxes
        jQuery('#accept_change_' + fieldref).prop('checked',false);
        jQuery('#accept_change_' + fieldref).prop('disabled',true);
        }
    else
        {
        jQuery('#accept_change_' + fieldref).prop('disabled',false);
        }
    }

function ProposeChangesUpdateAll(checkbox)
    {
    if (checkbox.checked)
        {
        jQuery('.ProposeChangesAcceptCheckbox').prop('checked',true);
        jQuery('.ProposeChangesDeleteCheckbox').prop('checked',false);
        jQuery('.ProposeChangesAcceptCheckbox').prop('disabled',false);
        }
    else
        {
        jQuery('.ProposeChangesAcceptCheckbox').prop('checked',false);
        }
    jQuery('.ProposeChangesAcceptCheckbox').trigger('change');
    }

function ProposeChangesDeleteAll(checkbox)
    {
    if (checkbox.checked)
        {
        jQuery('.ProposeChangesDeleteCheckbox').prop('checked',true);
        jQuery('.ProposeChangesAcceptCheckbox').prop('checked',false);
        jQuery('.ProposeChangesAcceptCheckbox').prop('disabled',true);
        }
    else
        {
        jQuery('.ProposeChangesDeleteCheckbox').prop('checked',false);
        jQuery('.ProposeChangesAcceptCheckbox').prop('disabled',false);
        }

    jQuery('.ProposeChangesAcceptCheckbox').trigger('change');
    }
</script>

<?php 

include "../../../include/footer.php";

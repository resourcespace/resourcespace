<?php
namespace RseVersion;

include '../../../include/boot.php';
include '../../../include/authenticate.php'; 
include_once '../../../include/image_processing.php';
include_once '../include/rse_version_functions.php';

if(is_valid_revert_state_request())
    {
    process_revert_state_form();
    include "../../../include/header.php";
    render_revert_state_form();
    include "../../../include/footer.php";
    exit();
    }

$ref=getval("ref",0,true);

# Load log entry
$log=ps_query("SELECT " . columns_in("resource_log") . ", rtf.ref `resource_type_field_ref`, rtf.type `resource_type_field_type` FROM resource_log 
        LEFT OUTER JOIN resource_type_field rtf ON resource_log.resource_type_field=rtf.ref 
        WHERE resource_log.ref=?",array("i",$ref));
if (count($log)==0) 
    {
    exit($lang["rse_version_log_not_found"]);
    }
$log=$log[0];
$resource=$log["resource"];

# Check edit permission.
if (!get_edit_access($resource))
    {
    # The user is not allowed to edit this resource or the resource doesn't exist.
    $error=$lang['error-permissiondenied'];
    error_alert($error,true, 401);
    exit();
    }

$field=$log["resource_type_field"];
$type=$log["type"];

$nodes_to_add=array();
$nodes_to_remove=array();
$node_strings_not_found=array();
$cattree = false;
// FIELD_TYPE_DATE_RANGE is a special type holding up to 2 nodes per resource (star/end dates). See definitions.php for more info.
$is_fixed_field = in_array($log['resource_type_field_type'], array_merge($FIXED_LIST_FIELD_TYPES, [FIELD_TYPE_DATE_RANGE]));

if ($type==LOG_CODE_EDITED || $type==LOG_CODE_MULTI_EDITED || $type==LOG_CODE_NODE_REVERT)
    {
    # ----------------------------- PROCESSING FOR "e" (edit) and "m" (multi edit) METADATA ROWS ---------------------------------------------

    // resolve node changes
    if($is_fixed_field)
        {    
        // Create array of all possible values with indexes as the values to look for - precedence of index as follows (last added): 
        // 1) Full path untranslated
        // 2) Full path translated
        // 3) Name untranslated
        // 4) Name translated
        $nodes_available_keys = [];
        $cattree = $log['resource_type_field_type'] == FIELD_TYPE_CATEGORY_TREE;
        $nodes_available_full = get_nodes($log['resource_type_field'],null,$cattree);
        $nodes_by_ref = [];
        foreach($nodes_available_full as $node_details)
            {
            $nodes_by_ref[$node_details["ref"]] = $node_details;
            if($cattree)
                {                
                $nodes_available_keys[mb_strtolower($node_details["path"])] = $node_details["ref"];
                $nodes_available_keys[mb_strtolower($node_details["translated_path"])] = $node_details["ref"];
                }
            $nodes_available_keys[mb_strtolower($node_details["name"])] = $node_details["ref"];
            $nodes_available_keys[mb_strtolower($node_details["translated_name"])] = $node_details["ref"];
            }

        // All to be added
        preg_match_all('/^\s*\-\s*(.*?)$/m',$log['diff'],$matches);
        if(isset($matches[1][0]))
            {
            foreach ($matches[1] as $match)
                {
                $match=trim(mb_strtolower($match));
                $found_key=array_search($match,$nodes_available_full);
                if(isset($nodes_available_keys[$match]))
                    {
                    debug("Found '" . $match . "' in \$nodes_available_keys" . $nodes_available_keys[$match]);
                    $nodes_to_add[] = $nodes_available_keys[$match];
                    }
                else
                    {
                    echo "Not found: '" . $match . "'\n<br/>";
                    $node_strings_not_found[]=$match;
                    }
                }
            }

        // All to be removed
        preg_match_all('/^\s*\+\s*(.*?)$/m',$log['diff'],$matches);
        if(isset($matches[1][0]))
            {
            foreach ($matches[1] as $match)
                {
                $match=trim(mb_strtolower($match));
                if(isset($nodes_available_keys[$match]))
                    {
                    debug("Found '" . $match . "' in \$nodes_available_keys" . $nodes_available_keys[$match]);
                    $nodes_to_remove[] = $nodes_available_keys[$match];
                    }
                else
                    {
                    echo "Not found: '" . $match . "'\n<br/>";
                    $node_strings_not_found[]=$match;
                    }
                }
            }
        }
    else
        {
        $current=get_data_by_field($resource,$field);
        $diff=log_diff($current,$log["previous_value"]);
        }

    # Process submit
    if (getval("revert_action","")=="revert" && enforcePostRequest(false))
        {
        if($is_fixed_field)
            {
            if(count($nodes_to_remove)>0)
                {
                delete_resource_nodes($resource,array_values($nodes_to_remove),false);
                }        
            if(count($nodes_to_add)>0)
                {
                add_resource_nodes($resource, array_values($nodes_to_add),false,false);
                }       

            # If this is a 'joined' field we need to add it to the resource column
            $joins = get_resource_table_joins();
            if(in_array($field, $joins))
                {
                // Get all options selected for this resource and field
                $resource_field_data       = get_resource_field_data($resource);
                $resource_field_data_index = array_search($field, array_column($resource_field_data, 'ref'));

                $truncated_value = null;
                if(
                    $resource_field_data_index !== false
                    && trim((string) $resource_field_data[$resource_field_data_index]["value"]) != ""
                    )
                    {
                    $new_joined_field_value = $resource_field_data[$resource_field_data_index]["value"];
                    $truncated_value = truncate_join_field_value($new_joined_field_value);
                    }
 
                if (is_null($truncated_value)) 
                    {
                    ps_query("UPDATE resource SET field{$field} = NULL WHERE ref = ?",array("i",$resource));
                    }
                else
                    {
                    ps_query("UPDATE resource SET field{$field} = ? WHERE ref = ?",array("s",$truncated_value, "i",$resource));
                    }
                }
            log_node_changes($resource,$nodes_to_add,$nodes_to_remove,$lang["revert_log_note"]);
            redirect(generateURL('pages/view.php', ['ref' => $resource]));
            }
        else
            {
            $errors=array();
            update_field($resource, $field, strip_leading_comma($log["previous_value"]),$errors,false); # Do not log as we are doing that below.
            if(count($errors) == 0)
                {
                resource_log($resource,LOG_CODE_EDITED,$field,$lang["revert_log_note"],$current,$log["previous_value"]);
                redirect(generateURL("pages/view.php",["ref"=>$resource]));
                }
            else
                {
                $onload_message = array("title" => $lang["error"],"text" => implode("<br/>",$errors));
                }
            }
        }
    }
elseif($type==LOG_CODE_UPLOADED)
    {
    # ----------------------------- PROCESSING FOR "u" IMAGE UPLOAD ROWS ---------------------------------------------
    
    # Process submit
    if (getval("revert_action","")=="revert" && enforcePostRequest(false))
        {
        # Perform the reversion.
        $revertok = revert_resource_file($resource,$log);
        if($revertok===true)
            {
            redirect("pages/view.php?ref=" . $resource);
            exit;
            }
        $error = $lang["error_upload_replace_file_fail"];        
        }
    }

include __DIR__ . "/../../../include/header.php";
?>

<div class="BasicsBox">
    <p>
        <a href="<?php echo $baseurl_short ?>pages/log.php?ref=<?php echo escape($resource) ?>"
            onClick="CentralSpaceLoad(this,true);return false;">
            <?php echo LINK_CARET_BACK . escape($lang["back"]); ?>
        </a>
    </p>

    <?php if (isset($error)) { ?>
        <div class="PageInfoMessage"><?php echo escape($error) ?></div>
    <?php } ?>

    <h1><?php echo escape($lang["revert"]); ?></h1>
    <p><?php echo escape($lang['revertingclicktoproceed']); ?></p>

    <form method=post
        name="rse_revert_form"
        id="rse_revert_form"
        action="<?php echo generateURL($baseurl_short . 'plugins/rse_version/pages/revert.php', ['ref' => $ref]); ?>"
        onSubmit="return CentralSpacePost(this,true);">

        <input type="hidden" name="ref" value="<?php echo escape($ref) ?>">
        <input type="hidden" name="revert_action" value="revert">

        <?php
        generateFormToken("form");

        if ($type == LOG_CODE_EDITED || $type == LOG_CODE_MULTI_EDITED || $type == LOG_CODE_NODE_REVERT) {
            if ($is_fixed_field) {
                if (count($nodes_to_add) > 0) { ?>
                    <div class="Question">
                        <label><?php echo escape($lang["revertingwilladd"]); ?></label>
                        <div class="Fixed">
                            <?php foreach($nodes_to_add as $node_to_add) {
                                echo escape($cattree ? $nodes_by_ref[$node_to_add]["translated_path"] : $nodes_by_ref[$node_to_add]["translated_name"]); ?>
                                <br/>
                            <?php } ?>
                        </div>
                    <div class="clearerleft"></div>
                    </div>
                <?php }

                if (count($nodes_to_remove) > 0) { ?>
                    <div class="Question">
                        <label><?php echo escape($lang["revertingwillremove"]); ?></label>
                        <div class="Fixed">
                            <?php foreach($nodes_to_remove as $node_to_remove) {
                                echo escape($cattree ? $nodes_by_ref[$node_to_remove]["translated_path"] : $nodes_by_ref[$node_to_remove]["translated_name"]); ?>
                                <br/>
                            <?php } ?>
                        </div>
                        <div class="clearerleft"></div>
                    </div>
                <?php }

                if (count($node_strings_not_found) > 0) { ?>
                    <div class="Question">
                        <label><?php echo escape($lang["revertingwillignore"]); ?></label>
                        <div class="Fixed">
                            <?php foreach($node_strings_not_found as $node_string_not_found) {
                                echo escape($node_string_not_found); ?>
                                <br/>
                            <?php } ?>
                        </div>
                        <div class="clearerleft"></div>
                    </div>
                <?php }
            } else { ?>
                <div class="Question">
                    <label><?php echo escape($lang["revertingwillapply"]); ?></label>
                    <div class="Fixed"><?php echo nl2br(escape($diff)) ?></div>
                    <div class="clearerleft"></div>
                </div>
            <?php }
        }

        if ($type == LOG_CODE_UPLOADED) {
            # Fetch the thumbnail of the image
            $alt_file = $log["previous_file_alt_ref"];
            $alt_thumb = get_resource_path($resource, true, 'thm', true, "", -1, 1, false, "", $alt_file);

            if (file_exists($alt_thumb)) { 
                $image = get_resource_path($resource, false, 'thm', true, "", -1, 1, false, "", $alt_file);
            } else {
                // If an image is not available, fetch a nopreview image based on extension   
                $alter_data = get_alternative_file($resource,$alt_file);    
                $image = $baseurl_short . 'gfx/no_preview/default.png';
            } ?>

            <div class="Question">
                <label><?php echo escape($lang["revertingwillreplace"]); ?></label>

                <div class="Fixed">
                    <img src="<?php echo $image ?>" alt="<?php echo escape($lang["preview"]); ?>" />
                </div>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="QuestionSubmit">
            <input name="revert" type="submit" value="<?php echo escape($lang["revert"]); ?>" />
        </div>

    </form>
</div>

<?php
include "../../../include/footer.php";
?>

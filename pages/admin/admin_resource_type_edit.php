<?php
include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../lib/lucide/icon_classes.php";

if (!checkperm("a")) {
    exit($lang["error-permissiondenied"]);
}

$ref                   = getval('ref', '', true);
$name                  = getval('name', '');
$config_options        = getval('config_options', '');
$allowed_extensions    = getval('allowed_extensions', '');
$tab                   = (int) getval('tab', 0);
$colour                = getval('colour', 0, true);
$push_metadata         = getval('push_metadata', 0, true);
$pull_images           = getval('pull_images', 0, true);
$icon                  = getval('icon', '');

$allrestypes           = get_resource_types('', true, true, true);

$restype_order_by = getval("restype_order_by", "rt");
$restype_sort = getval("restype_sort", "asc");

$url_params = array("ref" => $ref,
            "restype_order_by" => $restype_order_by,
            "restype_sort" => $restype_sort);
$url = generateURL($baseurl . "/pages/admin/admin_resource_type_edit.php", $url_params);

$backurl = getval("backurl", "");
if ($backurl == "") {
    $backurl = $baseurl . "/pages/admin/admin_resource_types.php?ref=" . $ref;
}

if (getval("restype_save", "") != "" && enforcePostRequest(false)) {
    # Save resource type data
    $savedata = [
        "name" => $name,
        "allowed_extensions" => $allowed_extensions,
        "tab" => $tab,
        "push_metadata" => $push_metadata,
        "pull_images" => $pull_images,
        "colour" => $colour,
        "icon" => $icon,
    ];
    if (!$execution_lockout) {
        $savedata["config_options"] = $config_options;
    }
    $saved = save_resource_type($ref, $savedata);
}

$confirm_delete = false;
$confirm_move_associated_rtf = false;
if (getval("restype_delete", "") != "" && enforcePostRequest(false)) {
    $targettype = getval("targettype", -1, true);
    $prereq_action = getval("prereq_action", "");
    $validtargets = array_column($allrestypes, "ref");

    $affectedresources = ps_array("SELECT ref value FROM resource WHERE resource_type=? AND ref>0", array("i",$ref), 0);
    $affected_rtfs = get_resource_type_fields(array($ref), "ref", "asc", "", array(), true);
    $dependentfields = [];
    foreach ($affected_rtfs as $affected_rtf) {
        if ($affected_rtf["global"] == 0 && count(explode(",", $affected_rtf["resource_types"])) == 1) {
            // Field only applies to this resource type
            $dependentfields[] = $affected_rtf["ref"];
        }
    }

    // If we have a target type, move the current resources to the new resource type
    if ($targettype > -1 && $targettype != $ref) {
        if (in_array($targettype, $validtargets) && $prereq_action == "move_affected_resources") {
            foreach ($affectedresources as $affectedresource) {
                update_resource_type($affectedresource, $targettype);
            }
            $affectedresources = [];
        }

        if (in_array($targettype, array_merge($validtargets, [0])) && $prereq_action == "move_affected_rtfs") {
            foreach ($dependentfields as $dependentfield) {
                update_resource_type_field_resource_types($dependentfield, [$targettype]);
            }
            $dependentfields = [];
        }
    }

    if (count($affectedresources) > 0) {
        // User needs to confirm a new resource type
        $confirm_delete = true;
    } elseif (count($dependentfields) > 0) {
        $confirm_move_associated_rtf = true;
    } else {
        // Safe to delete
        ps_query("DELETE from resource_type where ref=?", array("i",$ref));
        clear_query_cache("schema");
        redirect(generateURL($baseurl_short . "pages/admin/admin_resource_types.php", $url_params));
    }
}
$actions_required = ($confirm_delete || $confirm_move_associated_rtf);

# Fetch data
$restypedata = rs_get_resource_type($ref);

if (count($restypedata) == 0) {
    exit("Resource type not found.");
} // Should arrive here unless someone has an old/incorrect URL.

$restypedata = $restypedata[0];

include "../../include/header.php";

if (isset($saved) && $saved) {
    toast_notification(ToastNotificationType::Success, $lang['saved']);
} elseif (isset($saved) && !$saved) {
    toast_notification(ToastNotificationType::Error, $lang['error_generic']);
}
?>

<script src="<?php echo $baseurl_short ?>lib/chosen/chosen.jquery.min.js" type="text/javascript"></script>
<link rel="stylesheet" href="<?php echo $baseurl_short ?>lib/chosen/chosen.min.css">

<div class="BasicsBox">
    <h1><?php echo escape(i18n_get_translated($restypedata["name"])); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["resource_types_manage"],
            'href'  => $backurl
        ),
        array(
            'title' => escape(i18n_get_translated($restypedata["name"])),
            'help'  => "resourceadmin/resource-types"
        )
    );

    renderBreadcrumbs($links_trail);

    if (isset($error_text)) { ?>
        <div class="FormError"><?php echo $error_text?></div>
        <?php
    }
    ?>

    <form method=post action="<?php echo $baseurl_short?>pages/admin/admin_resource_type_edit.php?ref=<?php echo (int)$ref ?>&backurl=<?php echo urlencode($backurl) ?>" onSubmit="return CentralSpacePost(this,true);">
        <input type="hidden" name="ref" value="<?php echo urlencode($ref) ?>">
        <input type="hidden" id="restype_save" name="restype_save" value="">
        <input type="hidden" id="restype_delete" name="restype_delete" value="">

        <?php
        generateFormToken("admin_resource_type_edit");

        if ($actions_required) {
            ?>
            <div class="PageInfoMessage">
                <?php
                if ($confirm_delete) {
                    echo escape(str_replace("[resource_count]", count($affectedresources), $lang["resource_type_delete_confirmation"])) . "<br />";
                    ?>
                    <input type="hidden" name="prereq_action" value="move_affected_resources">
                    <?php
                } elseif ($confirm_move_associated_rtf) {
                    echo escape(str_replace("%COUNT", count($dependentfields), $lang["resource_type_delete_assoc_rtf_confirm"])) . "<br>";
                    ?>
                    <input type="hidden" name="prereq_action" value="move_affected_rtfs">
                    <?php
                }

                echo escape($lang["resource_type_delete_select_new"]) ;
                ?>
            </div>

            <div class="Question">  
                <label for="targettype"><?php echo escape($lang["resourcetype"]) ; ?></label>    
                <div class="tickset">
                    <div class="Inline">
                        <select name="targettype" id="targettype">
                            <option value="" selected ><?php echo escape($lang["select"]) ; ?></option>
                            <?php if ($confirm_move_associated_rtf) { ?>
                                <option value="0"><?php echo escape($lang["resourcetype-global_field"]) ; ?></option>
                                <?php
                            }

                            for ($n = 0; $n < count($allrestypes); $n++) {
                                if ($allrestypes[$n]["ref"] != $ref) { ?>
                                    <option value="<?php echo $allrestypes[$n]["ref"]; ?>"><?php echo escape(i18n_get_translated($allrestypes[$n]["name"])); ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="clearerleft"></div>
            </div>

            <div class="QuestionSubmit">        
                <input name="cancel" type="submit" value="<?php echo escape($lang["cancel"]) ?>" onClick="history.go(-1);return false;"/>
                <input name="delete" type="submit" value="<?php echo escape($lang["action-delete"]) ?>" onClick="if(confirm('<?php  echo escape($lang["confirm-deletion"]) ?>')){jQuery('#restype_delete').val('yes');this.form.submit();}else{jQuery('#restype_delete').val('');}return false;"/>
            </div>

            <?php
            exit();
        } else {
            ?>
            <div class="Question">
                <label><?php echo escape($lang["property-reference"]) ?></label>
                <div class="Fixed"><?php echo  $restypedata["ref"]; ?></div>
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label><?php echo escape($lang["property-name"]) ?></label>
                <input name="name" type="text" class="stdwidth" value="<?php echo escape((string)$restypedata["name"])?>" />
                <div class="clearerleft"></div>
            </div>

            <?php render_lucide_icon_selector($lang["property-icon"], "icon", ($restypedata['icon'] ?? "")); ?>

            <div class="Question">
                <label><?php echo escape($lang["property-allowed_extensions"]) ?></label>
                <input name="allowed_extensions" type="text" class="stdwidth" value="<?php echo escape((string)$restypedata["allowed_extensions"])?>" />

                <div class="FormHelp" style="padding:0;clear:left;" >
                    <div class="FormHelpInner"><?php echo escape($lang["information-allowed_extensions"]); ?></div>
                </div>    
                <div class="clearerleft"></div>    
            </div>

            <?php if (!$execution_lockout) { ?>
                <div class="Question">
                    <label><?php echo escape($lang["property-override_config_options"]); ?></label>
                    <textarea name="config_options" class="stdwidth" rows=5 cols=50><?php echo escape((string)$restypedata["config_options"])?></textarea>
                    <div class="FormHelp" style="padding:0;clear:left;">
                        <div class="FormHelpInner">
                            <?php echo escape($lang["information-resource_type_config_override"]); ?>
                        </div>
                    </div>
                    <div class="clearerleft"> </div>
                </div>
            <?php }

            render_dropdown_question($lang['property-tab_name'], 'tab', get_tab_name_options(), $restypedata['tab']);

            $MARKER_COLORS[-1] = $lang["select"];
            ksort($MARKER_COLORS);
            render_dropdown_question($lang['resource_type_marker_colour'], "colour", $MARKER_COLORS, $restypedata["colour"], '', array("input_class" => "stdwidth"));
            config_boolean_select("push_metadata", $lang["property-push_metadata"], (int)$restypedata["push_metadata"], '', 420, null, false, null, false, $lang["information-push_metadata"]);
            config_boolean_select("pull_images", $lang["pull_images"], (int)$restypedata["pull_images"], '', 420, null, false, null, false, $lang["pull_images_text"]);
            ?>

            <div class="QuestionSubmit">        
                <input name="save" type="submit" value="<?php  echo escape($lang["save"])?>" onClick="jQuery('#restype_save').val('yes');this.form.submit();return false;"/>
                <input name="delete" type="submit" value="<?php  echo escape($lang["action-delete"])?>" onClick="if(confirm('<?php  echo escape($lang["confirm-deletion"]) ?>')){jQuery('#restype_delete').val('yes');this.form.submit()}else{jQuery('#restype_delete').val('');}return false;"/>
            </div>
            <?php
        } // End of normal page (not confirm deletion)
        ?>
    </form>
</div><!-- End of Basics Box -->

<?php
include "../../include/footer.php";
?>

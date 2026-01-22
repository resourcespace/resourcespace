<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

$filterid = getval("filter", 0, true);

if (!checkperm("a")) {
    exit("Permission denied.");
}

$backurl = getval("backurl", "");
if ($backurl == "") {
    $filterorder    = getval("filterorder", "ref");
    $filtersort     = getval("filtersort", "ASC");
    $revsort        = ($filtersort == "ASC") ? "DESC" : "ASC";
    $filterfind     = getval("filterfind", "");
    $filter_manage_url  = $baseurl . "/pages/admin/admin_filter_manage.php";

    $params = array(
        "filterfind"    => $filterfind,
        "filtersort"    => $filtersort,
        "filterorder"   => $filterorder
        );

    $backurl = generateURL($filter_manage_url, $params);
} else {
    $backurl = urlencode($backurl);
}

$delete_filter = getval('delete_filter', '');
$delete_filter_rule = getval('delete_filter_rule', '');
$filter_rule = getval("filter_rule", "");
$filter_copy_from = getval("copy_from", 0, true);

// Process ajax requests
if ($delete_filter != "" && enforcePostRequest("admin_filter_edit")) {
    $result     = delete_filter($delete_filter);
    if ($result === true) {
        if (getval("filter_manage_page", "") != "") {
            $response   = array('deleted' => $result);
            exit(json_encode($response));
        } else {
            ?>
            <script>
            CentralSpaceLoad('<?php echo $backurl; ?>',true);
            </script>
            <?php
            exit();
        }
    } else {
        $response   = array('deleted' => false);
        $errors = array();
        $errors[] = escape($lang["filter_delete_error"]) . ":- ";
        foreach ($result["groups"] as $group) {
            $errors[] = escape($lang["group"]) . ": <a href='" . $baseurl_short . "/pages/admin/admin_group_management_edit.php?ref=" . (int) $group . "' target='_blank' >" . (int) $group . "</a>";
        }

        foreach ($result["users"] as $user) {
            $errors[] = escape($lang["user"]) . ": <a href='" . $baseurl_short . "?u=" . (int) $user . "' target='_blank' >" . (int) $user . "</a>";
        }
        if (getval("filter_manage_page", "") != "") {
            $response['errors'] = $errors;
            exit(json_encode($response));
        }
    }
}

if ($delete_filter_rule != "" && enforcePostRequest("delete_filter_rule")) {
    $result     = delete_filter_rule($delete_filter_rule);
    $response   = array('success' => $result);
    exit(json_encode($response));
} elseif ($filter_rule != "" && enforcePostRequest("filter_rule_edit")) {
    // Process saved rules
    $ruledata = getval("filter_rule_data", "");
    save_filter_rule($filter_rule, $filterid, $ruledata);
} elseif ($filterid != "" && getval("save", "") != "" && enforcePostRequest("admin_filter_edit")) {
    // Save the filter
    $filter_name = getval("filter_name", "");
    $filter_condition = getval("filter_condition", RS_FILTER_ALL, true);

    if ($filterid == 0 && $filter_copy_from != 0) {
        // Copy rules to new filter
        $newfilterid = copy_filter($filter_copy_from);
        $filterid = $newfilterid;
    } else {
        save_filter($filterid, $filter_name, $filter_condition);

        if (getval("filter_manage_page", "") == "" && !isset($errors) && empty($errors)) {
            redirect($backurl);
        }
    }
}

// Get all fields so we can resolve node field names
$allfields = get_resource_type_fields();

$filter = get_filter($filterid);
$filter_rules = get_filter_rules($filterid);

$filter_edit_url = generateURL($baseurl . "/pages/admin/admin_filter_edit.php", array("filter" => $filterid));
$rule_add_url = generateURL($baseurl . "/pages/admin/ajax/admin_filter_rule_edit.php", array("ref" => "new","filter" => $filterid));

// Convert filter so we can display it in a user friendly way
$rules = array();

foreach ($filter_rules as $fr_id => $frule) {
    foreach ($frule["nodes_on"] as $rulenode) {
        $nodeinfo = array();
        get_node($rulenode, $nodeinfo);
        if (!$nodeinfo) {
            debug("filter rule #" . $fr_id . " - node " . $rulenode . " not found ");
            // Node does not exist
            continue;
        }

        $field_index = array_search($nodeinfo["resource_type_field"], array_column($allfields, 'ref'));
        if ($field_index !== false) {
            if (!isset($rules[$fr_id]["fields"][$allfields[$field_index]["ref"]])) {
                $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["fieldname"] = i18n_get_translated($allfields[$field_index]["name"]);
                $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["values_on"] = array();
            }
            $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["values_on"][] = i18n_get_translated($nodeinfo["name"]);
        } else {
            echo "filter - node field " . $nodeinfo["resource_type_field"] . " for node:" . $rulenode . " not found ";
        }
    }

    foreach ($frule["nodes_off"] as $rulenode) {
        $nodeinfo = array();
        get_node($rulenode, $nodeinfo);
        if (!$nodeinfo) {
            debug("filter rule #" . $fr_id . " - node " . $rulenode . " not found ");
            // Node does not exist
            continue;
        }

        $field_index = array_search($nodeinfo["resource_type_field"], array_column($allfields, 'ref'));
        if ($field_index !== false) {
            if (!isset($rules[$fr_id]["fields"][$allfields[$field_index]["ref"]])) {
                $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["fieldname"] = i18n_get_translated($allfields[$field_index]["name"]);
                $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["values_off"] = array();
            }
            $rules[$fr_id]["fields"][$allfields[$field_index]["ref"]]["values_off"][] = i18n_get_translated($nodeinfo["name"]);
        } else {
            echo "filter - node field " . $nodeinfo["resource_type_field"] . " for node:" . $rulenode . " not found ";
        }
    }
}

include "../../include/header.php";
?>

<div id="CentralSpaceContainer">
    <div id="CentralSpace">
        <div class="BasicsBox">
            <h1><?php echo escape($filterid == 0 ? $lang["filter_new"] : $lang["filter_edit"]); ?></h1>
            <?php
            $links_trail = array(
                array(
                    'title' => $lang["systemsetup"],
                    'href'  => $baseurl_short . "pages/admin/admin_home.php",
                    'menu' =>  true
                ),
                array(
                    'title' => $lang["filter_manage"],
                    'href'  => $backurl
                ),
                array(
                    'title' => $filterid == 0 ? $lang["filter_new"] : $lang["filter_edit"],
                )
            );

            renderBreadcrumbs($links_trail);
            ?>
             
            <h2>
                <?php
                echo escape($lang["filter_edit_text"]);
                render_help_link("systemadmin/search-filters");
                ?>
            </h2>
            <form id="filter_edit_form" name="filter_edit_form" method="post" class="FormWide" action="<?php echo $filter_edit_url; ?>">
                <input type="hidden" name="filter" value="<?php echo escape($filterid); ?>" />
                <input type="hidden" name="save" value="true" />
                <?php generateFormToken("admin_filter_edit"); ?>
                <div class="Question" id="filter_name_question">
                    <label for="filter_name"><?php echo escape($lang["filter_name"]); ?></label>
                    <input class="stdwidth" type="text" name="filter_name" id="filter_name" value="<?php echo i18n_get_translated($filter["name"]) ?>" />
                    <div class="clearerleft"> </div>
                </div>
    
                <div class="Question" id="filter_condition_question" title="" style="height: 50px;">
                    <label><?php echo escape($lang["filter_condition_label"]); ?></label>
                    <select class="stdwidth" name="filter_condition">
                        <?php
                        foreach (array(RS_FILTER_ALL => "filter_criteria_all",RS_FILTER_NONE => "filter_criteria_none",RS_FILTER_ANY => "filter_criteria_any") as $filter_condition => $description) {
                            echo "<option value='" . escape($filter_condition) . "' " . ($filter["filter_condition"] == $filter_condition ? "selected " : "")  . ">" . escape($lang[$description]) . "</option>";
                        }?>
                    </select>  
                    <div class="clearerleft"> </div>
                </div>

                <div class="Question" id="fr_question">
                    <label for="fr_list"><?php echo escape($lang["filter_rules"]); ?></label>
                    <div id="fr_list" class="stdwidth">
                        <table class="OptionTable">
                            <?php
                            if (count($rules) == 0) {
                                echo escape($lang["filter_rules_none"]);
                            } else {
                                foreach ($rules as $ruleid => $ruleinfo) {
                                    $ruletext = array();
                                    foreach ($ruleinfo["fields"] as $rulefield) {
                                        if (isset($rulefield["values_on"]) && count($rulefield["values_on"]) > 0) {
                                            $ruletext[] = $rulefield["fieldname"] . " " . $lang["filter_is_in"] . " ('" . implode("'&nbsp;" . $lang["filter_or"] . "&nbsp;'", $rulefield["values_on"]) . "')";
                                        }
                                        if (isset($rulefield["values_off"]) && count($rulefield["values_off"]) > 0) {
                                            $ruletext[] = $rulefield["fieldname"] . " " . $lang["filter_is_not_in"] . " ('" . implode("'&nbsp;" . $lang["filter_or"] . "&nbsp;'", $rulefield["values_off"]) . "')";
                                        }
                                    }

                                    $rule_edit_url = generateURL($baseurl . "/pages/admin/ajax/admin_filter_rule_edit.php", array("ref" => $ruleid,"filter" => $filterid));
                                    echo "<tr><td><div class='keywordselected tag_inline' id='filter_rule_" . escape($ruleid) . "'> <a href={$rule_edit_url} onclick ='return ModalLoad(this,true);'>" . implode("&nbsp;" . escape($lang["filter_or"]) . "&nbsp;", $ruletext) . "</a><a href='#' onclick ='deleteFilterRule(" . escape($ruleid) . ");return false;'>[<i class='icon-x'></i>]</a></input></td></tr>";
                                }
                            }
                            ?>
                        </table>
                    </div> <!-- End of fr_list -->
                <div class="clearerleft"> </div>
                </div><!-- End of fr_question -->

                <div class="Question">
                    <input name="ruleadd" type="button" onclick="addFilterRule();"value="<?php echo escape($lang["filter_rule_add"]); ?>">
                <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label for="delete_filter"><?php echo escape($lang["action-delete"]); ?></label>
                    <input id="delete_filter" name="delete_filter" type="checkbox" value="<?php echo escape($filterid); ?>" >
                    <div class="clearerleft"></div>
                </div>
                        
                <div class="QuestionSubmit">
                    <input name="save" type="submit" value="<?php echo escape($lang["save"]);?>" onClick="return CentralSpacePost(this.form,true);">
                </div>
            </form>
         </div> <!-- End of BasicsBox -->
    </div> <!-- End of CentralSpace -->
</div> <!-- End of CentralSpaceContainer -->

<script>
function addFilterRule() {
    ModalLoad('<?php echo $rule_add_url; ?>',true,true,'left');
    ModalCentre();
    return true;
}
    
function deleteFilterRule(rule) {
    var post_data = {
        ajax: true,
        delete_filter_rule: rule,
        <?php echo generateAjaxToken("delete_filter_rule"); ?>
    };
    
    jQuery.post(window.location.href, post_data, function(response) {
        if (response.success === true) {
            jQuery('#filter_rule_' + rule).remove();
        } else {
            styledalert('<?php echo escape($lang["error"]); ?>',response);
        }
    }, 'json');

    return false;
}

jQuery(document).ready(function() {
    let errors = <?php echo isset($errors) ? json_encode($errors) : '""';?>;
    if (errors.length > 0) {
        error_message = '';
        for (var i in errors) {
            error_message += errors[i] + "<br />";
        }
        console.log(error_message);
        styledalert("<?php echo escape($lang['error']); ?>", error_message);
    }
});
</script>

<?php
include "../../include/footer.php";
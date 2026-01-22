<?php

include "../../../include/boot.php";
include "../../../include/authenticate.php";

$ruleid = getval("ref", "");
$filterid = getval("filter", 0, true);

if (!checkperm("a") || !(((string)(int)$ruleid == (string)$ruleid) || $ruleid == "new")) {
    exit($lang["error-permissiondenied"]);
}

if ($ruleid != "new") {
    $filter_rule = get_filter_rule($ruleid);
    if (!$filter_rule) {
        exit($lang["error"]);
    }
} else {
    $filter_rule = array();
}

$allfields = get_resource_type_fields();
$saveparams = array();
$saveparams["ref"] = $filterid;
$saveurl = generateURL($baseurl . "/pages/admin/admin_filter_edit.php", $saveparams);

?>

<script src="<?php echo $baseurl_short ?>lib/chosen/chosen.jquery.min.js" type="text/javascript"></script>
<link rel="stylesheet" href="<?php echo $baseurl_short ?>lib/chosen/chosen.min.css">

<div class="BasicsBox">
    <h1><?php echo escape($lang["filter_rule_add"]); ?></h1>
    <h2><?php echo escape($lang["filter_rule_edit_text"]); ?></h2>
    <form id="filter_edit_form" name="filter_edit_form" method="post" action="<?php echo $saveurl; ?>" onSubmit="processFilterRules();return CentralSpacePost(this,true);">
        <input type="hidden" name="filter_rule" value="<?php echo (int)$ruleid; ?>" />
        <input type="hidden" name="filter" value="<?php echo $filterid; ?>" />
        <input type="hidden" name="filter_rule_data" id="filter_rule_data" value="" />
        <?php generateFormToken("filter_rule_edit"); ?>

        <?php
        if ($ruleid != "new") {
            foreach ($filter_rule as $filter_line) {
                ?>
                <div class="Question filter_rule_question">
                    <select name="filter_rule_field" id="filter_rule_field" style="width:300px" onChange="updateFieldOptions(jQuery(this).parent());">
                        <option value='0' ><?php echo escape($lang["select"]); ?></option>
                        <?php
                        foreach ($allfields as $field) {
                            if (in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) { ?>
                                <option
                                    value="<?php echo $field["ref"];?>"
                                    <?php echo ($field["ref"] == $filter_line["resource_type_field"]) ? 'selected' : ''; ?>>
                                    <?php echo $field["title"]; ?>
                                </option>
                                <?php
                            }
                        }
                        ?>
                    </select>

                    <select name="filter_rule_node_condition[]" class="filter_rule_node_condition" id="filter_rule_node_condition" style="width:150px">
                        <option value="0" <?php echo ($filter_line['node_condition'] == 0) ? 'selected' : ''; ?>>
                            <?php echo escape($lang["filter_is_not_in"]); ?>
                        </option>
                        <option value="1" <?php echo ($filter_line['node_condition'] == 1) ? 'selected' : ''; ?>>
                            <?php echo escape($lang["filter_is_in"]); ?>
                        </option>
                    </select>
                    
                    <select name='filter_rule_nodes[]' class='filter_rule_nodes' multiple='multiple' size='7' style='width:420px'>
                        <?php
                        $field_options = get_field_options($filter_line["resource_type_field"], true);

                        foreach ($field_options as $option) {
                            ?>
                            <option
                                value='<?php echo $option['ref'];?>'
                                <?php if (in_array($option["ref"], explode(',', $filter_line["nodes"]))) {
                                    echo "selected";
                                } ?>>
                                <?php echo $option["name"]; ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="Question filter_rule_question">
                <select name="filter_rule_field" id="filter_rule_field" style="width:300px" onChange="updateFieldOptions(jQuery(this).parent());">
                    <option value='0' ><?php echo escape($lang["select"]); ?></option>
                    <?php
                    foreach ($allfields as $field) {
                        if (in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
                            echo "<option value='" . $field["ref"] . "' >" . $field["title"] . "</option>\n";
                        }
                    }
                    ?>
                </select>

                <select name="filter_rule_node_condition[]" class="filter_rule_node_condition" id="filter_rule_node_condition" style="width:150px">
                    <option value="0" selected ><?php echo escape($lang["filter_is_not_in"]); ?></option>
                    <option value="1" ><?php echo escape($lang["filter_is_in"]); ?></option>
                </select>
                
                <select name='filter_rule_nodes[]' class='filter_rule_nodes' multiple='multiple' size='7' style='width:420px'>
                    <option value='0' ><?php echo escape($lang["select"]); ?></option>
                </select>
                
            </div>
            <?php
        }
        ?>
        <div class="Question">
            <label for="conditionadd"></label>
            <a href="#" onclick="return addFilterRuleItem(); return false;">
                <i aria-hidden="true" class="icon-circle-plus"></i>&nbsp;Add condition
            </a>
        <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="ruleadd"></label>
            <input
                name="ruleadd"
                type="submit"
                value="<?php
                if ($ruleid != "new") {
                    echo escape($lang["filter_rule_save"]);
                } else {
                    echo escape($lang["filter_rule_add"]);
                } ?>">
            <div class="clearerleft"></div>
        </div>
    </form>
</div> <!-- End of Basicsbox -->

<script>
function addFilterRuleItem() {
    jQuery('.filter_rule_nodes').chosen('destroy');
    lastrow = jQuery('.filter_rule_question').last();
    newq = lastrow.clone();
    console.log(lastrow.attr('row'));
    newq.children('#filter_rule_field').val(0);
    newq.children('.filter_rule_node_condition').val(0);
    newq.children('.filter_rule_nodes').val('');
    lastrow.after(newq);
    jQuery('.filter_rule_nodes').chosen('');
}
    
function processFilterRules() {
    rule_elements = new Array();
    jQuery('.filter_rule_question').each(function () {
        rule_nodes = jQuery(this).children('.filter_rule_nodes').val();
        // Empty rule because no nodes selected, so skip this and move onto next filter rule in case there is one further down
        if (rule_nodes.length!=0) {
            rule_condition = jQuery(this).children('.filter_rule_node_condition').val();
            rule_element = [rule_condition,rule_nodes];
            rule_elements.push(rule_element);
        }
    });        

    jQuery ('#filter_rule_data').val(JSON.stringify(rule_elements));

    // No rules present
    if (rule_elements.length==0) {
        return false;
    }

}

function updateFieldOptions(question) {
    jQuery(question).children('.filter_rule_nodes').html('');
    jQuery('.filter_rule_nodes').chosen('destroy');
    selectedField =  jQuery(question).children('#filter_rule_field').val();

    var post_data = {
        'ref': selectedField,
        'nodeinfo':true
    };

    api("get_field_options", post_data, function(response) {
        if (response != false) {
            nodeselect =  jQuery(question).children('.filter_rule_nodes');

            jQuery.each(response, function () {
                nodeselect.append(jQuery('<option>', { 
                    value: this.ref,
                    text : this.name 
                }));
            });

            jQuery('.filter_rule_nodes').chosen();
            }
        },
        <?php echo generate_csrf_js_object('get_field_options'); ?>
    );
    jQuery('#modal').css('overflow', 'visible');
}

jQuery(document).ready(function(){
        jQuery('.filter_rule_nodes').chosen();
});
</script>
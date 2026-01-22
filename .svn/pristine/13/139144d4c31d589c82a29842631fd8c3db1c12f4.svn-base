<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$find = getval("find", "");
$offset = getval("offset", 0, true);

if (array_key_exists("find", $_POST)) {
    $offset = 0;
} # reset page counter when posting

$restypefilter = getval("restypefilter", "");
$restypesfilter = ($restypefilter != "") ? array((int)$restypefilter) : [];
$field_order_by = getval("field_order_by", "order_by");
$field_sort = getval("field_sort", "asc");
$reorder_view = getval("reorder_view", false);
$backurl = getval("backurl", "");

if ($backurl == "") {
    $backurl = $baseurl . "/pages/admin/admin_home.php";
}

$allow_reorder = false;
// Allow sorting if we are ordering metadata fields for all resource types (ie Resource type == "All" and $restypefilter=="")
if ($restypefilter == "" && $reorder_view) {
    $allow_reorder = true;
}

include "../../include/header.php";

$url_params = array("restypefilter" => $restypefilter,
            "field_order_by" => $field_order_by,
            "field_sort" => $field_sort,
            "find" => $find);
$url = generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params);

// Common ResourceSpace URL params are used as an override when calling {@see generateURL()}
$common_rs_url_params = [
    'backurl' => $url,
];

if (getval("newfield", "") != "" && enforcePostRequest(false)) {
    $newfieldname = getval("newfield", "");
    $newfieldtype = getval("field_type", 0, true);
    $newfieldrestype = getval("newfieldrestype", 0, true);
    $new = create_resource_type_field($newfieldname, $newfieldrestype, $newfieldtype, "", true);
    redirect($baseurl_short . 'pages/admin/admin_resource_type_field_edit.php?ref=' . $new . '&newfield=true');
}

function addColumnHeader($orderName, $labelKey)
{
    global $baseurl, $group, $field_order_by, $field_sort, $find, $lang, $restypefilter, $url_params;

    if ($field_order_by == $orderName && $field_sort == "asc") {
        $arrow = '<span class="DESC"></span>';
    } elseif ($field_order_by == $orderName && $field_sort == "desc") {
        $arrow = '<span class="ASC"></span>';
    } else {
        $arrow = '';
    }

    $newparams = array();
    $newparams["field_order_by"] = $orderName;
    $newparams["field_sort"] = ($field_sort == "desc" || $field_order_by == "order_by") ? 'asc' : 'desc';

    ?>
    <th>
        <a
            href="<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params, $newparams); ?>"
            onClick="return CentralSpaceLoad(this);">
            <?php echo escape($lang[$labelKey]) . $arrow ?>
        </a>
    </th>
    <?php
}

$links_trail = array(
    array(
        'title' => $lang["systemsetup"],
        'href'  => $baseurl_short . "pages/admin/admin_home.php",
        'menu' =>  true
    ),
    array(
        'title' => $lang["admin_resource_type_fields"],
        'help'  => "resourceadmin/configure-metadata-field"
    )
);
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["admin_resource_type_fields"]); ?></h1>

    <?php
    renderBreadcrumbs($links_trail);

    $introtext = text("introtext");
    if ($introtext != "") {
        echo "<p>" . escape($introtext) . "</p>";
    }

    $fields = get_resource_type_fields($restypesfilter, $field_order_by, $field_sort, $find, array(), true);

    if (!empty($restypesfilter) && !in_array(0, $restypesfilter)) {
        // Don't show global fields as a specific resource type has been selected
        $fields = array_values(array_filter($fields, function ($field) {
            return $field["global"] != 1;
        }));
    }

    $resource_types = get_resource_types();
    $arr_restypes = array_column($resource_types, "name", "ref");

    $results = count($fields);
    ?>

    <div class="FormError" id="PageError"
        <?php if (!isset($error_text)) { ?>
            style="display:none;">
            <?php
        } else {
            echo ">" . $error_text ;
        } ?>
    </div>

    <?php
    if ($allow_reorder) {
        ?>
        <p><?php echo escape($lang["admin_resource_type_field_reorder_information"]); ?></p>   
        <?php
    } elseif ($restypefilter == "") {
        ?>
        <a
            href="<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params, array("restypefilter" => (($use_order_by_tab_view) ? "" : $restypefilter),"field_order_by" => "order_by","fieldsort" => "asc","reorder_view" => "true")); ?>"
            onClick="return CentralSpaceLoad(this,true);">
            <?php
            echo LINK_CARET;
            if ($use_order_by_tab_view) {
                echo escape($lang["admin_resource_type_field_reorder_mode_all"]);
            } else {
                echo escape($lang["admin_resource_type_field_reorder_mode"]);
            }
            ?>
        </a>
        <?php
    } else {
        ?>
        <p><?php echo escape($lang["admin_resource_type_field_reorder_select_restype"]); ?></p>   
        <?php
    }
    ?>

    <form method="post" id="AdminResourceTypeFieldForm" onSubmit="return CentralSpacePost(this,true);" action="<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params); ?>" >
        <?php generateFormToken("AdminResourceTypeFieldForm"); ?>       
        <div class="Question">
            <label for="restypefilter"><?php echo escape($lang["property-resource_type"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <select name="restypefilter" id="restypefilter" onChange="return CentralSpacePost(this.form,true);">
                        <option value="" <?php echo ($restypefilter == "") ? " selected" : ''; ?>>
                            <?php echo escape($lang["all"]); ?>
                        </option>
                        <option value="0" <?php echo ($restypefilter == "0") ? " selected" : ''; ?>>
                            <?php echo escape($lang["resourcetype-global_field"]); ?>
                        </option>
                        
                        <?php for ($n = 0; $n < count($resource_types); $n++) { ?>
                            <option
                                value="<?php echo $resource_types[$n]["ref"]; ?>"
                                <?php echo ($restypefilter == $resource_types[$n]["ref"]) ? " selected" : ''; ?>>
                                <?php echo i18n_get_translated($resource_types[$n]["name"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
        
    <div class="Listview">
        <table id="resource_type_field_table" class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <?php
                $system_tabs = get_tab_name_options();

                addColumnHeader('ref', 'property-reference');
                addColumnHeader('title', 'property-title');

                if ($metadata_admin_show_shorthand)
                {
                    addColumnHeader('name', 'property-shorthand_name');
                }
                addColumnHeader('type', 'property-field_type');
                addColumnHeader('resource_type', 'resourcetypes');
                addColumnHeader('exiftool_field', 'property-exiftool_field');

                if (!hook('replacetabnamecolumnheader')) {
                    addColumnHeader('tab', 'property-tab_name');
                }
                ?>
                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                </th>
            </tr>

            <tbody id="resource_type_field_table_body">
                <?php for ($n = 0; $n < count($fields); $n++) { ?>
                    <tr
                        class="resource_type_field_row <?php echo ($fields[$n]["active"] == 0) ? "FieldDisabled" : ''; ?>"
                        id="field_sort_<?php echo $fields[$n]["ref"];?>">
                        <td>
                            <?php echo escape($fields[$n]["ref"]); ?>
                        </td>   
                        <td>
                            <div class="ListTitle">
                                <a
                                    href="<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_field_edit.php", $url_params, array("ref" => $fields[$n]["ref"],"backurl" => $url)); ?>"
                                    onClick="jQuery('#resource_type_field_table_body').sortable('cancel');return CentralSpaceLoad(this,true);">
                                    <span><?php echo escape(i18n_get_translated($fields[$n]["title"])); ?></span>
                                </a>
                            </div>
                        </td>
                        <?php if ($metadata_admin_show_shorthand) { ?>
                        <td>
                            <?php echo escape($fields[$n]["name"]); ?>
                        </td>
                        <?php } ?>
                        <td>
                            <?php
                            // If no field value is set it is treated as type 0 (single line text)
                            echo escape($fields[$n]["type"] != "" ? $lang[$field_types[$fields[$n]["type"]]] : $lang[$field_types[0]]);
                            ?>
                        </td>

                        <?php
                        # Resolve resource type names
                        if ((bool)$fields[$n]["global"] == 1) {
                            $restypestring = $lang["resourcetype-global_field"];
                        } else {
                            $fieldrestypes = explode(",", (string)$fields[$n]["resource_types"]);
                            $restypestring = implode(", ", array_intersect_key($arr_restypes, array_flip($fieldrestypes)));
                        } ?>

                        <td title="<?php echo escape($restypestring); ?>">
                            <?php echo escape(tidy_trim($restypestring, 30)); ?>
                        </td>

                        <td>
                            <?php echo str_replace(",","<br />",escape($fields[$n]['exiftool_field'] ?? '')); ?>
                        </td>

                        <?php if (!hook('replacetabnamecolumn')) { ?>
                            <td>
                                <?php echo escape($system_tabs[(int) $fields[$n]['tab']] ?? ''); ?>
                            </td>
                        <?php } ?>



                        <td>
                            <div class="ListTools">
                                <?php if ($field_order_by == "order_by" && $allow_reorder) { ?>      
                                    <a href="javascript:void(0)" class="movelink movedownlink" <?php echo ($n == count($fields) - 1) ? " disabled" : ''; ?>>
                                        <i class="icon-arrow-down"></i>&nbsp;<?php echo escape($lang['action-move-down']); ?>
                                    </a>
                                    <a href="javascript:void(0)" class="movelink moveuplink" <?php echo ($n == 0) ? " disabled" : ''; ?>>
                                        <i class="icon-arrow-up"></i>&nbsp;<?php echo escape($lang['action-move-up']); ?>
                                    </a>
                                <?php } ?>
                            
                                <a href="<?php echo generateURL("{$baseurl}/pages/admin/admin_copy_field.php", ['ref' => $fields[$n]["ref"]], $common_rs_url_params); ?>" onClick="CentralSpaceLoad(this,true)">
                                    <i class="icon-copy"></i>&nbsp;<?php echo escape($lang["copy"]); ?>
                                </a>
                                <a href="<?php echo generateURL("{$baseurl}/pages/admin/admin_resource_type_field_edit.php", ['ref' => $fields[$n]["ref"]], $common_rs_url_params); ?>" onClick="jQuery('#resource_type_field_table_body').sortable('cancel');return CentralSpaceLoad(this,true);">
                                    <i class="icon-square-pen"></i>&nbsp;&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                </a>
                                <a href="<?php echo generateURL("{$baseurl}/pages/admin/admin_system_log.php", ['table' => 'resource_type_field', 'table_reference' => $fields[$n]['ref']], $common_rs_url_params); ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-history"></i>&nbsp;<?php echo escape($lang["log"]); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <form method="post" id="AdminResourceTypeFieldForm2" onSubmit="return CentralSpacePost(this,true);" action="<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params); ?>" >
        <?php generateFormToken("AdminResourceTypeFieldForm2"); ?>
        <div class="Question">
            <label for="find"><?php echo escape($lang["find"]); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input type=text name="find" id="find" value="<?php echo escape($find)?>" maxlength="100" class="shrtwidth" />
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["searchbutton"]); ?>" />
                </div>
                <?php if ($find != "") { ?>
                    <div class="Inline">
                        <input
                            name="resetform"
                            class="resetform"
                            type="submit"
                            value="<?php echo escape($lang["clearbutton"]); ?>"
                            onclick="CentralSpaceLoad('<?php echo generateURL($baseurl . "/pages/admin/admin_resource_type_fields.php", $url_params, array("find" => "")); ?>',false);return false;"
                        />
                    </div>
                <?php } ?>
            </div>
            <div class="clearerleft"></div>
        </div>
        
        <div class="Question">
            <label for="newfield"><?php echo escape($lang["admin_resource_type_field_create"]); ?></label>
            <div class="tickset">
                <input type="hidden" name="newfieldrestype" value="<?php echo escape($restypefilter) ?>"/>   
                <div class="Inline">
                    <input type=text name="newfield" id="newtype" maxlength="100" class="shrtwidth" />
                </div>
                <div class="Inline">
                    <select name="field_type" id="new_field_type_select" class="medwidth">
                        <?php foreach ($field_types as $field_type => $field_type_description) { ?>
                            <option value="<?php echo $field_type ?>"><?php echo escape($lang[$field_type_description]) ; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="Inline">
                    <input name="Submit" type="submit" value="<?php echo escape($lang["create"]); ?>" />
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div><!-- End of BasicsBox -->

<script>
    function ReorderResourceTypeFields(idsInOrder) {
        var newOrder = [];
        jQuery.each(idsInOrder, function() {
            newOrder.push(this.substring(11));
        }); 
        
        jQuery.ajax({
            type: 'POST',
            url: '<?php echo generateURL($baseurl_short . "pages/admin/ajax/update_resource_type_field_order.php", $url_params, array("reorder" => "true")); ?>',
            data: {
            order: JSON.stringify(newOrder),
            <?php echo generateAjaxToken('reorder_resource_type_fields');?>
            },
            success: function() {
                jQuery('.movedownlink:last').prop( "disabled", true);
                jQuery('.moveuplink:first').prop( "disabled", true);
                jQuery('.movedownlink:not(:last)').prop( "disabled",false);
                jQuery('.moveuplink:not(:first)').prop( "disabled", false);
            }
        });       
    }

    function enableFieldsort(){
        var fixHelperModified = function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                jQuery(this).width($originals.eq(index).width())
            });
            return $helper;
        };
        
        jQuery('#resource_type_field_table_body').sortable({
            items: "tr",
            axis: "y",
            cursor: 'move',
            opacity: 0.6, 
            distance: 20,
            stop: function(event, ui) {
                <?php
                if ($allow_reorder) {
                    ?>
                    var idsInOrder = jQuery('#resource_type_field_table_body').sortable("toArray");
                    ReorderResourceTypeFields(idsInOrder);
                    <?php
                } else {
                    if ($use_order_by_tab_view && $restypefilter != "") {
                        $errormessage = $lang["admin_resource_type_field_reorder_information_tab_order"];
                    } elseif (!$use_order_by_tab_view && $restypefilter == "" && $field_order_by == "order_by") {
                        $errormessage = $lang["admin_resource_type_field_reorder_select_restype"];
                        ?>
                        hideinfo = true;
                        <?php
                    } else {
                        $errormessage = $lang["admin_resource_type_field_reorder_information_normal_order"];
                    }
                    ?>
                    
                    jQuery('#PageError').html("<?php echo $errormessage ?>");
                    jQuery('#PageError').show();
                    if (hideinfo !== undefined) {
                        jQuery('#PageInfo').hide();                    
                    }

                    jQuery("#resource_type_field_table_body").sortable("cancel");
                    <?php
                }
                ?>
            },
            helper: fixHelperModified
            
        }).disableSelection();
    }

    <?php if ($allow_reorder) { ?>
        enableFieldsort();
    <?php } ?>

    jQuery(".moveuplink").click(function() {
        if (jQuery(this).prop('disabled')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        curvalue = parseInt(jQuery(this).parents(".resource_type_field_row").children('.order_by_value').html());
        parentvalue = parseInt(jQuery(this).parents(".resource_type_field_row").prev().children('.order_by_value').html());

        jQuery(this).parents(".resource_type_field_row").children('.order_by_value').html(curvalue - 10);
        jQuery(this).parents(".resource_type_field_row").prev().children('.order_by_value').html(parentvalue + 10);
        jQuery(this).parents(".resource_type_field_row").insertBefore(jQuery(this).parents(".resource_type_field_row").prev());

        var idsInOrder = jQuery('#resource_type_field_table_body').sortable("toArray");
        ReorderResourceTypeFields(idsInOrder);
    });
    
    jQuery(".movedownlink").click(function() {
        if (jQuery(this).prop('disabled')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        curvalue = parseInt(jQuery(this).parents(".resource_type_field_row").children('.order_by_value').html());
        childvalue = parseInt(jQuery(this).parents(".resource_type_field_row").next().children('.order_by_value').html());

        jQuery(this).parents(".resource_type_field_row").children('.order_by_value').html(curvalue + 10);
        jQuery(this).parents(".resource_type_field_row").next().children('.order_by_value').html(childvalue - 10);
        jQuery(this).parents(".resource_type_field_row").insertAfter(jQuery(this).parents(".resource_type_field_row").next());

        var idsInOrder = jQuery('#resource_type_field_table_body').sortable("toArray");
        ReorderResourceTypeFields(idsInOrder);
    });
</script>
    
<?php
include "../../include/footer.php";
?>

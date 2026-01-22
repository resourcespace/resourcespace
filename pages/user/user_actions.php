<?php

include "../../include/boot.php";

include_once "../../include/request_functions.php";
include_once "../../include/action_functions.php";
include "../../include/authenticate.php";

if (!$actions_on) {
    exit("access denied");
}
$modal = getval("modal", "") == "true";

$actiontypes = array();
$actiontypes[] = "resourcereview";
if ($actions_account_requests && checkperm("u")) {
    $actiontypes[] = "userrequest";
}
if ($actions_resource_requests && checkperm("R")) {
    $actiontypes[] = "resourcerequest";
}
$updatedactiontypes = hook("updateactiontypes", '', array($actiontypes));
if (is_array($updatedactiontypes)) {
    $actiontypes = $updatedactiontypes;
}
$actiontype = getval("actiontype", ''); // Set to ascertain if we are filtering on type
$offset = getval("offset", 0, true);
$order_by = getval("actions_order_by", "date");
$sort = getval("actions_sort", "DESC", false, 'validate_sort_value');
$valid_order_bys = array("date","ref","description","type","user","usergroup");

if (!in_array($order_by, $valid_order_bys)) {
    $order_by = "date";
    $sort = "DESC";
}
$revsort = ($sort == "ASC") ? "DESC" : "ASC";

$all_actions = get_user_actions(false, $actiontype, $order_by, $sort);

# pager
$jumpcount = 1;
$per_page = getval("per_page_list", $default_perpage_list);
$results = count($all_actions);
$totalpages = ceil($results / $per_page);
$curpage = floor($offset / $per_page) + 1;
$totalpages = ceil($results / $per_page);

$url_params = array(
    "offset" => $offset,
    "actions_order_by" => $order_by,
    "actions_sort" => $sort,
    "actiontype" => $actiontype,
    "per_page_list" => $per_page,
    "paging" => true
   );

$url = generateURL($baseurl . "/pages/user/user_actions.php", $url_params);

include "../../include/header.php";

?>
<div class="BasicsBox">
    <h1><?php echo escape($lang["actions_myactions"])?></h1>
    <p><?php echo escape($lang["actions_introtext"]) ?></p>
    <div class="VerticalNav">
        <ul>
            <?php if ($user_preferences) { ?>
                <li>
                    <a
                        href="<?php echo $baseurl_short?>pages/user/user_preferences.php"
                        onClick="return CentralSpaceLoad(this,true);">
                        <?php echo LINK_CARET . "&nbsp;" . escape($lang["userpreferences"]); ?>
                    </a>
                </li>
                <?php
            }

            // Make sure all states are unchecked if they had the deprecated option $actions_resource_review set to false.
            // Also forcibly set it to true so they can still change preferences;
            if (isset($actions_resource_review) && !$actions_resource_review) {
                $actions_notify_states = "";
            }

            if (trim($actions_notify_states) != "") {
                $rtypes = get_resource_types();
                $searchable_restypes = implode(",", array_diff(array_column($rtypes, "ref"), explode(",", $actions_resource_types_hide)));
                $add_editable_resources_url = $baseurl_short . "pages/collections.php?addsearch=&mode=resources&restypes=" . $searchable_restypes . "&archive=" . $actions_notify_states . "&foredit=true&order_by=date";
                $search_url = $baseurl_short . "pages/search.php?search=&restypes=" . $searchable_restypes . "&archive=" . $actions_notify_states . "&foredit=true";
                ?>
                <li>
                    <a href="#" onclick="CollectionDivLoad('<?php echo $add_editable_resources_url?>'); return false;">
                        <?php echo LINK_CARET . "&nbsp;" . escape($lang['actions_add_editable_to_collection']); ?>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="CentralSpaceLoad('<?php echo $search_url?>'); return false;">
                        <?php echo LINK_CARET . "&nbsp;" . escape($lang['actions_view_editable_as_resultset']); ?>
                    </a>
                </li>
                <?php
            } ?>
        </ul>
    </div><!-- End of VerticalNav -->
</div><!-- End of BasicsBox -->

<?php if (count($actiontypes) > 1) { ?>
    <div class="BasicsBox">
        <form id="FilterActions" class="FormFilter" method="post" action="<?php echo $url ?>">
            <?php generateFormToken("FilterActions"); ?>
            <fieldset>
                <legend><?php echo escape($lang['filter_label']); ?></legend>  
                <div class="tickset">
                    <div class="Inline">
                        <select name="actiontype" id="actiontype" onChange="this.form.submit();">
                            <option
                                value=""
                                <?php if ($actiontype == '') {
                                    echo " selected";
                                } ?>>
                                <?php echo escape($lang["all"]); ?>
                            </option>
                            <?php foreach ($actiontypes as $action_type_option) { ?>
                                <option
                                    value="<?php echo $action_type_option; ?>"
                                    <?php if ($actiontype == $action_type_option) {
                                        echo " selected";
                                    } ?>>
                                    <?php echo escape($lang["actions_type_" . $action_type_option]); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </fieldset>
        </form>
    </div>
    <div class="clearerleft"></div>
    <?php
} else {
    ?>
    <div class="spacer"></div>
    <?php
}
?>
<div class="TopInpageNav">
    <div class="TopInpageNavLeft">
        <div class="InpageNavLeftBlock"><?php echo escape($lang["actions-total"]) . ": <strong>" . $results; ?> </strong></div>
        <div class="InpageNavLeftBlock"><?php echo escape($lang["resultsdisplay"])?>:
            <?php
            for ($n = 0; $n < count($list_display_array); $n++) {
                if ($per_page == $list_display_array[$n]) { ?>
                    <span class="Selected"><?php echo $list_display_array[$n]; ?></span>
                    <?php
                } else { ?>
                    <a
                        href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("per_page_list" => $list_display_array[$n])) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo $list_display_array[$n]; ?>
                    </a>
                    <?php
                } ?>&nbsp;|
                <?php
            }

            if ($per_page == 99999) { ?>
                <span class="Selected"><?php echo escape($lang["all"])?></span>
                <?php
            } else { ?>
                <a
                    href="<?php echo $url; ?>&per_page_list=99999"
                    onClick="return CentralSpaceLoad(this);">
                    <?php echo escape($lang["all"])?>
                </a>
                <?php
            } ?>
        </div>
    </div><?php pager(false); ?>
    <div class="clearerleft"></div>
</div>
<div class="clearerleft"> </div>

<div class="BasicsBox">
    <div class="Listview" id="<?php echo $modal ? "Modal" : "CentralSpace"; ?>_resource_actions">
        <table class="ListviewStyle">
            <tr class="ListviewTitleStyle">
                <th>
                    <?php if ($order_by == "date") { ?>
                        <span class="Selected">
                        <?php
                    } ?>
                    <a
                        href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "date")) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["date"]); ?>
                    </a>
                </th>
              
                <th>
                    <?php if ($order_by == "name") { ?>
                        <span class="Selected">
                        <?php
                    } ?>
                    <a
                        href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "user")) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["user"]); ?>
                    </a>
                </th>

                <?php if ($messages_actions_usergroup) { ?>
                    <th>
                        <?php if ($order_by == "usergroup") { ?>
                            <span class="Selected">
                            <?php
                        } ?>
                        <a
                            href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "user")) ?>"
                            onClick="return CentralSpaceLoad(this);">
                            <?php echo escape($lang["columnheader-user_group"]); ?>
                        </a>
                    </th>
                    <?php
                } ?>

                <th>
                    <?php if ($order_by == "ref") { ?>
                        <span class="Selected">
                        <?php
                    } ?>
                    <a
                        href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "ref")) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["property-reference"]); ?>
                    </a>
                </th>

                <th>
                    <?php if ($order_by == "description") { ?>
                        <span class="Selected">
                        <?php
                    } ?>
                    <a
                        href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "description")) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["description"]); ?>
                    </a>
                </th>

                <th>
                    <?php if ($order_by == "type") { ?>
                        <span class="Selected">
                        <?php
                    } ?>
                    <a href="<?php echo generateURL($baseurl . "/pages/user/user_actions.php", $url_params, array("offset" => 0,"actions_sort" => urlencode($revsort),"actions_order_by" => "type")) ?>"
                        onClick="return CentralSpaceLoad(this);">
                        <?php echo escape($lang["type"]); ?>
                    </a>
                </th>

                <th>
                    <div class="ListTools"><?php echo escape($lang["tools"])?></div>
                </th>
            </tr>

            <?php
            if ($results == 0) {
                echo "<tr><td style='padding:20px;'>" . escape($lang['actions_noactions']) . "</td></tr>";
            } else {
                for ($n = $offset; (($n < $results) && ($n < ($offset + $per_page))); $n++) {
                    $actionlinks = hook("actioneditlink", '', array($all_actions[$n]));
                    if ($actionlinks) {
                        $actioneditlink = $actionlinks["editlink"];
                        $actionviewlink = $actionlinks["viewlink"];
                        $actionaddlink = isset($actionlinks["addlink"]) ? $actionlinks["addlink"] : "";
                    } else {
                        $actioneditlink = '';
                        $actionviewlink = '';
                        $actionaddlink = '';
                    }

                    if ($all_actions[$n]["type"] == "resourcereview") {
                        $actioneditlink = $baseurl_short . "pages/edit.php";
                        $actionviewlink = $baseurl_short . "pages/view.php";
                        $actionaddlink = add_to_collection_link($all_actions[$n]["ref"], '', '', 'icon-circle-plus');
                    } elseif ($all_actions[$n]["type"] == "resourcerequest") {
                        $actioneditlink = $baseurl_short . "pages/team/team_request_edit.php";
                    } elseif ($all_actions[$n]["type"] == "userrequest") {
                        $actioneditlink = $baseurl_short  . "pages/team/team_user_edit.php";
                    }

                    $linkparams["ref"] = $all_actions[$n]["ref"];
                    $linkparams["disablenav"] = "true";

                    $editlink = ($actioneditlink == '') ? '' : generateURL($actioneditlink, $linkparams);
                    $viewlink = ($actionviewlink == '') ? '' : generateURL($actionviewlink, $linkparams);
                    ?>
                    <tr>
                        <td><?php echo nicedate($all_actions[$n]["date"], true, true, true); ?></td>
                        <?php echo "<td>" . strip_tags_and_attributes(tidy_trim(TidyList($all_actions[$n]["user"]), $list_search_results_title_trim)) . "</td>";
                        if ($messages_actions_usergroup) {
                            echo "<td>" . $all_actions[$n]["usergroup"] . "</td>";
                        } ?> 
                        <td>
                            <a
                                href="<?php echo $editlink; ?>"
                                onClick="actionreload=true;return <?php echo $actions_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);" >
                                <?php echo $all_actions[$n]["ref"]; ?>
                            </a>
                        </td>
                        <td><?php echo strip_tags_and_attributes(tidy_trim(TidyList($all_actions[$n]["description"]), $list_search_results_title_trim)) ; ?></td>
                        <td><?php echo escape($lang["actions_type_" . $all_actions[$n]["type"]]); ?></td>
                        <td>
                            <div class="ListTools">
                                <?php
                                if ($actionaddlink != "") {
                                    echo $actionaddlink;
                                }
                                if ($editlink != "") { ?>
                                    <a
                                        aria-hidden="true"
                                        href="<?php echo $editlink; ?>"
                                        onClick="actionsreload=true;return <?php echo $actions_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);"
                                        title="<?php echo escape($lang["action-edit"]); ?>">
                                        <i class="icon-square-pen"></i>&nbsp;<?php echo escape($lang["action-edit"]); ?>
                                    </a>
                                    <?php
                                }
                                if ($viewlink != "") { ?>
                                    <a
                                        aria-hidden="true"
                                        href="<?php echo $viewlink; ?>"
                                        onClick="actionsreload=true;return <?php echo $actions_modal ? 'Modal' : 'CentralSpace'; ?>Load(this,true);"
                                        title="<?php echo escape($lang["view"]); ?>">
                                        <i class="icon-maximize-2"></i>&nbsp;<?php echo escape($lang["view"]); ?>
                                    </a>
                                    <?php
                                } ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                } // End of $all_actions loop
            }
            ?>
        </table>
    </div>
</div> <!-- End of BasicsBox -->

<script>
actionsreload=false;
jQuery('#CentralSpace').on('ModalClosed',function(e,url){
    if(ajaxinprogress!=true && typeof(actionsreload)!=="undefined" && actionsreload==true && window.location.href.indexOf('/pages/user/user_actions.php')!=-1){
            actionsreload=false;
            CentralSpaceLoad(window.location.href,false, null, false);
        }
    });     
</script>
<?php
include "../../include/footer.php";

<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkPermission_dashadmin()) {
    exit($lang["error-permissiondenied"]);
}

include "../../include/dash_functions.php";

if (checkperm('h') && checkperm('hdt_ug')) {
    $user_groups = array(ucfirst($lang['all_users']));
    $user_groups += get_usergroups(false, '', true);
} else {
    global $usergroup;
    $user_groups = array_filter(get_usergroups(false, '', true), fn($k) => $usergroup == $k, ARRAY_FILTER_USE_KEY);
}

// Get selected user group or default to all users dash tiles
$selected_user_group = getval('selected_user_group', key($user_groups), true);

$show_usergroups_dash = ('true' == getval('show_usergroups_dash', '') ? true : false);
if ($selected_user_group == 0) {
    $show_usergroups_dash = false;
}

if (getval("quicksave", false)) {
    $tile = getval("tile", "");
    $revokeallusers = getval("revokeallusers", "false") != "false";

    #If a valid tile value supplied
    if (!empty($tile) && is_numeric($tile)) {
        #Tile available to this user?
        $all_user_available   = get_alluser_available_tiles($tile);
        $user_group_available = array();

        if ($show_usergroups_dash) {
            $user_group_available = get_usergroup_available_tiles($selected_user_group, $tile);
        }

        $available = array_merge($all_user_available, $user_group_available);

        if (!empty($available)) {
            $tile = $available[0];
            $active = all_user_dash_tile_active($tile["ref"]);
            if ($active) {
                if ($revokeallusers) {
                    revoke_all_users_flag_cascade_delete($tile['ref']);
                } else {
                    #Delete if the tile is active
                    #Check config tiles for permanent deletion
                    $force = false;
                    $search_string = explode('?', $tile["url"]);
                    parse_str(str_replace("&amp;", "&", $search_string[1]), $search_string);
                    if ($search_string["tltype"] == "conf") {
                        $force = !checkTileConfig($tile, $search_string["tlstyle"]);
                    }
                    delete_dash_tile($tile["ref"], true, $force);
                    log_activity($lang['manage_all_dash'], LOG_CODE_DELETED, $tile["title"], 'dash_tile', null, $tile["ref"]);
                }
                reorder_default_dash();
                $dtiles_available = get_alluser_available_tiles();
                exit("negativeglow");
            } else {
                #Add to the front of the pile if the user already has the tile
                ps_query("DELETE FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile["ref"]]);
                ps_query("INSERT user_dash_tile (user,dash_tile,order_by) SELECT user.ref, ?,5 FROM user", ['i', $tile['ref']]);

                $dtiles_available = get_alluser_available_tiles();
                exit("positiveglow");
            }
        }
    }
    exit("Save Failed");
}

include "../../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["manage_dash_tiles"]); ?></h1>
    
    <?php
    $links_trail = array(
    array(
        'title' => $lang["teamcentre"],
        'href'  => $baseurl_short . "pages/team/team_home.php",
        'menu' =>  true
    ),
    array(
        'title' => $lang["manage_dash_tiles"],
        'help'  => "user/manage-dash-tile"
    )
    );

    renderBreadcrumbs($links_trail);

    $href = "{$baseurl_short}pages/team/team_dash_tile.php";
    if ($show_usergroups_dash) {
        $href .= "?show_usergroups_dash=true&selected_user_group={$selected_user_group}";
    }
    ?>

    <p>
        <a href="<?php echo $href; ?>" onClick="return CentralSpaceLoad(this, true);">
            <?php echo LINK_CARET . escape($lang['view_tiles']); ?>
        </a>
    </p>

    <?php if (!$show_usergroups_dash) { ?>
        <p>
            <a href="<?php echo $baseurl_short?>pages/team/team_dash_tile_special.php" onClick="return CentralSpaceLoad(this,true);">
                <?php echo LINK_CARET . escape($lang['specialdashtiles']); ?>
            </a>
        </p>
        <?php
    }

    render_dropdown_question(
        $lang['property-user_group'],
        'select_user_group',
        $user_groups,
        $selected_user_group,
        "onchange=\"CentralSpaceLoad('{$baseurl_short}pages/team/team_dash_admin.php?show_usergroups_dash=true&selected_user_group=' + jQuery(this[this.selectedIndex]).val(), true);\""
    );
    ?>

    <form class="Listview">
        <input type="hidden" name="submit" value="true" />
        <table class="ListviewStyle">
            <thead>
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang["dashtileshow"]); ?></th>
                    <th><?php echo escape($lang["dashtiletitle"]); ?></th>
                    <th><?php echo escape($lang["dashtiletext"]); ?></th>
                    <th><?php echo escape($lang["dashtilelink"]); ?></th>
                    <th><?php echo escape($lang["showresourcecount"]); ?></th>
                    <th><?php echo escape($lang["tools"]); ?></th>
                </tr>
            </thead>
            <tbody id="dashtilelist">
                <?php
                if ($show_usergroups_dash) {
                    $dtiles_available = get_usergroup_available_tiles($selected_user_group);
                } else {
                    $dtiles_available = get_alluser_available_tiles();
                }
                build_dash_tile_list($dtiles_available);
                ?>
            </tbody>
        </table>
        <div id="confirm_dialog" style="display:none;text-align:left;"></div>
        <div id="delete_permanent_dialog" style="display:none;text-align:left;">
            <?php echo escape($lang['confirmdeleteconfigtile']);?>
        </div>
    </form>

    <style>
    .ListviewStyle tr.positiveglow td,.ListviewStyle tr.positiveglow:hover td{background: rgba(45, 154, 0, 0.38);}
    .ListviewStyle tr.negativeglow td,.ListviewStyle tr.negativeglow:hover td{  background: rgba(227, 73, 75, 0.38);}
    </style>

    <script type="text/javascript">
        function processTileChange(tile,revoke_all_users) {
            if (revoke_all_users === undefined) {
                revoke_all_users = false;
            }

            jQuery.post(
                window.location, {
                    "tile": tile,
                    "quicksave": "true",
                    "revokeallusers": revoke_all_users,
                    <?php echo generateAjaxToken("processTileChange"); ?>
                },
                function(data) {
                    jQuery("#tile"+tile).removeClass("positiveglow");
                    jQuery("#tile"+tile).removeClass("negativeglow");
                    jQuery("#tile"+tile).addClass(data);
                    window.setTimeout(function(){jQuery("#tile"+tile).removeClass(data);},2000);
                }
            );
        }

        function changeTile(tile,all_users) {
            if (!jQuery("#tile"+tile+" .tilecheck").prop("checked")) {
                if (jQuery("#tile"+tile).hasClass("conftile")) {
                    jQuery("#delete_permanent_dialog").dialog({
                        title:'<?php echo escape($lang["dashtiledelete"]); ?>',
                        modal: true,
                        resizable: false,
                        dialogClass: 'delete-dialog no-close',
                        buttons: {
                            "<?php echo escape($lang['confirmdefaultdashtiledelete']); ?>": function() {
                                jQuery(this).dialog("close");
                                deleteDefaultDashTile(tile);
                            },    
                            "<?php echo escape($lang['cancel']); ?>": function() {
                                jQuery(".tilecheck[value="+tile+"]").attr('checked', true);
                                jQuery(this).dialog('close');
                            }
                        }
                    });
                    return;
                } else {
                    jQuery("#confirm_dialog").dialog({
                    title:'<?php echo escape($lang["dashtiledelete"]); ?>',
                    modal: true,
                    resizable: false,
                    dialogClass: 'confirm-dialog no-close',
                    buttons: {
                        "<?php echo escape($lang['confirmdefaultdashtiledelete']); ?>": function() {processTileChange(tile,true); jQuery(this).dialog( "close" );},
                        "<?php echo escape($lang['cancel']); ?>":  function() { jQuery(".tilecheck[value="+tile+"]").prop('checked', true); jQuery(this).dialog('close'); }
                    }
                    });
                }
            } else {
                processTileChange(tile);
            }
        }

        function deleteDefaultDashTile(tileid) {
            jQuery.post(
                "<?php echo $baseurl; ?>/pages/ajax/dash_tile.php",
                {
                    "tile": tileid,
                    "delete": "true",
                    <?php echo generateAjaxToken("deleteDefaultDashTile"); ?>
                }
            );
        }
    </script>
</div>
<?php
include "../../include/footer.php";
?>

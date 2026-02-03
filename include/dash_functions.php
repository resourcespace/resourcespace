<?php
/*
 * Dash Functions - Jethro, Montala Ltd
 * Functions for the homepage dash tiles
 *
 */

/**
 * returns a ref for an existing dash tile in dash_tile table, returns 0 if no existing dash tile
 *
 * @param   string      $url
 * @param   string      $link
 * @param   string      $title
 * @param   string      $text
 * @param   integer     $reload_interval
 * @param   integer     $all_users
 * @param   integer     $resource_count
 *
 * @return  integer
 */
function existing_dash_tile(string $url = "", string $link = "", string $title = "", string $text = "", int $reload_interval = 0, int $all_users = 0, int $resource_count = 0)
{
    $existing_tile_ref = ps_value(
        "SELECT ref as `value` FROM dash_tile WHERE url=? AND link=? AND title=? AND txt=? AND reload_interval_secs=? AND all_users=? AND resource_count=?",
        array("s",$url,"s",$link,"s",$title,"s",$text,"i",$reload_interval,"i",$all_users,"i",$resource_count),
        0
    );
    return (int) $existing_tile_ref;
}

/*
 * Create a dash tile template
 * @$all_users,
 *	If passed true will push the tile out to all users in your installation
 *  If passed false you must give this tile to a user with sql_insert_id() to have it used
 *
 */
function create_dash_tile($url, $link, $title, $reload_interval, $all_users, $default_order_by, $resource_count, $text = "", $delete = 1, array $specific_user_groups = array())
{
    $rebuild_order = true;

    # Validate Parameters
    if (empty($reload_interval) || !is_numeric($reload_interval)) {
        $reload_interval = 0;
    }

    $delete    = $delete ? 1 : 0;
    $all_users = $all_users ? 1 : 0;

    if (!is_numeric($default_order_by)) {
        $default_order_by = append_default_position();
        $rebuild_order = false;
    }

    $resource_count = $resource_count ? 1 : 0;
    $existing_tile_ref = existing_dash_tile($url, $link, $title, $text, (int) $reload_interval, $all_users, $resource_count);

    if ($existing_tile_ref > 0) {
        $tile = $existing_tile_ref;
        $rebuild_order = false;
    } else {
        ps_query(
            "INSERT INTO dash_tile
                (url,
                    link,
                    title,
                    reload_interval_secs,
                    all_users,
                    default_order_by,
                    resource_count,
                    allow_delete,txt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
            's', $url,
            's', $link,
            's', $title,
            'i', $reload_interval,
            'i', $all_users,
            'i', $default_order_by,
            'i', $resource_count,
            'i', $delete,
            's', $text
            ]
        );
        $tile = sql_insert_id();

        foreach ($specific_user_groups as $user_group_id) {
            add_usergroup_dash_tile($user_group_id, $tile, $default_order_by);
            build_usergroup_dash($user_group_id, 0, $tile);
        }
    }

    # If tile already existed then this no reorder
    if ($rebuild_order) {
        reorder_default_dash();
    }

    if ($all_users == 1 && empty($specific_user_groups)) {
        ps_query("DELETE FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile]);
        ps_query("INSERT user_dash_tile (user,dash_tile,order_by) SELECT user.ref,?,5 FROM user", ['i', $tile]);
    }

    hook('after_create_dash_tile', '', array($tile));

    return $tile;
}

/*
 * Update Dash tile based upon ref
 * This updates the record in the dash_tile table
 * If the all_user flag is being changed it will only get pushed out to users not removed. That action is specifically upon delete not edit as this is a flag
 */
function update_dash_tile($tile, $url, $link, $title, $reload_interval, $all_users, $tile_audience, $current_specific_user_groups, $specific_user_groups, $default_order_by, $resource_count, $text = "", $delete = 1)
{
    global $userref;

    if (!is_array($tile)) {
        $tile = get_tile($tile);
    }

    #Sensible Defaults for insertion to Database
    if (empty($reload_interval) || !is_numeric($reload_interval)) {
        $reload_interval = 0;
    }

    $delete = $delete ? 1 : 0;
    $all_users = $all_users ? 1 : 0;

    if (!is_numeric($default_order_by)) {
        $default_order_by = $tile["default_order_by"];
    }

    $resource_count = $resource_count ? 1 : 0;

    ps_query(
        "UPDATE dash_tile SET url= ?, link= ?, title= ?, reload_interval_secs= ?, all_users= ?, default_order_by= ?, resource_count= ?, allow_delete= ?, txt= ? WHERE ref= ?",
        [
            's', $url,
            's', $link,
            's', $title,
            'i', $reload_interval,
            'i', $all_users,
            'i', $default_order_by,
            'i', $resource_count,
            'i', $delete,
            's', $text,
            'i', $tile['ref']
            ]
    );

    if ($tile_audience == 'true') { // All users tile
        // Check if this was a specific usergroup tile
        if (count($current_specific_user_groups) > 0 || $tile["all_users"] == 0) {
            #Delete the users existing record to ensure they don't get a duplicate.
            ps_query("DELETE FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile['ref']]);
            ps_query("INSERT user_dash_tile (user,dash_tile,order_by) SELECT user.ref, ?,5 FROM user", ['i', $tile['ref']]);
        }

        // This is an all users dash tile, delete any existing usergroup entries
        ps_query("DELETE FROM usergroup_dash_tile WHERE dash_tile = ?", ['i', $tile['ref']]);
    } elseif ($tile_audience == 'specific_user_groups') { // Specific usergroups tile
        // This is a usergroup specific dash tile
        // As is not meant for a specific user group, remove it from the users immediately
        if (count($current_specific_user_groups) == 0) {
            // This was an all users/usergroup dash tile, delete any existing user entries
            ps_query("DELETE FROM user_dash_tile WHERE dash_tile = ?", ['i', $tile['ref']]);
        }

        // Remove tile from old user groups
        foreach (array_diff($current_specific_user_groups, $specific_user_groups) as $remove_group) {
            delete_usergroup_dash_tile($tile['ref'], $remove_group);
        }

        // Newly selected user groups.
        foreach (array_diff($specific_user_groups, $current_specific_user_groups) as $add_group) {
            add_usergroup_dash_tile($add_group, $tile['ref'], $default_order_by);
            build_usergroup_dash($add_group, 0, $tile['ref']);
        }
    } else // Tile is now just for the current user
        {
        // This was an all users/usergroup dash tile, delete any existing user entries and add just for this user
        ps_query("DELETE FROM usergroup_dash_tile WHERE dash_tile = ?", ['i', $tile['ref']]);
        ps_query("DELETE FROM user_dash_tile WHERE dash_tile = ?", ['i', $tile['ref']]);
        add_user_dash_tile($userref, $tile['ref'], $default_order_by);
    }

    hook('after_update_dash_tile');
}

/*
 * Delete a dash tile
 * @$tile, the dash_tile.ref number of the tile to be deleted
 * @$cascade, whether this delete should remove the tile from all users.
 */
function delete_dash_tile($tile, $cascade = true, $force = false)
{
    #Force Delete ignores the allow_delete flag (This allows removal of config tiles)
    $allow_delete = $force ? "" : "AND allow_delete=1";
    ps_query("DELETE FROM dash_tile WHERE ref= ? " . $allow_delete, ['i', $tile]);

    if ($cascade) {
        ps_query("DELETE FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile]);
        ps_query("DELETE FROM usergroup_dash_tile WHERE dash_tile = ?", ['i', $tile]);
    }

    hook('after_delete_dash_tile', '', array($tile, $cascade , $force));
}

/*
 * Turn off push to all users "all_users" flag and cascade delete any existing entries users might have
 * @$tile, the dash_tile.ref number of the tile to be hidden from all users
 */
function revoke_all_users_flag_cascade_delete($tile)
{
    ps_query("UPDATE dash_tile SET `all_users`=0 WHERE `ref`= ?", ['i', $tile]);
    ps_query("DELETE FROM `user_dash_tile` WHERE `dash_tile`= ?", ['i', $tile]);
}

/*
 * Returns the position to append a tile to the default dash order
 */
function append_default_position()
{
    $last_tile = ps_query("SELECT default_order_by from dash_tile order by default_order_by DESC LIMIT 1");
    return isset($last_tile[0]["default_order_by"]) ? $last_tile[0]["default_order_by"] + 10 : 10;
}

/*
 * Reorders the default dash,
 * this is useful when you have just inserted a new tile or moved a tile and need to reorder them with the proper 10 gaps
 * Tiles should be ordered with values 10,20,30,40,50,60,70 for easy insertion
 */
function reorder_default_dash()
{
    $tiles = ps_query("SELECT ref FROM dash_tile WHERE all_users=1 ORDER BY default_order_by");
    $order_by = 10 * count($tiles);

    for ($i = count($tiles) - 1; $i >= 0; $i--) {
        update_default_dash_tile_order($tiles[$i]["ref"], $order_by);
        $order_by -= 10;
    }
}

/*
 * Simple updates a particular dash_tile with the new order_by.
 * this does NOT apply to a users dash, that must done with the user_dash functions.
 */
function update_default_dash_tile_order($tile, $order_by)
{
    return ps_query("UPDATE dash_tile SET default_order_by= ? WHERE ref= ?", ['i', $order_by, 'i', $tile]);
}

/*
 * Gets the full content from a tile record row
 *
 */
function get_tile($tile)
{
    $result = ps_query("SELECT ref, title, txt, all_users, default_order_by, url, link, reload_interval_secs, resource_count, allow_delete FROM dash_tile WHERE ref= ?", ['i', $tile]);
    return isset($result[0]) ? $result[0] : false;
}

/*
 * Checks if an all_user tile is currently in use and therefore active for all_users
 * Pass the dash_tile.ref of tile to check
 */
function all_user_dash_tile_active($tile)
{
    return ps_query(
        "SELECT
            dash_tile.ref AS 'tile',
            dash_tile.title,
            dash_tile.url,
            dash_tile.reload_interval_secs,
            dash_tile.link,
            dash_tile.default_order_by as 'order_by',
            dash_tile.allow_delete 
        FROM dash_tile 
        WHERE 
            dash_tile.all_users=1 
            AND
            dash_tile.ref= ?
            AND 
            (
                dash_tile.allow_delete=1 
                OR 
                (
                    dash_tile.allow_delete=0 
                    AND 
                    dash_tile.ref IN (SELECT DISTINCT user_dash_tile.dash_tile FROM user_dash_tile)
                )
            ) ORDER BY default_order_by
        ",
        ['i', $tile]
    );
}

/*
 * Checks if a tile already exists.
 * This is based upon  a complete set of values so unless all values match exactly it will return false.
 *
 */
function existing_tile($title, $all_users, $url, $link, $reload_interval, $resource_count, $text = "")
{
    $existing = ps_query(
        "SELECT ref FROM dash_tile WHERE url= ? AND link= ? AND title= ? AND reload_interval_secs= ? AND all_users= ? AND resource_count= ? AND txt= ?",
        [
            's', $url,
            's', $link,
            's', $title,
            'i', $reload_interval,
            'i', $all_users,
            'i', $resource_count,
            's', $text
        ]
    );

    return isset($existing[0]["ref"]);
}

/*
 * Cleanup Duplicate and Loose Tiles
 * This removes all unused tiles that are flagged as:
 * "allowed to delete"
 * AND not "all users"
 */
function cleanup_dash_tiles()
{
    global $lang;
    $tiles = ps_query(
        "SELECT ref, title, txt, all_users, default_order_by, url, link, reload_interval_secs, resource_count, allow_delete FROM dash_tile 
        WHERE allow_delete = 1
        AND all_users = 0
        AND ref NOT IN (SELECT DISTINCT dash_tile FROM user_dash_tile)
        AND ref NOT IN (SELECT DISTINCT dash_tile FROM usergroup_dash_tile)"
    );

    ps_query(
        "DELETE FROM dash_tile 
        WHERE allow_delete = 1
        AND all_users = 0
        AND ref NOT IN (SELECT DISTINCT dash_tile FROM user_dash_tile)
        AND ref NOT IN (SELECT DISTINCT dash_tile FROM usergroup_dash_tile)"
    );

    foreach ($tiles as $tile) {
        log_activity($lang['manage_all_dash'], LOG_CODE_DELETED, $tile["title"], 'dash_tile', null, $tile["ref"]);
    }
}

/*
 * Checks if this tiles config is still active
 * @param: $tile = tile record
 * @param: $tilestyle = extracted tilestyle of this config tile
 */
function checkTileConfig($tile, $tile_style)
{
    #Returns whether the config is still on for these tiles
    switch ($tile_style) {
        case "thmsl":
            global $home_themeheaders;
            return $home_themeheaders;
        case "custm":
            global $custom_home_panels;
            return isset($custom_home_panels) ? checkConfigCustomHomePanels($tile, $tile_style) : false;
    }
}

/*
 * Checks the configuration for each custom tile.
 * If the config for the tile is still there then return true
 */
function checkConfigCustomHomePanels($tile, $tile_style)
{
    global $custom_home_panels;
    $tile_config_set = false;
    for ($n = 0; $n < count($custom_home_panels); $n++) {
        if (existing_tile($tile["title"], $tile["all_users"], $tile["url"], $tile["link"], $tile["reload_interval_secs"], $tile["resource_count"], $tile["txt"])) {
            $tile_config_set = true;
        }
    }
    return $tile_config_set;
}

/*
 * All dash tiles available to all_users
 * If you provide a dash_tile ref it will check if this tile exists within the list of available tiles
 *
 */
function get_alluser_available_tiles($tile = "null")
{
    if (is_numeric($tile)) {
        $tilecheck = 'AND ref = ?';
        $params = ['i', $tile];
    } else {
        $tilecheck = '';
        $params = [];
    }

    return ps_query(
        "SELECT 
            dash_tile.ref,
            dash_tile.ref as 'tile',
            dash_tile.title,
            dash_tile.txt,
            dash_tile.link,
            dash_tile.url,
            dash_tile.reload_interval_secs,
            dash_tile.resource_count,
            dash_tile.all_users,
            dash_tile.allow_delete,
            dash_tile.default_order_by,
            dash_tile.default_order_by AS `order_by`, # needed for get_default_dash()
            (IF(ref IN (select distinct dash_tile FROM user_dash_tile),1,0)) as 'dash_tile'
        FROM
            dash_tile
        WHERE
            dash_tile.all_users=1 
        " . $tilecheck . "
            AND ref NOT IN (SELECT dash_tile FROM usergroup_dash_tile)
        ORDER BY 
        dash_tile,
        default_order_by
        ",
        $params
    );
}

/*
 * Retrieves the default dash which only display all_user tiles.
 * This should only be accessible to thos with Dash Tile Admin permissions
 */
function get_default_dash($user_group_id = null, $edit_mode = false)
{
    global $baseurl,$baseurl_short,$lang,$anonymous_login,$username;

    #Build Tile Templates
    $tiles = ps_query(
        "SELECT
                dash_tile.ref AS 'tile',
                dash_tile.title,
                dash_tile.url,
                dash_tile.reload_interval_secs,
                dash_tile.link,
                dash_tile.default_order_by AS 'order_by',
                dash_tile.allow_delete
            FROM dash_tile
            WHERE dash_tile.all_users = 1
            AND dash_tile.ref NOT IN (SELECT dash_tile FROM usergroup_dash_tile)
            AND (dash_tile.allow_delete=1 OR (dash_tile.allow_delete=0 AND dash_tile.ref IN (SELECT DISTINCT user_dash_tile.dash_tile FROM user_dash_tile)))
            ORDER BY default_order_by"
    );

    // In edit_mode, as a super admin, we want to see all user dash tiles otherwise re-ordering will be broken
    // due to tiles that are not visible but still being taken into account
    $hidden_tiles = array();
    $hidden_tile_class = '';

    if ($edit_mode) {
        $managed_tiles = $tiles;
        $tiles = get_alluser_available_tiles();
        $hidden_tile_class  = ' HiddenTile';

        foreach ($tiles as $all_user_available_tile) {
            if (false === array_search($all_user_available_tile['ref'], array_column($managed_tiles, 'tile'))) {
                $hidden_tiles[] = $all_user_available_tile['ref'];
            }
        }
    }

    if (!is_null($user_group_id)) {
        $tiles = get_usergroup_available_tiles($user_group_id);
    }

    $order = 10;

    if (count($tiles) == 0) {
        echo escape($lang["nodashtilefound"]);
        exit;
    }

    foreach ($tiles as $tile) {
        $contents_tile_class = '';

        if (($order != $tile["order_by"] || ($tile["order_by"] % 10) > 0) && is_null($user_group_id)) {
            update_default_dash_tile_order($tile["tile"], $order);
        } elseif ((!isset($tile['default_order_by']) || $order != $tile['default_order_by'] || ($tile['default_order_by'] % 10) > 0) && !is_null($user_group_id)) {
            update_usergroup_dash_tile_order($user_group_id, $tile['tile'], $order);
        }

        $order += 10;

        $tile_custom_style = '';
        $buildstring = explode('?', $tile['url']);
        parse_str(str_replace('&amp;', '&', ($buildstring[1] ?? "")), $buildstring);

        if (isset($buildstring['tltype']) && allow_tile_colour_change($buildstring['tltype']) && isset($buildstring['tlstylecolour'])) {
            $tile_custom_style .= get_tile_custom_style($buildstring);
        }

        if (in_array($tile['tile'], $hidden_tiles)) {
            $contents_tile_class .= $hidden_tile_class;
        }
        ?>
        <a 
            <?php
            # Check link for external or internal
            if (mb_strtolower(substr($tile["link"], 0, 4)) == "http") {
                $link = $tile["link"];
                $newtab = true;
            } else {
                $link = $baseurl . "/" . escape($tile["link"]);
                $newtab = false;
            }
            ?>
            href="<?php echo $link?>" <?php echo $newtab ? "target='_blank'" : "";?>
            onclick="if(dragging){dragging=false;return false;}" 
            class="HomePanel DashTile DashTileDraggable <?php echo $tile["allow_delete"] ? "" : "conftile";?>" 
            id="tile<?php echo escape($tile["tile"]);?>"
        >
            <div id="contents_tile<?php echo escape($tile["tile"]);?>" class="HomePanelIN HomePanelDynamicDash <?php echo $contents_tile_class; ?>" style="<?php echo $tile_custom_style; ?>">
                <?php
                if (strpos($tile["url"], "dash_tile.php") !== false) {
                    # Only pre-render the title if using a "standard" tile and therefore we know the H2 will be in the target data.
                    ?>
                    <h2 class="title"><?php echo escape($tile["title"]);?></h2>
                    <?php
                }
                ?>
                <p>Loading...</p>
                <script>
                    height = jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").height();
                    width = jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").width();
                    jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").load("<?php echo $baseurl . "/" . $tile["url"] . "&tile=" . escape($tile["tile"]);?>&tlwidth="+width+"&tlheight="+height);
                </script>
            </div>
        </a>
        <?php
    }

    render_trash("dash_tile", $lang['confirmdeleteconfigtile']);
    ?>
    <script>
        function deleteDefaultDashTile(id) {
            jQuery.post(
                "<?php echo $baseurl?>/pages/ajax/dash_tile.php",
                {
                    "tile": id,
                    "delete": "true",
                    <?php echo generateAjaxToken("deleteDefaultDashTile"); ?>
                },
                function(data) {
                    jQuery("#tile"+id).remove();
                });
            }

        function updateDashTileOrder(index,tile) {
            jQuery.post(
                "<?php echo $baseurl?>/pages/ajax/dash_tile.php",
                {
                    "tile": tile,
                    "new_index": ((index*10))<?php echo !is_null($user_group_id) ? ", \"selected_user_group\": {$user_group_id}" : ''; ?>,
                    <?php echo generateAjaxToken("updateDashTileOrder"); ?>
                }
            );
        }

        var dragging = false;

        jQuery(function() {
            if (is_touch_device()) {
                jQuery("#HomePanelContainer").prepend("<p><?php echo escape($lang["dashtilesmalldevice"]);?></p>");
                return false;
            }

            jQuery("#HomePanelContainer").sortable({
                distance: 20,
                items: ".DashTileDraggable",
                start: function(event,ui) {
                    jQuery("#dash_tile_bin").show();
                    dragging=true;
                },
                stop: function(event,ui) {
                    jQuery("#dash_tile_bin").hide();
                },
                update: function(event, ui) {
                    nonDraggableTiles = jQuery(".HomePanel").length - jQuery(".DashTileDraggable").length;
                    newIndex = (ui.item.index() - nonDraggableTiles) + 1;
                    var id = jQuery(ui.item).attr("id").replace("tile","");
                    updateDashTileOrder(newIndex,id);
                }
            });

            jQuery("#dash_tile_bin").droppable({
                accept: ".DashTileDraggable",
                activeClass: "ui-state-hover",
                hoverClass: "ui-state-active",
                drop: function(event,ui) {
                    var id = jQuery(ui.draggable).attr("id");
                    id = id.replace("tile","");
                    title = jQuery(ui.draggable).find(".title").html();

                    jQuery("#dash_tile_bin").hide();

                    if (jQuery("#tile" + id).hasClass("conftile")) {
                        jQuery("#delete_permanent_dialog").dialog({
                            title:'<?php echo escape($lang["dashtiledelete"]); ?>',
                            modal: true,
                            resizable: false,
                            dialogClass: 'delete-dialog no-close',
                            buttons: {
                                "<?php echo escape($lang['confirmdefaultdashtiledelete']); ?>": function() {
                                    jQuery(this).dialog("close");
                                    deleteDefaultDashTile(id);
                                },    
                                "<?php echo escape($lang['cancel']); ?>": function() { 
                                    jQuery(this).dialog('close');
                                }
                            }
                        });
                        return;
                    }

                    jQuery("#trash_bin_delete_dialog").dialog({
                        title:'<?php echo escape($lang["dashtiledelete"]); ?>',
                        modal: true,
                        resizable: false,
                        dialogClass: 'delete-dialog no-close',
                        buttons: {
                            "<?php echo escape($lang['confirmdefaultdashtiledelete']); ?>": function() {jQuery(this).dialog("close");deleteDefaultDashTile(id); },    
                            "<?php echo escape($lang['cancel']); ?>": function() { jQuery(this).dialog('close'); }
                        }
                    });
                }
            });
        });
    </script>
    <div class="clearerleft"></div>
    <?php
}

/*
 * Shows only tiles that are marked for all_users (and displayed on a user dash if they are a legacy tile).
 * No controls to modify or reorder (See $managed_home_dash config option)
 */
function get_managed_dash()
{
    global $baseurl,$baseurl_short,$lang,$anonymous_login,$username, $userref, $usergroup;
    global $managed_home_dash, $help_modal;

    #Build Tile Templates
    $tiles = ps_query(
        "SELECT dash_tile.ref AS 'tile',
                dash_tile.title,
                dash_tile.url,
                dash_tile.reload_interval_secs,
                dash_tile.link,
                dash_tile.default_order_by,
                (
                    SELECT ugdt.default_order_by
                        FROM usergroup_dash_tile AS ugdt
                        WHERE ugdt.dash_tile = dash_tile.ref
                        AND ugdt.usergroup = ?
                ) AS 'usergroup_default_order_by'
            FROM dash_tile
            WHERE dash_tile.all_users = 1
            AND (dash_tile.ref IN (SELECT dash_tile FROM usergroup_dash_tile WHERE usergroup_dash_tile.usergroup = ?)
            OR dash_tile.ref NOT IN (SELECT distinct dash_tile FROM usergroup_dash_tile))
            AND (
                dash_tile.allow_delete = 1
                OR (
                    dash_tile.allow_delete = 0
                    AND dash_tile.ref IN (SELECT DISTINCT user_dash_tile.dash_tile FROM user_dash_tile)
                    )
                )
        ORDER BY usergroup_default_order_by ASC, default_order_by ASC
    ",
        ['i', $usergroup, 'i', $usergroup]
    );

    foreach ($tiles as $tile) {
        $tile_custom_style = '';
        $buildstring = explode('?', $tile['url']);
        list($url_page, $buildstring) = $buildstring;
        parse_str(str_replace('&amp;', '&', $buildstring), $buildstring);
        if (isset($buildstring['tltype']) && allow_tile_colour_change($buildstring['tltype']) && isset($buildstring['tlstylecolour'])) {
            $tile_custom_style .= get_tile_custom_style($buildstring);
        }
        ?>
        <a 
            <?php
            # Check link for external or internal
            if ('http' == mb_strtolower(substr($tile['link'], 0, 4))) {
                $link   = parse_dashtile_link($tile['link']);
                $newtab = true;
            } else {
                $link   = $baseurl . '/' . escape(parse_dashtile_link($tile['link']));
                $newtab = false;
            }
            ?>
            href="<?php echo $link?>" <?php echo $newtab ? "target='_blank'" : "";?>
            onClick="<?php echo !$newtab ? 'return ' . ($help_modal && strpos($link, 'pages/help.php') !== false ? 'ModalLoad(this,true);' : 'CentralSpaceLoad(this,true);') : ''; ?>"
            <?php
            # Check if tile is set to double width
            $tlsize = (isset($buildstring['tlsize']) ? $buildstring['tlsize'] : '');
            ?>
            class="HomePanel DashTile DashTileDraggable <?php echo 'double' == $tlsize ? 'DoubleWidthDashTile' : ''; ?>" 
            id="tile<?php echo escape($tile["tile"]);?>"
        >
            <div id="contents_tile<?php echo escape($tile["tile"]);?>" class="HomePanelIN HomePanelDynamicDash" style="<?php echo $tile_custom_style; ?>">
                <?php if (strpos($tile["url"], "dash_tile.php") !== false) {
                    # Only pre-render the title if using a "standard" tile and therefore we know the H2 will be in the target data.
                    ?>
                    <h2 class="title"><?php echo escape($tile["title"]);?></h2>
                    <?php
                } ?>
                <p>Loading...</p>
                <script>
                    height = jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").height();
                    width = jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").width();
                    jQuery("#contents_tile<?php echo escape($tile["tile"]);?>").load("<?php echo generateURL($baseurl . '/' . $url_page, array_merge($buildstring, ['tile' => $tile['tile']]));?>&tlwidth="+width+"&tlheight="+height);
                </script>
            </div>
        </a>
        <?php
    }
    ?>
    <div class="clearerleft"></div>
    <?php
}

/*
 * User Group dash functions
 */

/*
 * Add a tile for a user group
 *
 */
function add_usergroup_dash_tile($usergroup, $tile, $default_order_by)
{
    if (!is_numeric($usergroup) || !is_numeric($tile)) {
        return false;
    }

    $reorder = true;

    if (!is_numeric($default_order_by)) {
        $default_order_by = append_usergroup_position($usergroup);
        $reorder          = false;
    }

    $existing = ps_query(
        "SELECT ref, usergroup, dash_tile, default_order_by, order_by
            FROM usergroup_dash_tile
            WHERE usergroup = ? AND dash_tile = ?",
        ['i', $usergroup, 'i', $tile]
    );

    if (!$existing) {
        ps_query(
            "INSERT INTO usergroup_dash_tile (usergroup, dash_tile, default_order_by)
            VALUES (?, ?, ?)",
            ['i', $usergroup, 'i', $tile, 'i', $default_order_by]
        );
    } else {
        return $existing;
    }

    if ($reorder) {
        reorder_usergroup_dash($usergroup);
    }

    return true;
}

/*
 * Get the position for a new tile at the end of the current usergroup tiles.
 * Returns the last position or the first position if no tiles found for this usergroup
 */
function append_usergroup_position($usergroup)
{
    $last_tile = ps_query("SELECT order_by FROM usergroup_dash_tile WHERE usergroup = ? ORDER BY default_order_by DESC LIMIT 1", ['i', $usergroup]);

    return isset($last_tile[0]['default_order_by']) ? $last_tile[0]['order_by'] + 10 : 10;
}

/**
 * Reorder dashboard tiles for a specific user group.
 *
 * This function retrieves all dashboard tiles assigned to a user group and updates each tile's order in increments of 10.
 *
 * @param int $usergroup The user group ID for which dashboard tiles should be reordered.
 * @return void
 */
function reorder_usergroup_dash($usergroup)
{
    $usergroup_tiles = ps_query(
        "SELECT usergroup_dash_tile.dash_tile
        FROM usergroup_dash_tile
        LEFT JOIN dash_tile
        ON usergroup_dash_tile.dash_tile = dash_tile.ref
        WHERE usergroup_dash_tile.usergroup = ?
        ORDER BY usergroup_dash_tile.default_order_by",
        ['i', $usergroup]
    );

    $order_by = 10 * count($usergroup_tiles);

    for ($i = count($usergroup_tiles) - 1; $i >= 0; $i--) {
        update_usergroup_dash_tile_order($usergroup, $usergroup_tiles[$i]['dash_tile'], $order_by);
        $order_by -= 10;
    }
}

/**
 * Update the display order of a specific dashboard tile for a user group.
 *
 * @param int $usergroup The user group ID to which the dashboard tile belongs.
 * @param int $tile The ID of the dashboard tile to update.
 * @param int $default_order_by The new default order position for the tile within the user group's dashboard.
 * @return void
 */
function update_usergroup_dash_tile_order($usergroup, $tile, $default_order_by)
{
    ps_query(
        "UPDATE usergroup_dash_tile
        SET default_order_by = ?
        WHERE usergroup = ?
        AND dash_tile = ?",
        ['i', $default_order_by, 'i', $usergroup, 'i', $tile]
    );
}

/**
* build_usergroup_dash - rebuild the usergroup tiles for either a specific user or all users.
* If a specific tile is passed e.g. if called from create_dash_tile then we just add it to the end
*
* @param    integer $user_group     ID of group to add tile(s) to
* @param    integer $user_id        ID of individual user to add tile(s) to
* @param    integer $newtileid      ID of a single tile to add on the end
*
* @return void
*/

function build_usergroup_dash($user_group, $user_id = 0, $newtileid = "")
{
    if ($newtileid != "" && is_numeric($newtileid)) {
        $user_group_tiles = array($newtileid);
    } else {
        $user_group_tiles = ps_array(
            "SELECT 
                dash_tile.ref AS `value`
            FROM
                usergroup_dash_tile
                    JOIN
                dash_tile ON usergroup_dash_tile.dash_tile = dash_tile.ref
            WHERE
                usergroup_dash_tile.usergroup = ?
            AND dash_tile.all_users = 1
            AND (dash_tile.allow_delete = 1
            OR (dash_tile.allow_delete = 0
            AND dash_tile.ref IN (SELECT DISTINCT
                user_dash_tile.dash_tile
            FROM
                user_dash_tile)))
            ORDER BY usergroup_dash_tile.default_order_by;",
            array("i",$user_group)
        );
    }

    // If client code has specified a user ID, then just add the tiles for it
    if (is_numeric($user_id) && 0 < $user_id) {
        $starting_order = 99999;

        foreach ($user_group_tiles as $tile) {
            add_user_dash_tile($user_id, $tile, $starting_order, false); // No need to reorder as we have already set the position
            $starting_order += 10;
        }

        return;
    }

    $user_list = ps_array("SELECT ref AS `value` FROM user WHERE usergroup = ?", array("i",$user_group));
    foreach ($user_list as $user) {
        $starting_order  = 99999;
        foreach ($user_group_tiles as $tile) {
            add_user_dash_tile($user, $tile, $starting_order, false); // No need to reorder as we have already set the position
            $starting_order += 10;
        }
    }
}

/**
 * Retrieve user group IDs associated with a specific dashboard tile.
 *
 * This function fetches the IDs of all user groups that have access to a given dashboard tile.
 * Each user group ID is returned as a value in the resulting array.
 *
 * @param int $tile_id The ID of the dashboard tile for which to retrieve associated user groups.
 * @return array An array of user group IDs that are linked to the specified dashboard tile.
 */
function get_tile_user_groups($tile_id)
{
    return ps_array("SELECT usergroup AS `value` FROM usergroup_dash_tile WHERE dash_tile = ?", array("i",$tile_id));
}


/**
 * Retrieve dashboard tiles available to a specific user group.
 *
 * This function returns the tiles that are accessible by a particular user group, optionally filtered by a specific tile ID.
 * The function ensures that the user group ID is numeric and fetches the tiles that are either available to all users
 * or specifically assigned to the provided user group.
 *
 * @param int $user_group_id The ID of the user group for which to retrieve available tiles.
 * @param int|string $tile (optional) Specific tile ID to filter by; if omitted, all available tiles for the user group are returned.
 * @return array An array of associative arrays, each representing a dashboard tile with keys:
 *               - 'ref' (int): Unique reference ID of the tile.
 *               - 'tile' (int): Same as 'ref', included for compatibility.
 *               - 'title' (string): The title of the tile.
 *               - 'txt' (string): Text content of the tile.
 *               - 'link' (string): Link associated with the tile.
 *               - 'url' (string): URL of an external resource, if any.
 *               - 'reload_interval_secs' (int): Time interval for reloading the tile content in seconds.
 *               - 'resource_count' (int): Resource count, if applicable.
 *               - 'all_users' (int): Flag indicating if the tile is available to all users.
 *               - 'allow_delete' (int): Flag indicating if the tile can be deleted.
 *               - 'default_order_by' (int): Default order for the tile.
 *               - 'order_by' (int|null): Order for the tile within the user group; may be null if not explicitly set.
 *               - 'dash_tile' (int): Always set to 1, indicating it is a dashboard tile.
 *
 * @throws \Exception If $user_group_id is not a numeric value.
 */
function get_usergroup_available_tiles($user_group_id, $tile = '')
{
    if (!is_numeric($user_group_id)) {
        trigger_error('$user_group_id has to be a number');
    }

    $tile_sql = '';
    $params = [];

    if ('' != $tile) {
        $tile_sql = "AND dt.ref = ?";
        $params = ['i', $tile];
    }

    $params[] = 'i';
    $params[] = $user_group_id;

    return ps_query(
        "SELECT
            dt.ref,
            dt.ref AS `tile`,
            dt.title,
            dt.txt,
            dt.link,
            dt.url,
            dt.reload_interval_secs,
            dt.resource_count,
            dt.all_users,
            dt.allow_delete,
            dt.default_order_by,
            udt.order_by,
            1 AS 'dash_tile'
        FROM dash_tile AS dt
        LEFT JOIN usergroup_dash_tile AS udt ON dt.ref = udt.dash_tile
        WHERE dt.all_users = 1
        AND udt.usergroup = ? {$tile_sql}
        ORDER BY udt.default_order_by ASC",
        $params
    );
}

/**
 * Get usergroup_dash_tile record
 *
 * @param integer $tile_id
 * @param integer $user_group_id
 *
 * @return array
 */
function get_usergroup_tile($tile_id, $user_group_id)
{
    $return = ps_query(
        "SELECT ref, usergroup, dash_tile, default_order_by, order_by
        FROM usergroup_dash_tile
        WHERE dash_tile = ?
        AND usergroup = ?",
        ['i', $tile_id, 'i', $user_group_id]
    );

    if (0 < count($return)) {
        return $return[0];
    }

    return array();
}

/*
 * User Dash Functions
 */

/*
 * Add a tile to a users dash
 * Affects the user_dash_tile table, tile must be the ref of a record from dash_tile
 *
 */
function add_user_dash_tile($user, $tile, $order_by, $reorder = true)
{
    if (!is_numeric($user) || !is_numeric($tile)) {
        return false;
    }
    if (!is_numeric($order_by)) {
        $order_by = append_user_position($user);
        $reorder = false;
    }

    ps_query(
        "INSERT INTO user_dash_tile (user,dash_tile,order_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE order_by= ?",
        ['i', $user, 'i', $tile, 'i', $order_by, 'i', $order_by]
    );

    if ($reorder) {
        reorder_user_dash($user);
    }

    return true;
}

/*
 * Get user_dash_tile record,
 * Provide the user_dash_tile ref as the $tile
 * this a place holder which links a dash_tile template with the user and the order that that tile should appear on THIS users dash
 *
 */
function get_user_tile($usertile, $user)
{
    $result = ps_query(
        "SELECT ref, user, dash_tile, order_by
        FROM user_dash_tile
        WHERE ref= ?
        AND user= ?",
        ['i', $usertile, 'i', $user]
    );

    return isset($result[0]) ? $result[0] : false;
}

 /*
  * Builds a users dash, this is a quick way of adding all_user tiles back to a users dash.
  * The Add_user_dash_tile function used checks for an existing match so that it won't duplicate tiles on a users dash
  *
  */
function create_new_user_dash($user)
{
    $tiles = ps_query(
        "SELECT
            dash_tile.ref AS 'tile',
            dash_tile.title,
            dash_tile.url,
            dash_tile.reload_interval_secs,
            dash_tile.link,
            dash_tile.default_order_by AS 'order'
        FROM dash_tile
        WHERE dash_tile.all_users = 1
        AND ref NOT IN (SELECT dash_tile FROM usergroup_dash_tile)
        AND (
            dash_tile.allow_delete=1 OR (
                dash_tile.allow_delete=0 AND dash_tile.ref IN (
                    SELECT DISTINCT user_dash_tile.dash_tile FROM user_dash_tile
                )
            )
        )
        ORDER BY default_order_by"
    );

    foreach ($tiles as $tile) {
        add_user_dash_tile($user, $tile["tile"], $tile["order"], false);
    }
}
/*
 * Updates a user_dash_tile record for a specific tile on a users dash with an order.
 *
 */
function update_user_dash_tile_order($user, $tile, $order_by)
{
    return ps_query("UPDATE user_dash_tile SET order_by= ? WHERE user= ? and ref= ?", ['i', $order_by, 'i', $user, 'i', $tile]);
}
/*
 * Delete a tile from a user dash
 * this will only remove the tile from this users dash.
 * It must be the ref of the row in the user_dash_tile
 * this also performs cleanup to ensure that there are no unused templates in the dash_tile table
 *
 */
function delete_user_dash_tile($usertile, $user)
{
    global $lang;

    if (!is_numeric($usertile) || !is_numeric($user)) {
        return false;
    }

    $row = get_user_tile($usertile, $user);
    ps_query("DELETE FROM user_dash_tile WHERE ref= ? and user= ?", ['i', $usertile, 'i', $user]);

    if (!isset($row["dash_tile"]) || !is_numeric($row["dash_tile"])) {
        return false;
    }

    $existing = ps_query("SELECT count(*) as 'count' FROM user_dash_tile WHERE dash_tile= ?", ['i', $row['dash_tile']]);

    if ($existing[0]["count"] < 1) {
        $tile = get_tile($row["dash_tile"]);
        delete_dash_tile($row["dash_tile"]);
        log_activity($lang['manage_all_dash'], LOG_CODE_DELETED, ($tile["title"] ?? ""), 'dash_tile', null, $row["dash_tile"]);
    }
}

/*
 * Remove all tiles from a users dash
 * Purge option does the cleanup in dash_tile removing any unused tiles
 * Turn purge off if you are just doing a quick rebuild of the tiles.
 */
function empty_user_dash($user, $purge = true)
{
    global $lang;
    $usertiles = ps_query(
        "SELECT udt.dash_tile,dt.title
            FROM user_dash_tile udt
                JOIN dash_tile dt ON udt.dash_tile=dt.ref
            WHERE udt.user= ?",
        ['i', $user]
    );

    ps_query("DELETE FROM user_dash_tile WHERE user= ?", ['i', $user]);

    if ($purge) {
        foreach ($usertiles as $tile) {
            $existing = ps_query("SELECT count(*) as 'count' FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile['dash_tile']]);
            if ($existing[0]["count"] < 1) {
                delete_dash_tile($tile["dash_tile"]);
                log_activity($lang['manage_all_dash'], LOG_CODE_DELETED, $tile["title"], 'dash_tile', null, $tile["dash_tile"]);
            }
        }
    }
}


/*
 * Reorders the users dash,
 * this is useful when you have just inserted a new tile or moved a tile and need to reorder them with the proper 10 gaps
 * Tiles should be ordered with values 10,20,30,40,50,60,70 for easy insertion
 */
function reorder_user_dash($user)
{
    $user_tiles = ps_query(
        "SELECT user_dash_tile.ref
        FROM user_dash_tile
        LEFT JOIN dash_tile ON user_dash_tile.dash_tile = dash_tile.ref
        WHERE user_dash_tile.user= ?
        ORDER BY user_dash_tile.order_by",
        ['i', $user]
    );

    if (count($user_tiles) < 2) {
        return;
    }

    $order_by = (10 * count($user_tiles)) + 10; # Begin ordering at 10 for first position, not 0.
    $sql = "UPDATE user_dash_tile SET order_by = (CASE ";
    $params = [];

    for ($i = count($user_tiles) - 1; $i >= 0; $i--) {
        $sql .= " WHEN ref= ? THEN ? ";
        $order_by -= 10;
        $params = array_merge($params, ['i', $user_tiles[$i]["ref"], 'i', $order_by]);
    }

    $sql .= " END) WHERE user='" . $user . "'";

    ps_query($sql, $params);
}

/*
 * Returns the position for a tile at the end of existing tiles
 *
 */
function append_user_position($user)
{
    $last_tile = ps_query("SELECT order_by FROM user_dash_tile WHERE user= ? ORDER BY order_by DESC LIMIT 1", ['i', $user]);
    return isset($last_tile[0]["order_by"]) ? $last_tile[0]["order_by"] + 10 : 10;
}

/*
 * All dash tiles available to the supplied userref
 * If you provide a dash_tile ref it will check if this tile exists within the list of available tiles to the user
 *
 */
function get_user_available_tiles($user, $tile = "null")
{
    $tilecheck = '';
    $params = [];

    if (is_numeric($tile)) {
        $tilecheck = 'WHERE ref = ?';
        $params = ['i', $tile];
    }

    return ps_query(
        "SELECT 
            result.*
        FROM
        (	(
            SELECT 
                dash_tile.ref,
                '' as 'dash_tile',
                '' as 'usertile', 
                '' as 'user', 
                '' as 'order_by',
                dash_tile.ref as 'tile',
                dash_tile.title,
                dash_tile.txt,
                dash_tile.link,
                dash_tile.url,
                dash_tile.resource_count,
                dash_tile.all_users,
                dash_tile.allow_delete,
                dash_tile.default_order_by
            FROM
                dash_tile
            WHERE
                dash_tile.all_users = 1
                AND
                ref 
                NOT IN
                (
                    SELECT 
                        dash_tile.ref
                    FROM
                        user_dash_tile
                    RIGHT OUTER JOIN
                        dash_tile
                    ON 
                        user_dash_tile.dash_tile = dash_tile.ref

                    WHERE
                        user_dash_tile.user = ?
                )
            AND ref NOT IN (SELECT dash_tile FROM usergroup_dash_tile)
            )
        UNION
            (
            SELECT 
                dash_tile.ref,
                user_dash_tile.dash_tile,
                user_dash_tile.ref as 'usertile', 
                user_dash_tile.user, 
                user_dash_tile.order_by,
                dash_tile.ref as 'tile',
                dash_tile.title,
                dash_tile.txt,
                dash_tile.link,
                dash_tile.url,
                dash_tile.resource_count,
                dash_tile.all_users,
                dash_tile.allow_delete,
                dash_tile.default_order_by
            FROM
                user_dash_tile
            RIGHT OUTER JOIN
                dash_tile
            ON 
                user_dash_tile.dash_tile = dash_tile.ref
            WHERE
                user_dash_tile.user = ?
            )
        ) result
        " . $tilecheck . "
        ORDER BY result.order_by,result.default_order_by
        ",
        array_merge(['i', $user, 'i', $user], $params)
    );
}

/*
 * Returns a users dash along with all necessary scripts and tools for manipulation
 * checks for the permissions which allow for deletions and manipulation of all_user tiles from the dash
 *
 */
function get_user_dash($user)
{
    global $baseurl,$baseurl_short,$lang,$help_modal;

    #Build User Dash and recalculate order numbers on display
    $user_tiles = ps_query(
        "SELECT
            dash_tile.ref AS 'tile',
            dash_tile.title,
            dash_tile.all_users,
            dash_tile.url,
            dash_tile.reload_interval_secs,
            dash_tile.link,
            user_dash_tile.ref AS 'user_tile',
            user_dash_tile.order_by
        FROM user_dash_tile
        JOIN dash_tile ON user_dash_tile.dash_tile = dash_tile.ref
        WHERE user_dash_tile.user = ?
        ORDER BY user_dash_tile.order_by ASC, dash_tile.ref DESC",
        array("i", $user)
    );

    $order = 10;

    foreach ($user_tiles as $tile) {
        if ($order != $tile["order_by"] || ($tile["order_by"] % 10) > 0) {
            update_user_dash_tile_order($user, $tile["user_tile"], $order);
        }

        $order += 10;
        $tile_custom_style = '';
        $buildstring = explode('?', $tile['url']);

        list($url_page, $buildstring) = $buildstring;
        parse_str(str_replace('&amp;', '&', $buildstring), $buildstring);

        if ($tile['all_users'] == 1 && strpos($tile['link'], 'team_analytics_edit.php') !== false) {
            // Dash tile is a graph from an analytics report. Clicking this tile should only link to the analytics report for the report owner.
            // The tile won't do anything for other users as they don't have access to view the report.
            if (!isset($user_analytics_reports)) {
                $user_analytics_reports = ps_array('SELECT ref AS `value` FROM user_report WHERE user = ?', array('i', $user));
            }

            $analytics_report_id = (int) substr(strrchr($tile['link'], '='), 1);

            if (!in_array($analytics_report_id, $user_analytics_reports)) {
                $tile['link'] = '';
            }
        }

        $tlsize = (isset($buildstring['tlsize']) ? $buildstring['tlsize'] : '');
        if (isset($buildstring['tltype']) && allow_tile_colour_change($buildstring['tltype']) && isset($buildstring['tlstylecolour'])) {
            $tile_custom_style .= get_tile_custom_style($buildstring);
        }
        ?>
        <a 
            <?php
            # Check link for external or internal
            if (mb_strtolower(substr($tile["link"], 0, 4)) == "http") {
                $link = escape($tile["link"]);
                $newtab = true;
            } else {
                $link = $baseurl . "/" . escape($tile["link"]);
                $newtab = false;
            }
            ?>
            href="<?php echo parse_dashtile_link($link)?>" <?php echo $newtab ? "target='_blank'" : "";?> 
            onClick="if(dragging){dragging=false;return false;}
                <?php if ($tile["link"] != "") {
                    echo $newtab ? "" : "return " . ($help_modal && strpos($link, "pages/help.php") !== false ? "ModalLoad(this,true);" : "CentralSpaceLoad(this,true);");
                } else {
                    echo "return false;";
                } ?>"
            class="HomePanel DashTile DashTileDraggable <?php echo $tile['all_users'] == 1 ? 'allUsers' : '';?> <?php echo 'double' == $tlsize ? 'DoubleWidthDashTile' : ''; ?>"
            tile="<?php echo $tile['tile']; ?>"
            id="user_tile<?php echo escape($tile["user_tile"]);?>"
        >
            <div id="contents_user_tile<?php echo escape($tile["user_tile"]);?>" class="HomePanelIN HomePanelDynamicDash" style="<?php echo $tile_custom_style; ?>">
                <script>
                    jQuery(function() {
                        var height = jQuery("#contents_user_tile<?php echo escape($tile["user_tile"]);?>").height();
                        var width = jQuery("#contents_user_tile<?php echo escape($tile["user_tile"]);?>").width();
                        jQuery('#contents_user_tile<?php echo escape($tile["user_tile"]) ?>').load("<?php echo generateURL($baseurl . '/' . $url_page, array_merge($buildstring, ['tile' => $tile['tile'], 'user_tile' => $tile['user_tile']]));?>&tlwidth="+width+"&tlheight="+height);
                    });
                </script>
            </div>  
        </a>
        <?php
    }

    # Check Permissions to Display Deleting Dash Tiles
    if ((checkperm("h") && !checkperm("hdta")) || (checkperm("dta") && !checkperm("h")) || !checkperm("dtu")) {
        render_trash("dash_tile", $lang['confirmdeleteconfigtile']);
        ?>
        <script>
            function deleteDashTile(id) {
                jQuery.post(
                    "<?php echo $baseurl?>/pages/ajax/dash_tile.php",
                    {
                        "user_tile": id,
                        "delete": "true",
                        <?php echo generateAjaxToken("deleteDashTile"); ?>
                    },
                    function(data) {
                        jQuery("#user_tile" + id).remove();
                    }
                );
            }

            function deleteDefaultDashTile(tileid,usertileid) {
                jQuery.post(
                    "<?php echo $baseurl?>/pages/ajax/dash_tile.php",
                    {
                        "tile": tileid,
                        "delete": "true",
                        <?php echo generateAjaxToken("deleteDefaultDashTile"); ?>
                    },
                    function(data) {
                        jQuery("#user_tile" + usertileid).remove();
                    }
                );
            }
        <?php
    } else {
        echo "<script>";
    }
    ?>

    function updateDashTileOrder(index,tile) {
        jQuery.post(
            "<?php echo $baseurl?>/pages/ajax/dash_tile.php",
            {
                "user_tile": tile,
                "new_index": ((index*10)),
                <?php echo generateAjaxToken("updateDashTileOrder"); ?>
            }
        );
    }

    var dragging = false;
        jQuery(function() {
            if (is_touch_device()) {
                return false;
            }

            jQuery("#HomePanelContainer").sortable({
                distance: 20,
                items: ".DashTileDraggable",
                start: function(event,ui) {
                    jQuery("#dash_tile_bin").show();
                    dragging = true;
                },
                stop: function(event,ui) {
                    jQuery("#dash_tile_bin").hide();
                },
                update: function(event, ui) {
                    nonDraggableTiles = jQuery(".HomePanel").length - jQuery(".DashTileDraggable").length;
                    newIndex = (ui.item.index() - nonDraggableTiles) + 1;
                    var id = jQuery(ui.item).attr("id").replace("user_tile","");
                    updateDashTileOrder(newIndex,id);
                }
            });

            <?php
            # Check Permissions to Display Deleting Dash Tiles
            if ((checkperm("h") && !checkperm("hdta")) || (checkperm("dta") && !checkperm("h")) || !checkperm("dtu")) {
                ?>
                jQuery("#dash_tile_bin").droppable({
                    accept: ".DashTileDraggable",
                    activeClass: "ui-state-hover",
                    hoverClass: "ui-state-active",
                    drop: function(event, ui) {
                        var usertileid = jQuery(ui.draggable).attr("id");
                        usertileid = usertileid.replace("user_tile","");
                        <?php
                        # If permission to delete all_user tiles
                        if ((checkperm("h") && !checkperm("hdta")) || (checkperm("dta") && !checkperm("h"))) { ?>
                            var tileid = jQuery(ui.draggable).attr("tile");
                            var usertileid = jQuery(ui.draggable).attr("id");
                            usertileid = usertileid.replace("user_tile","");
                            <?php
                        } ?>

                        title = jQuery(ui.draggable).find(".title").html();
                        jQuery("#dash_tile_bin").hide();

                        <?php
                        # If permission to delete all_user tiles
                        if ((checkperm("h") && !checkperm("hdta")) || (checkperm("dta") && !checkperm("h"))) {
                            ?>
                            if (jQuery(ui.draggable).hasClass("allUsers")) {
                                // This tile is set for all users so provide extra options
                                <?php render_delete_dialog_JS(true); ?>
                            } else {
                                // This tile belongs to this user only
                                <?php render_delete_dialog_JS(false); ?>
                            }
                            <?php
                        } else {
                            // Only show dialog to delete for this user
                            ?>
                            var dialog = <?php render_delete_dialog_JS(false);
                        } ?>
                    }
                });
                <?php
            }
            ?>
        });
    </script>
    <?php
}

/**
 * Render JavaScript for the dashboard tile delete dialog.
 *
 * This function outputs JavaScript to create and display a jQuery UI dialog for deleting dashboard tiles.
 * Depending on the `$all_users` parameter, the dialog may include additional options for deleting default tiles
 * or managing default dashboard tiles.
 *
 * @param bool $all_users (optional) If set to `true`, additional options are displayed for managing tiles that are
 *                         accessible by all users. Default is `false`.
 * @return void Outputs JavaScript directly to the page to render the dialog.
 */
function render_delete_dialog_JS($all_users = false)
{
    global $baseurl, $lang;
    ?>
    jQuery("#trash_bin_delete_dialog").dialog({
        title:'<?php echo escape($lang["dashtiledelete"]); ?>',
        autoOpen: true,
        modal: true,
        resizable: false,
        dialogClass: 'delete-dialog no-close',
        buttons: {
            "<?php echo escape($lang['confirmdashtiledelete']); ?>": function() {deleteDashTile(usertileid); jQuery(this).dialog( "close" );},
            <?php if ($all_users) { ?>
                "<?php echo escape($lang['confirmdefaultdashtiledelete']); ?>": function() {deleteDefaultDashTile(tileid,usertileid); jQuery(this).dialog( "close" );},
                "<?php echo escape($lang['managedefaultdash']); ?>": function() {window.location = "<?php echo $baseurl; ?>/pages/team/team_dash_tile.php"; return false;},
            <?php } ?>
            "<?php echo escape($lang['cancel']); ?>":  function() { jQuery(this).dialog('close'); }
        }
    });
    <?php
}

/*
 * Helper Functions
 */
function parse_dashtile_link($link)
{
    global $userref, $upload_then_edit;
    $link = str_replace("[userref]", $userref, $link);

    //For upload tiles respect the upload then edit preference
    if ((strpos($link, 'uploader=') !== false) && $upload_then_edit) {
        global $baseurl;

        $query = parse_url($link, PHP_URL_QUERY);

        if ($query === false || is_null($query)) {
            $query = "";
        }

        /**
        * @var path is the real ResourceSpace path (regardless if RS is installed under web root or in a subfolder)
        * Example:
        * For http://localhost/trunk/pages/edit.php?ref=-[userref]&uploader=batch the real path is pages/edit.php as
        * RS handles this via its baseurl when generating absolute paths.
        */
        $path = str_replace("{$baseurl}/", "", $link);
        $path = str_replace("?{$query}", "", $path);

        $link = str_replace($path, "pages/upload_batch.php", $link);
    }

    return $link;
}

/*
 * Dash Admin Display Functions
 */

// Build dash list
function build_dash_tile_list($dtiles_available)
{
    global $lang,$baseurl_short,$baseurl;

    foreach ($dtiles_available as $tile) {
        $checked = false;

        if (!empty($tile["dash_tile"])) {
            $checked = true;
        }

        $buildstring = explode('?', $tile["url"]);
        parse_str(str_replace("&amp;", "&", ($buildstring[1] ?? "")), $buildstring);
        ?>

        <tr
            id="tile<?php echo $tile["ref"];?>"
            <?php if (isset($buildstring["tltype"]) && $buildstring["tltype"] == "conf") { ?>
                class="conftile"
            <?php } ?>
        >
            <td>
                <input 
                    type="checkbox" 
                    class="tilecheck" 
                    name="tiles[]" 
                    value="<?php echo $tile["ref"];?>" 
                    onChange="changeTile(<?php echo $tile["ref"];?>,<?php echo $tile["all_users"];?>);"
                    <?php echo $checked ? "checked" : "";?> 
                />
            </td>
            <td>
                <?php
                if (isset($buildstring["tltype"]) && $buildstring["tltype"] == "conf" && $buildstring["tlstyle"] != "custm" && $buildstring["tlstyle"] != "pend" && isset($lang[$tile["title"]])) {
                    echo escape(i18n_get_translated($lang[$tile["title"]]));
                } else {
                    echo escape(i18n_get_translated($tile["title"]));
                }
                ?>
            </td>
            <td>
                <?php
                if (isset($buildstring["tltype"]) && $buildstring["tltype"] == "conf" && $buildstring["tlstyle"] != "custm" && $buildstring["tlstyle"] != "pend") {
                    $tile["txt"] = text($tile["title"]);
                } elseif (isset($buildstring["tltype"]) && $buildstring["tltype"] == "conf" && $buildstring["tlstyle"] == "pend") {
                    if (isset($lang[strtolower($tile['txt'])])) {
                        $tile['txt'] = escape($lang[strtolower($tile["txt"])]);
                    } else {
                        $tile['txt'] = escape($tile['txt']);
                    }
                }

                if (strlen($tile["txt"]) > 75) {
                    echo escape(substr(i18n_get_translated($tile["txt"]), 0, 72) . "...");
                } else {
                    echo escape(i18n_get_translated($tile["txt"]));
                }
                ?>
            </td>
            <td>
                <a 
                    href="<?php echo (mb_strtolower(substr($tile["link"], 0, 4)) == "http") ? escape($tile["link"]) : $baseurl . "/" . escape($tile["link"]);?>"
                    target="_blank"
                >
                    <?php echo escape($lang["dashtilevisitlink"]); ?>
                </a>
            </td>
            <td><?php echo escape($tile["resource_count"] ? $lang["yes"] : $lang["no"]); ?></td>
            <td class="ListTools">
                <?php
                if (
                    $tile["allow_delete"]
                    && (
                        ($tile["all_users"] && checkPermission_dashadmin())
                        || (
                            !$tile["all_users"]
                            && (
                                checkPermission_dashuser()
                                || checkPermission_dashadmin()
                            )
                        )
                    )
                ) { ?>
                    <a href="<?php echo $baseurl_short; ?>pages/dash_tile.php?edit=<?php echo $tile['ref'];?>">
                        <i class="icon-square-pen"></i>
                        &nbsp;
                        <?php echo escape($lang["action-edit"]);?>
                    </a>
                    <?php
                }
                ?>
            </td>
        </tr>
        <?php
    }
}

/**
* Check whether we allow a colour change of a tile from the interface.
* At the moment it is only available for blank search tiles and text
* text only tiles.
*
* @param string $tile_type
* @param string $tile_style Examples: thmbs, multi, blank, ftxt
*
* @return boolean
*/
function allow_tile_colour_change($tile_type, $tile_style = '')
{
    global $tile_styles;

    $allowed_styles = array('blank', 'ftxt');

    // Check a specific style for a type
    if ('' !== $tile_style && !in_array($tile_style, $allowed_styles)) {
        return false;
    }

    // Is one of the allowed styles in the styles available for this tile type?
    if (isset($tile_styles[$tile_type]) && 0 < count(array_intersect($tile_styles[$tile_type], $allowed_styles))) {
        return true;
    }

    return false;
}

/**
* Renders a new section to pick/ select a colour. User can either use the color
* picker or select a colour from the ones already available (config option)
*
* @param string $tile_style
* @param string $tile_colour Hexadecimal code (without the # sign). Example: 0A8A0E
*
* @return void
*/
function render_dash_tile_colour_chooser($tile_style, $tile_colour)
{
    global $lang, $baseurl;

    if ('ftxt' == $tile_style) { ?>
        <div class="Question">
    <?php } else { ?>
        <span id="tile_style_colour_chooser" style="display: none;">
    <?php } ?>

    <label for="tile_style_colour"><?php echo escape($lang['colour']); ?></label>
    <input id="tile_style_colour" name="tlstylecolour" type="color" onchange="update_tile_preview_colour(this.value);" value="<?php echo $tile_colour; ?>">

    <!-- Show/ hide colour picker/ selector -->
    <script>
        function update_tile_preview_colour(colour) {
            jQuery('#previewdashtile').css('background-color', '#' + colour);
        }

        <?php if ('ftxt' == $tile_style) { ?>
            jQuery(document).ready(function() {
                if (jQuery('#tile_style_colour').val() != '') {
                    update_tile_preview_colour('<?php echo $tile_colour; ?>');
                }
            });
        <?php } else { ?>
            jQuery(document).ready(function() {
                if (jQuery('#tile_style_<?php echo $tile_style; ?>').prop('checked')) {
                    jQuery('#tile_style_colour_chooser').show();
                    update_tile_preview_colour('<?php echo $tile_colour; ?>');
                }
            });

            jQuery('input:radio[name="tlstyle"]').change(function() {
                if (jQuery(this).prop('checked') && jQuery(this).val() == '<?php echo $tile_style; ?>') {
                    jQuery('#tile_style_colour_chooser').show();
                } else {
                    jQuery('#tile_style_colour_chooser').hide();
                    jQuery('#tile_style_colour').val('');
                    jQuery('#tile_style_colour').removeAttr('style');
                    jQuery('#previewdashtile').removeAttr('style');
                }
            });
        <?php } ?>
    </script>

    <?php if ('ftxt' == $tile_style) { ?>
        </div>
    <?php } else { ?>
        </span>
    <?php } ?>
    <div class="clearerleft"></div>
    <?php
}

/**
 * Generate custom CSS style for a dashboard tile background color.
 *
 * @param array $buildstring An associative array containing tile style properties, including 'tlstylecolour' for the background color.
 * @return string A CSS style string for the background color (e.g., "background-color: #FFFFFF;"), or an empty string if no color is specified.
 */
function get_tile_custom_style($buildstring)
{
    if (isset($buildstring['tlstylecolour'])) {
        $return_value = "background-color: ";

        if (preg_match('/^[a-fA-F0-9]+$/', $buildstring['tlstylecolour'])) {
            // this is a fix for supporting legacy hex values that do not have '#' at start
            $return_value .= '#';
        }

        $return_value .= $buildstring['tlstylecolour'] . ';';

        return $return_value;
    } else {
        return '';
    }
}

/*
 * Delete a tile from the dash for all users in a group
*
* @param integer $tile ID of tile to delete
* @param integer $group ref of usergroup to delete tile from
*
* @return boolean
*/
function delete_usergroup_dash_tile($tile, $group)
{
    if (!is_numeric($tile) || !is_numeric($group)) {
        return false;
    }
    ps_query("DELETE FROM usergroup_dash_tile WHERE usergroup = ? AND dash_tile = ?", ['i', $group, 'i', $tile]);
    ps_query("DELETE ud.* FROM user_dash_tile ud LEFT JOIN user u ON ud.user=u.ref LEFT JOIN usergroup ug ON ug.ref=u.usergroup WHERE ud.dash_tile= ? and ug.ref= ?", ['i', $tile, 'i', $group]);
}

/**
* Confirms whether a dash tile type allows for promoted resources
*
* @param string $tile_type
*
* @return boolean
*/
function allowPromotedResources($tile_type)
{
    if ('' === trim($tile_type)) {
        return false;
    }

    $allowed_types = array('srch', 'fcthm');

    return in_array($tile_type, $allowed_types);
}

/**
* Render "Upgrade available" tile for Administrators and Super Admins. This tile cannot be deleted or removed unless
* ResourceSpace version is up to date
*
* @param integer $user User ID, normally this is the $userref
*
* @return void
*/
function render_upgrade_available_tile($user)
{
    if (!(checkperm("t") || checkperm("a"))) {
        return;
    }

    if (!is_resourcespace_upgrade_available()) {
        return;
    }
    ?>
    <a href="https://www.resourcespace.com/versions"
       target="_blank"
       class="HomePanel DashTile"
       id="upgrade_available_tile">
        <div id="contents_user_tile_upgrade_available" class="HomePanelIN HomePanelDynamicDash">
            <h2><?php echo escape($GLOBALS['lang']['upgrade_available_title']); ?></h2>
            <p><?php echo escape($GLOBALS['lang']['upgrade_available_text']); ?></p>
        </div>
    </a>
    <?php
}

/**
 * Generate HTML and JavaScript for the dashboard tile toolbar.
 *
 * This function renders a toolbar for a dashboard tile, providing options to delete or edit the tile,
 * based on the user's permissions. The toolbar appears on hover and includes JavaScript functionality
 * to manage tile interactions, including preventing accidental clicks on the tile itself when using the toolbar.
 *
 * @param array $tile An associative array containing information about the dashboard tile, including:
 *                    - 'ref' (int): Tile reference ID.
 *                    - 'all_users' (int): Flag indicating if the tile is accessible by all users.
 *                    - 'no_edit' (bool): Flag indicating if the tile is non-editable.
 *                    - 'url' (string|null): Optional URL for the tile's link.
 * @param string $tile_id The unique HTML ID of the tile used for identifying elements in the toolbar.
 * @return void Outputs HTML and JavaScript directly to the page for the tile toolbar.
 */
function generate_dash_tile_toolbar(array $tile, $tile_id)
{
    global $baseurl_short, $lang, $managed_home_dash;

    $editlink = $baseurl_short . "pages/dash_tile.php?edit=" . (int) $tile['ref'];

    if (!$managed_home_dash && (checkPermission_dashadmin() || checkPermission_dashuser())) {
        ?>
        <div id="DashTileActions_<?php echo substr($tile_id, 18); ?>" class="DashTileActions"  style="display:none;">
            <div class="tool dash-delete_<?php echo substr($tile_id, 18); ?>">
                <a href="#">
                    <span><?php echo LINK_CARET . escape($lang['action-delete']); ?></span>
                </a>
            </div>
            <?php
            if ((checkPermission_dashadmin() || (isset($tile['all_users']) && $tile['all_users'] == 0)) && !(isset($tile['no_edit']) && $tile['no_edit'])) {
                ?>
                <div class="tool edit">
                    <a href="<?php echo $editlink ?>" onClick="return CentralSpaceLoad(this,true);">
                        <span><?php echo LINK_CARET . escape($lang['action-edit']); ?></span>
                    </a>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
    ?>

    <script>
        jQuery(document).ready(function() {
            if (pagename == "home") {
                var tileid = "<?php echo (int) $tile["ref"]; ?>"; //Needs to be set for delete functionality
                var usertileid = "<?php echo escape(substr($tile_id, 18)); ?>" //Needs to be set for delete functionality
                var usertileidname = "#<?php echo escape(substr($tile_id, 9)); ?>";
                var dashtileactionsid = "#DashTileActions_" + usertileid;
                var deletetileid = ".dash-delete_" + usertileid;
                var editlink = "<?php echo isset($tile["url"]) ? $tile["url"] : ""; ?>";
                var tilehref; //Used to switch off and on tile link to stop issue clicking on tool bar but opening tile link
                var tileonclick; //Used to switch off and on tile link to stop issue clicking on tool bar but opening tile link
        
                jQuery(usertileidname).hover(
                    function(e) {
                        jQuery(dashtileactionsid).stop(true, true).slideDown();
                    },
                    function(e) {
                        jQuery(dashtileactionsid).stop(true, true).slideUp();
                    }
                );
        
                jQuery(dashtileactionsid).hover(
                    function(e) {
                        tilehref = jQuery(usertileidname).attr("href");
                        tileonclick = jQuery(usertileidname).attr("onclick");
                        jQuery(usertileidname).attr("href", "#");
                        jQuery(usertileidname).attr("onclick", "return false;");
                    },
                    function(e) {
                        jQuery(usertileidname).attr("href", tilehref);
                        jQuery(usertileidname).attr("onclick", tileonclick);
                        tilehref = '';
                        tileonclick = '';
                    }
                );
        
                jQuery(deletetileid).click(
                    function(event,ui) {
                        <?php if (checkPermission_dashadmin()) { ?>
                            if (jQuery(usertileidname).hasClass("allUsers")) {
                                // This tile is set for all users so provide extra options
                                <?php render_delete_dialog_JS(true); ?>
                            } else {
                                // This tile belongs to this user only
                                <?php render_delete_dialog_JS(false); ?>
                            }
                            <?php
                        } else {
                            // Only show dialog to delete for this user
                            ?>
                            var dialog = <?php render_delete_dialog_JS(false);
                        } ?>
                    }
                );
            } else {
                jQuery("#DashTileActions_").remove();
            }
        });
    </script>
    <?php
}

/**
* Build list of resources that can be shown on a dash tile as a background image. Helper function.
*
* @param array|integer $c   Collection ref -or- collection data as returned by {@see get_collection()}
* @param array         $ctx Contextual data
*
* @return array
*/
function dash_tile_featured_collection_get_resources($c, array $ctx)
{
    $collection = (is_int($c) ? get_collection($c) : $c);
    $collection = (is_array($collection) && !empty($collection) ? $collection : false);

    if ($collection === false) {
        return array();
    }

    global $view_title_field;

    $collection_resources = get_collection_resources($collection["ref"]);
    $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);

    $featured_collection_resources = get_featured_collection_resources($collection, $ctx);
    $featured_collection_resources_data = get_resource_data_batch($featured_collection_resources);

    $resources = array();

    foreach ($featured_collection_resources_data as $resource_data) {
        $resources[] = array(
            "ref" => $resource_data['ref'],
            "field{$view_title_field}" => get_data_by_field($resource_data['ref'], $view_title_field),
            "resource_type" => $resource_data['resource_type'],
            "file_extension" => $resource_data['file_extension']);
    }

    return $resources;
}

/**
 * Validate the type of dash tile and check that the style provided is valid for it.
 *
 * @param  string  $type   Tile type name.
 * @param  string  $style  Tile style name.
 *
 * @return string  Will return the style value provided if correct, the first defined style or blank if no styles defined.
 */
function validate_tile_style(string $type, string $style)
{
    global $tile_styles;
    if (isset($tile_styles) && array_key_exists($type, $tile_styles)) {
        if (count($tile_styles[$type]) === 0) {
            return '';
        }

        if (in_array($style, $tile_styles[$type])) {
            return $style;
        } else {
            return $tile_styles[$type][0];
        }
    } else {
        return '';
    }
}

/**
 * Sanitise the url provided when saving a dash tile. This function will take the value obtained by the form and pass it through if valid.
 * If the url supplied is invalid, a blank value will be returned allowing the default standard tile type to be used.
 *
 * @param   string  $buildurl   url supplied when dash tile is edited, containing a number of optional parameters.
 *
 * @return  string  A valid url or empty string if invalid.
 */
function validate_build_url($buildurl)
{
    global $tile_styles;

    if ($buildurl != "") {
        # Sanitise the url provided.
        $build_url_parts = explode('?', $buildurl);
        $valid_tile_urls = array();
        $valid_tile_urls[] = 'pages/ajax/dash_tile.php';
        $valid_tile_urls[] = 'pages/team/ajax/graph.php';

        if (!in_array($build_url_parts[0], $valid_tile_urls)) {
            // Url is invalid
            $buildurl = "";
        } else {
            parse_str(($build_url_parts[1] ?? ""), $build_url_parts_param);
            foreach ($build_url_parts_param as $param => $value) {
                switch ($param) {
                    case 'tltype':
                        # type checks
                        if (!array_key_exists($value, $tile_styles)) {
                            $buildurl = "";
                        }
                        break;
                    case 'tlsize':
                        # size checks
                        if (!in_array($value, array('single','double',''))) {
                            $buildurl = "";
                        }
                        break;
                    case 'tlstyle':
                        # style checks
                        $all_tile_styles = array();
                        foreach ($tile_styles as $tile_type_style) {
                            $all_tile_styles = array_merge($all_tile_styles, $tile_type_style);
                        }
                        if (!in_array($value, $all_tile_styles)) {
                            $buildurl = "";
                        }
                        break;
                    case 'promimg':
                        # img checks
                        if (!is_int_loose($value) && !is_bool($build_url_parts_param[1])) {
                            $buildurl = "";
                        }
                        break;
                }
            }
        }
    }
    return $buildurl;
}

/**
 * Generate client side logic for doing expensive computation async for retrieving the tile background and total results count.
 *
 * @param array  $tile           Tile information {@see pages/ajax/dash_tile.php}
 * @param string $tile_id        HTML ID for the container div
 * @param int    $tile_width     Tile width {@see pages/ajax/dash_tile.php}
 * @param int    $tile_height    Tile height {@see pages/ajax/dash_tile.php}
 * @param int    $promoted_image ID of the promoted resource (for background)
 */
function tltype_srch_generate_js_for_background_and_count(array $tile, string $tile_id, int $tile_width, int $tile_height, int $promoted_image)
{
    // Prevent function from running for the wrong tile type and style
    parse_str(parse_url($tile['url'] ?? '', PHP_URL_QUERY), $tile_meta);
    if (
        !(
        isset($tile_meta['tltype'], $tile_meta['tlstyle'])
        && $tile_meta['tltype'] === 'srch'
        && in_array($tile_meta['tlstyle'], $GLOBALS['tile_styles']['srch'])
        )
    ) {
        return;
    }

    $tile_style = $tile_meta['tlstyle'];

    ?>
    <!-- Resource counter -->
    <p class="no_resources DisplayNone"><?php echo escape($GLOBALS['lang']['noresourcesfound']); ?></p>
    <p class="tile_corner_box DisplayNone">
        <span aria-hidden="true" class="icon-copy"></span>
    </p>

    <script>
        jQuery(document).ready(function() {
            const TILE_STYLE = '<?php echo escape($tile_style); ?>';
            const SHOW_RESOURCE_COUNT = <?php echo $tile['resource_count'] ? 'true' : 'false'; ?>;

            let data = {
                'link': '<?php echo escape($tile["link"]); ?>',
                'promimg': '<?php echo (int)$promoted_image; ?>',
            };

            api('get_dash_search_data', data, function(response) {
                const TILE_ID = '<?php echo escape($tile_id); ?>';
                const TILE_WIDTH = <?php echo $tile_width; ?>;
                const TILE_HEIGHT = <?php echo $tile_height; ?>;
                var preview_resources;

                if (TILE_STYLE === 'thmbs') {
                    let promoted_image = <?php echo (int)$promoted_image; ?>;
                    let promoted_image_resource = response.images.filter(resource => resource.ref == promoted_image && typeof resource.url !== 'undefined');
                    console.debug('promoted_image_resource = %o', promoted_image_resource);

                    // Filter response
                    preview_resources = promoted_image > 0 && promoted_image_resource[0] !== undefined ? [promoted_image_resource[0]]
                    : promoted_image === 0 && response.images[0] !== undefined ? [response.images[0]]
                    : [];
                    // Fit (adjust) the 'pre' size to the tile size
                    preview_resources = preview_resources.map(function(resource) {
                        if (resource['thumb_width'] * 0.7 >= resource['thumb_height']) {
                            let ratio = resource['thumb_height'] / TILE_HEIGHT;
                            if (ratio == 0) { ratio = 1; } // attempt fit if 'thumb_height' is 0
                            let width = resource['thumb_width'] / ratio;
                            var size = width < TILE_WIDTH ? ' width="100%"' : ' height="100%"';
                        } else {
                            let ratio = resource['thumb_width'] / TILE_WIDTH;
                            if (ratio == 0) { ratio = 1; } // attempt fit if 'thumb_width' is 0

                            let height = resource['thumb_height'] / ratio;
                            var size = height < TILE_HEIGHT ? ' height="100%"' : ' width="100%"';
                        }

                        return '<img alt="' + resource.title + '" src="' + resource.url + '"' + size + ' class="thmbs_tile_img AbsoluteTopLeft">';
                    });
                } else if (TILE_STYLE === 'multi') {
                    preview_resources = response.images
                        .map(function(resource, index, resources_list) {
                            let tile_working_space = <?php echo $tile['tlsize'] == '' ? 140 : 280; ?>;
                            let gap = tile_working_space / resources_list.length;
                            let space = index * gap;
                            let style = 'left: ' + (space * 1.5) + 'px;'
                                + ' transform: rotate(' + (20 - (index * 12)) + 'deg);';

                            return '<img alt="' + resource.title + '" src="' + resource.url + '" style="' + style + '">';
                        })
                        // images will be prepended to the tile container so reverse the order so that the layout ends up as 
                        // expected (from left to right, each preview on top of the previous one)
                        .reverse();
                } else {
                    // Blank style
                    preview_resources = [];
                }

                // Tile background - resource(s) preview
                console.debug('preview_resources = %o', preview_resources);
                if (preview_resources.length > 0) {
                    let tile_div = jQuery('div#' + TILE_ID);

                    for (let i = 0; i < preview_resources.length; i++) {
                        tile_div.prepend(preview_resources[i]);
                    }
                }

                // Resource count
                let tile_corner_box = jQuery('div#' + TILE_ID + ' p.tile_corner_box');

                if (SHOW_RESOURCE_COUNT) {
                    tile_corner_box.find('.DisplayResourceCount').remove();
                    tile_corner_box.append(`<span class="DisplayResourceCount">${response.count}</span>`);
                    tile_corner_box.removeClass('DisplayNone');
                } else if(response.count == 0) {
                    jQuery('div#' + TILE_ID + ' p.no_resources').removeClass('DisplayNone');
                }
            },
            <?php echo generate_csrf_js_object('get_dash_search_data'); ?>,
            {
                onStart: function() { jQuery('#<?php echo escape($tile_id); ?>').addClass('is-loading'); },
                onEnd:   function() { jQuery('#<?php echo escape($tile_id); ?>').removeClass('is-loading'); }
            }
            );
        });
    </script>
    <?php
}

/**
 * Get images and resource count for search dash tile.
 * This has to work on a string because the dash tile does not yet exist when on dash creation page
 * For performance this function will return a maximum of 4 images
 *
 * @param  string   $link          Tile link URL
 * @param  int      $promimg       Promoted image ref
 *
 * @return array    $searchdata    Array containing the count of resources and details of preview images.
 */
function get_dash_search_data($link = '', $promimg = 0)
{
    global $search_all_workflow_states, $view_title_field, $lang;

    // Maximum number of preview images to show for a dash tile
    $dash_tile_preview_count = 4;

    // Maximum number of resources to process for dash tile preview generation
    // Beyond this count, resource previews will not be considered for display on the dash tile
    $dash_tile_resource_limit = 50;

    $searchdata = [];
    $searchdata["count"] = 0;
    $searchdata["images"] = [];

    $search_string = explode('?', $link);
    parse_str(str_replace("&amp;", "&", $search_string[1]), $search_string);
    $search = isset($search_string["search"]) ? $search_string["search"] : "";
    $restypes = isset($search_string["restypes"]) ? $search_string["restypes"] : "";
    $order_by = isset($search_string["order_by"]) ? $search_string["order_by"] : "";
    $archive = isset($search_string["archive"]) ? $search_string["archive"] : "";
    $sort = isset($search_string["sort"]) ? $search_string["sort"] : "";

    if ($archive !== "") {
        $search_all_workflow_states = false;
    }

    $results = do_search($search, $restypes, $order_by, $archive, [0, $dash_tile_resource_limit], $sort);

    $imagecount = 0;

    if (is_array($results)) {

        if (key_exists("total", $results)) {

            $resultcount = $results["total"];
            $searchdata["count"] = $resultcount;
            $n = 0;

            // First see if we can get the promoted image by adding it to the front of the array
            if ($promimg != 0) {
                $add = get_resource_data($promimg);
                if (is_array($add)) {
                    array_unshift($results["data"], $add);
                }
            }

            while ($imagecount < $dash_tile_preview_count && $n < $resultcount && $n < $dash_tile_resource_limit) { // Don't keep trying to find images if none exist
                global $access; // Needed by check_use_watermark()
                $access = get_resource_access($results["data"][$n]);
                if (in_array($access, [RESOURCE_ACCESS_RESTRICTED, RESOURCE_ACCESS_FULL])) {
                    $use_watermark = check_use_watermark();
                    if (
                        !resource_has_access_denied_by_RT_size($results["data"][$n]['resource_type'], 'pre')
                        && file_exists(get_resource_path($results["data"][$n]['ref'], true, 'pre', false, 'jpg', -1, 1, $use_watermark))
                    ) {
                        $searchdata["images"][$imagecount]["ref"] = $results["data"][$n]["ref"];
                        $searchdata["images"][$imagecount]["thumb_width"] = $results["data"][$n]["thumb_width"];
                        $searchdata["images"][$imagecount]["thumb_height"] = $results["data"][$n]["thumb_height"];
                        $searchdata["images"][$imagecount]["url"] = get_resource_path($results["data"][$n]["ref"], false, "pre", false, "jpg", -1, 1, $use_watermark);
                        $searchdata["images"][$imagecount]["title"] = escape($results["data"][$n]["field" . $view_title_field] ?? $lang["resource-1"] . " " . $results["data"][$n]["ref"]);
                        $imagecount++;
                    }
                }
                $n++;
            }

        } else {
            $resultcount = count($results);
            $searchdata["count"] = $resultcount;
            $n = 0;

            // First see if we can get the promoted image by adding it to the front of the array
            if ($promimg != 0) {
                $add = get_resource_data($promimg);
                if (is_array($add)) {
                    array_unshift($results, $add);
                }
            }

            while ($imagecount < $dash_tile_preview_count && $n < $resultcount && $n < $dash_tile_resource_limit) { // Don't keep trying to find images if none exist
                global $access; // Needed by check_use_watermark()
                $access = get_resource_access($results[$n]);
                if (in_array($access, [RESOURCE_ACCESS_RESTRICTED, RESOURCE_ACCESS_FULL])) {
                    $use_watermark = check_use_watermark();
                    if (
                        !resource_has_access_denied_by_RT_size($results[$n]['resource_type'], 'pre')
                        && file_exists(get_resource_path($results[$n]['ref'], true, 'pre', false, 'jpg', -1, 1, $use_watermark))
                    ) {
                        $searchdata["images"][$imagecount]["ref"] = $results[$n]["ref"];
                        $searchdata["images"][$imagecount]["thumb_width"] = $results[$n]["thumb_width"];
                        $searchdata["images"][$imagecount]["thumb_height"] = $results[$n]["thumb_height"];
                        $searchdata["images"][$imagecount]["url"] = get_resource_path($results[$n]["ref"], false, "pre", false, "jpg", -1, 1, $use_watermark);
                        $searchdata["images"][$imagecount]["title"] = escape($results[$n]["field" . $view_title_field] ?? $lang["resource-1"] . " " . $results[$n]["ref"]);
                        $imagecount++;
                    }
                }
                $n++;
            }
        }
    }
    return $searchdata;
}

/**
 * Check if current user can edit dash tile. Users shouldn't be able to edit tiles that they can't view and only dash admins should be able to edit shared tiles.
 *
 * @param  int   $usertile   Ref of the tile being edited. Typically obtained from get_tile()
 * @param  int   $audience   0 for tile available to one user, 1 for all users. Typically obtained from get_tile()
 * @param  int   $user       Ref of the user editing the tile.
 *
 * @return bool   Will return true if editing is allowed else will return false.
 */
function can_edit_tile(int $tileref, int $audience, int $user)
{
    if ($audience === 0) {
        // User is trying to edit a tile visible to only them.
        $result = ps_query("SELECT ref, user, dash_tile, order_by FROM user_dash_tile WHERE dash_tile = ? AND user = ?", ['i', $tileref, 'i', $user]);
        return isset($result[0]);
    } else {
        // Tile is available to all users so editable only to dash admin users.
        // This includes tiles available to a specified group.
        return checkPermission_dashadmin();
    }
}
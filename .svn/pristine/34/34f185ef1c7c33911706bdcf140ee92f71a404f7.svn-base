<?php

/*
 * Home Dash Ajax Interface - Montala Ltd, Jethro Dew
 * Requests from the dash interactions are processed here.
 */
include "../../include/boot.php";

include "../../include/authenticate.php";
include "../../include/dash_functions.php";

/* You must correctly use exit calls when functionality is complete. */

global $userref,$baseurl_short;
/* Tile */
$rawtile = getval("tile", null, true);
if (isset($rawtile) && !empty($rawtile)) {
    if (!is_numeric($rawtile)) {
        exit($lang["invaliddashtile"]);
    }
    $tile = get_tile($rawtile);
    if (!$tile) {
        exit($lang["nodashtilefound"]);
    }
}

/* User Tile */
$user_rawtile = getval("user_tile", null, true);
if (isset($user_rawtile) && !empty($user_rawtile)) {
    if (!is_numeric($user_rawtile)) {
        exit($lang["invaliddashtile"]);
    }
    $usertile = get_user_tile($user_rawtile, $userref);
    if (!$usertile) {
        exit($lang["nodashtilefound"]);
    }
}

/*
 * Reorder Tile
 */
$index               = getval('new_index', '', true);
$selected_user_group = getval('selected_user_group', '', true);

// Re-order user tiles
if (!empty($index) && isset($usertile) && '' == $selected_user_group && enforcePostRequest(true)) {
    if ($index > $usertile["order_by"]) {
        $index += 5;
    } else {
        $index -= 5;
    }
    update_user_dash_tile_order($userref, $usertile["ref"], $index);
    reorder_user_dash($userref);
    exit("Tile " . $usertile["ref"] . " at index: " . ($index));
}

// Re-order user group tiles
if (!empty($index) && isset($tile) && !isset($usertile) && '' != $selected_user_group && enforcePostRequest(true)) {
    $usergroup_tile = get_usergroup_tile($tile['ref'], $selected_user_group);
    if (0 == count($usergroup_tile)) {
        exit($lang['nodashtilefound']);
    }

    if ($index > $usergroup_tile['default_order_by']) {
        $index += 5;
    } else {
        $index -= 5;
    }

    update_usergroup_dash_tile_order($selected_user_group, $usergroup_tile['dash_tile'], $index);
    reorder_usergroup_dash($selected_user_group);

    log_activity($lang['dashtile'], LOG_CODE_REORDERED, $index, 'usergroup_dash_tile', 'default_order_by', $usergroup_tile['dash_tile']);

    exit("Tile {$usergroup_tile['dash_tile']} at index: {$index}");
}

// Re-order default dash tiles
if (!empty($index) && isset($tile) && !isset($usertile) && '' == $selected_user_group && enforcePostRequest(true)) {
    if ($index > $tile["default_order_by"]) {
        $index += 5;
    } else {
        $index -= 5;
    }
    update_default_dash_tile_order($tile["ref"], $index);
    reorder_default_dash();
    echo "Tile " . $tile["ref"] . " at index: " . ($index);
    log_activity($lang["dashtile"], LOG_CODE_REORDERED, $index, 'dash_tile', 'default_order_by', $tile["ref"]);
    exit();
}

/*
 * Delete Tile
 */
$delete = getval("delete", false);
if ($delete && isset($usertile) && enforcePostRequest(true)) {
    if (!checkPermission_dashmanage()) {
        exit($lang["error-permissiondenied"]);
    }
    delete_user_dash_tile($usertile["ref"], $userref);
    reorder_user_dash($userref);
    echo "Deleted U" . $usertile['ref'];
    exit();
}
if ($delete && isset($tile) && !isset($usertile) && enforcePostRequest(true)) {
    if (!checkPermission_dashcreate()) {
        exit($lang["error-permissiondenied"]);
    }

    #Check config tiles for permanent deletion
    $force = false;
    $search_string = explode('?', $tile["url"]);
    parse_str(str_replace("&amp;", "&", $search_string[1]), $search_string);
    if ($search_string["tltype"] == "conf") {
        $force = !checkTileConfig($tile, $search_string["tlstyle"]);
    }

    delete_dash_tile($tile["ref"], true, $force);
    log_activity($lang['manage_all_dash'], LOG_CODE_DELETED, $tile["title"], 'dash_tile', null, $tile["ref"]);
    reorder_default_dash();
    echo "Deleted " . $tile['ref'];
    exit();
}

if (!isset($usertile) && !isset($tile)) {
    exit($lang["nodashtilefound"]);
}

/*
 * Generating Tiles
 */
$tile_type      = getval("tltype", "");
$tile['tlsize'] = ('double' === getval('tlsize', '') ? 'double' : '');
$tile_style     = getval("tlstyle", "");
$tile_id        = (isset($usertile)) ? "contents_user_tile" . $usertile["ref"] : "contents_tile" . $tile["ref"];
$tile_width     = getval("tlwidth", ($tile['tlsize'] === 'double' ? 515 : 250), true);
$tile_height    = getval("tlheight", 180, true);

if (!is_numeric($tile_width) || !is_numeric($tile_height) || $tile_width <= 0 || $tile_height <= 0) {
    exit($lang["error-missingtileheightorwidth"]);
}
include "../../include/dash_tile_generation.php";

tile_select($tile_type, $tile_style, $tile, $tile_id, $tile_width, $tile_height);
exit($lang["nodashtilefound"]);

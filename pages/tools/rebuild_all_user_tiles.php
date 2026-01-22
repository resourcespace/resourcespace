<?php

/*
 * Rebuild All User Dashes from existing all user tiles.
 * Before running this tool, use the "manage all user tiles" tool in Admin to get the order as you wish it to be.
 * This WILL NOT delete existing user only tiles
 */

include "../../include/boot.php";

include "../../include/authenticate.php";
if (!checkperm("a")) {
    exit("Permission denied");
}
include "../../include/dash_functions.php";

set_time_limit(0);

//get all of the all_user dash tiles in dash tile.
$tiles = ps_query("SELECT dash_tile.ref AS 'tile',dash_tile.title,dash_tile.url,dash_tile.reload_interval_secs,dash_tile.link,dash_tile.default_order_by as 'order_by' FROM dash_tile WHERE all_users=1 ORDER BY default_order_by");
if (count($tiles) == 0) {
    echo escape($lang["nodashtilefound"]);
    exit;
}

for ($i = count($tiles) - 1; $i >= 0; $i--) {
    $tile = $tiles[$i];
    //Delete Existing of this instance
    ps_query("DELETE FROM user_dash_tile WHERE dash_tile= ?", ['i', $tile["tile"]]);
    //Add TO all Users
    $result = ps_query("INSERT user_dash_tile (user,dash_tile,order_by) SELECT user.ref, ?,5 FROM user", ['i', $tile['tile']]);
}
echo "Done Rebuilding Dash.";

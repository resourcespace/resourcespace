<?php

/*
 * Ajax generation handling for dash tile previews - Montala Ltd, Jethro Dew
 * Separated out into a new file as there is no existing dash tile record to pull information from
 * Content for the tile is sent via ajax to this page. Standard build functions available from include/dash_tile_generation.php
 */

include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/dash_functions.php";

global $userref,$baseurl_short;

$tile_type = getval("tltype", "");
$tile_style = getval("tlstyle", "");
$promoted_image = getval('promimg', '');

$tile                   = array();
$tile['ref']            = getval('edit', '');
$tile['link']           = getval('tllink', '');
$tile['txt']            = getval('tltxt', '');
$tile['title']          = getval('tltitle', '');
$tile['resource_count'] = getval('tlrcount', '');
$tile['tlsize']         = ('double' === getval('tlsize', '') ? 'double' : '');

// Simulate URL so we can preview based on requested params
$tile['url'] = generateURL(
    'pages/ajax/dash_tile_preview.php',
    [
        'tltype' => $tile_type,
        'tlsize' => $tile['tlsize'],
        'tlstyle' => $tile_style,
        'promimg' => $promoted_image,
    ]
);
$tile_id = "previewdashtile";
$tile_width = getval("tlwidth", ($tile['tlsize'] === 'double' ? 515 : 250), true);
$tile_height = getval("tlheight", 180, true);
if (!is_numeric($tile_width) || !is_numeric($tile_height) || $tile_width <= 0 || $tile_height <= 0) {
    exit($lang["error-missingtileheightorwidth"]);
}
include "../../include/dash_tile_generation.php";
tile_select($tile_type, $tile_style, $tile, $tile_id, $tile_width, $tile_height);
exit($lang["nodashtilefound"]);

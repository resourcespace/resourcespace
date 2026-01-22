<?php

command_line_only();

include_once __DIR__ . "/../../include/image_processing.php";

$tileresource = create_resource(1, 0);
$resource_path = get_resource_path($tileresource, true, '');
copy(__DIR__ . "/../../gfx/homeanim/1.jpg", $resource_path);

// Create previews
$preview_tiles = true;
$preview_tiles_create_auto = true;
$preview_tile_size = 256;
$preview_tile_scale_factors = array(1,2,4);
create_previews($tileresource, false, "jpg", false, false, -1, true, false, false, ["tiles"]);

$tile_checks = array();
$tile_checks[] = get_resource_path($tileresource, true, 'tile_1_0_0_256_256');
$tile_checks[] = get_resource_path($tileresource, true, 'tile_2_0_0_512_512');
$tile_checks[] = get_resource_path($tileresource, true, 'tile_4_0_0_1024_1024');

foreach ($tile_checks as $tile_check) {
    if (!file_exists($tile_check)) {
        echo "Failed to create preview tile. ";
        return false;
    }
}
$saved_resource_deletion_state = $resource_deletion_state;
unset($resource_deletion_state);
delete_resource($tileresource);
$resource_deletion_state = $saved_resource_deletion_state;
return true;

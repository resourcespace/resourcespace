<?php

command_line_only();

$saved_userref      = $userref;
$savedpermissions   = $userpermissions;
$hide_real_filepath_saved = $GLOBALS["hide_real_filepath"];
$valid_upload_paths_saved = $GLOBALS["valid_upload_paths"];
$enable_thumbnail_creation_on_upload_saved = $enable_thumbnail_creation_on_upload;
$resource_deletion_state_saved = $GLOBALS["resource_deletion_state"] ?? null;
$userref = new_user("user001170");

// Create resources
$col_data_type = create_resource_type("Collection data");
$col_image_type = create_resource_type("Collection images");
$imgresource = create_resource($col_image_type, 0);
$noimgresource = create_resource($col_data_type, 0);

// Test A - ensure that preview images are 'pulled' from a related resource if configured
$GLOBALS["hide_real_filepath"] = false;
$enable_thumbnail_creation_on_upload = false; // Don't need previews here
$GLOBALS["valid_upload_paths"][] = realpath(__DIR__ . "/../../");
save_resource_type($col_data_type, ["pull_images" => true]);
relate_all_resources([$imgresource,$noimgresource]);

// Add file to image resource and get URL
$imgresource_path = get_resource_path($imgresource, true, 'pre');
upload_file($imgresource, true, false, false, __DIR__ . "/../../gfx/watermark.png", false, false);
$imgresource_url = get_resource_path($imgresource, false, 'pre', false, 'png', true, 1, false, '', -1, false);
$noimgresource_url = get_resource_path($noimgresource, false, 'pre', false, 'png', true, 1, false, '', -1, false);
if ($imgresource_url !== $noimgresource_url) {
    echo "Test A: Failed to pull image from related resource. ";
    return false;
}

// Test B - ensure that images are not pulled from a related resource when pull_images = 0 for the resource type
save_resource_type($col_data_type, ["pull_images" => false]);
$noimgresource_url = get_resource_path($noimgresource, false, 'pre', false, 'png', true, 1, false, '', -1, false);
if ($imgresource_url === $noimgresource_url) {
    echo "Test B: Failed - pull_images = 0";
    return false;
}

// Test C - ensure that images are not pulled from an invalid related resource e.g. one that does not have a preview
save_resource_type($col_data_type, ["pull_images" => true]);
unset($GLOBALS["resource_deletion_state"]);
delete_resource($imgresource);
$newrelatedresource = create_resource($col_image_type, 0);
$noimgresource_url = get_resource_path($noimgresource, false, 'pre', false, 'png', true, 1, false, '', -1, false);
$relatedresource_url = get_resource_path($newrelatedresource, false, 'pre', false, 'png', true, 1, false, '', -1, false);
if ($noimgresource_url === $relatedresource_url || $imgresource_url === $noimgresource_url) {
    echo "Test C: Failed - pulled an image from invalid related resource";
    return false;
}

// Restore state
$userref = $saved_userref;
$userpermissions = $savedpermissions;

$GLOBALS["hide_real_filepath"] = $hide_real_filepath_saved;
$GLOBALS["valid_upload_paths"] = $valid_upload_paths_saved;
$enable_thumbnail_creation_on_upload = $enable_thumbnail_creation_on_upload_saved;
if (!is_null($resource_deletion_state_saved)) {
    $GLOBALS["resource_deletion_state"] = $resource_deletion_state_saved;
}

return true;

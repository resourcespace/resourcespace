<?php

include_once __DIR__ . "/../../include/boot.php";

$k = trim(getval("k", ""));
$parent = (int) getval("parent", $featured_collections_root_collection, true);

// Match the main Featured Collections page authentication contract, including access-key shares.
if ($k === "" || !check_access_key_collection($parent, $k)) {
    include_once __DIR__ . "/../../include/authenticate.php";
    $parent = (int) getval("parent", $featured_collections_root_collection, true);
} else {
    $CSRF_enabled = false;
}

if (!$enable_themes) {
    http_response_code(403);
    exit($lang["error-permissiondenied"]);
}

if ($parent > 0 && !featured_collection_check_access_control($parent)) {
    http_response_code(403);
    exit($lang["error-permissiondenied"]);
}

$smart_rtf = (int) getval("smart_rtf", 0, true);
$smart_fc_parent_value = (int) getval("smart_fc_parent", 0, true);
$smart_fc_parent = $smart_fc_parent_value > 0 ? $smart_fc_parent_value : null;
$offset = max(0, (int) getval("offset", 0, true));
$batch_target = max(0, (int) $featured_collection_tiles_per_batch_target);
$columns = min(12, max(1, (int) getval("columns", 1, true)));
$fill_row = getval("fill_row", "", true) === "1";

if ($smart_rtf > 0 && !metadata_field_view_access($smart_rtf)) {
    http_response_code(403);
    exit($lang["error-permissiondenied"]);
}

$tile_data = get_featured_collection_tile_data($parent, $smart_rtf, $smart_fc_parent);
$batch_limit = get_featured_collection_row_aware_batch_limit(
    $offset,
    $batch_target,
    $columns,
    $fill_row
);

// A stale resize reconciliation can arrive after another request has completed the row. Return an empty batch rather
// than interpreting the zero row-fill limit as the established "batching disabled" value.
if ($fill_row && $batch_limit === 0) {
    $total = count($tile_data["featured"]) + count($tile_data["smart"]);
    $offset = min($offset, $total);
    $tile_batch = [
        "featured" => [],
        "smart" => [],
        "offset" => $offset,
        "has_more" => $offset < $total,
        "total" => $total,
    ];
} else {
    $tile_batch = get_featured_collection_tile_batch($tile_data, $offset, $batch_limit);
}
$general_url_params = $k === "" ? [] : ["k" => $k];
$rendering_options = [
    "general_url_params" => $general_url_params,
    "all_fcs" => get_all_featured_collections(),
    "reorder" => $allow_fc_reorder && can_reorder_featured_collections(),
];

ob_start();
render_featured_collections($rendering_options, $tile_batch["featured"]);

$smart_rendering_options = $rendering_options;
$smart_rendering_options["smart"] = true;
$smart_rendering_options["reorder"] = false;
render_featured_collections($smart_rendering_options, $tile_batch["smart"]);
$html = ob_get_clean();

header("Content-Type: application/json");
echo json_encode(
    [
        "html" => $html,
        "offset" => $tile_batch["offset"],
        "has_more" => $tile_batch["has_more"],
    ],
    JSON_INVALID_UTF8_SUBSTITUTE
);

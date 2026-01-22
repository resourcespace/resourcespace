<?php

command_line_only();


include_once __DIR__ . '/../../include/image_processing.php';

// Setup
$preview_tile_size_cache = $preview_tile_size;
$preview_tile_scale_factors_cache = $preview_tile_scale_factors;

$preview_tile_size = 1024;
$preview_tile_scale_factors = [1, 2, 4, 8];
$source_width = 5616;
$source_height = 3744;

// Request tiles for a scale factor bigger than the source. Expect an empty array - ie. unable to generate tiles
$tiles = compute_tiles_at_scale_factor(8, $source_width, $source_height);
if (!empty($tiles)) {
    echo 'Tile region greater than source - ';
    return false;
}


// Request tiles for a scale factor of 4. Expect 2 tiles (1 row, 2 columns)
$tiles = compute_tiles_at_scale_factor(4, $source_width, $source_height);
$expected_tile_ids = [
    'tile_4_0_0_4096_3744',    # row 0, col 0
    'tile_4_4096_0_1520_3744', # row 0, col 1
];
if ($expected_tile_ids !== array_column($tiles, 'id')) {
    echo 'Scale factor 4 (1 row, 2 columns) - ';
    return false;
}


// Request tiles for a scale factor of 2. Expect 6 tiles (2 rows, 3 columns)
$tiles = compute_tiles_at_scale_factor(2, $source_width, $source_height);
$expected_tile_ids = [
    'tile_2_0_0_2048_2048',       # row 0, col 0
    'tile_2_2048_0_2048_2048',    # row 0, col 1
    'tile_2_4096_0_1520_2048',    # row 0, col 2
    'tile_2_0_2048_2048_1696',    # row 1, col 0
    'tile_2_2048_2048_2048_1696', # row 1, col 1
    'tile_2_4096_2048_1520_1696', # row 1, col 2
];
if ($expected_tile_ids !== array_column($tiles, 'id')) {
    echo 'Scale factor 2 (2 rows, 3 columns) - ';
    return false;
}


// Request tiles for a scale factor of 1. Expect 24 tiles (4 rows, 6 columns)
$tiles = compute_tiles_at_scale_factor(1, $source_width, $source_height);
$expected_tile_ids = [
    'tile_1_0_0_1024_1024',       # row 0, col 0
    'tile_1_1024_0_1024_1024',    # row 0, col 1
    'tile_1_2048_0_1024_1024',    # row 0, col 2
    'tile_1_3072_0_1024_1024',    # row 0, col 3
    'tile_1_4096_0_1024_1024',    # row 0, col 4
    'tile_1_5120_0_496_1024',     # row 0, col 5

    'tile_1_0_1024_1024_1024',    # row 1, col 0
    'tile_1_1024_1024_1024_1024', # row 1, col 1
    'tile_1_2048_1024_1024_1024', # row 1, col 2
    'tile_1_3072_1024_1024_1024', # row 1, col 3
    'tile_1_4096_1024_1024_1024', # row 1, col 4
    'tile_1_5120_1024_496_1024',  # row 1, col 5

    'tile_1_0_2048_1024_1024',    # row 2, col 0
    'tile_1_1024_2048_1024_1024', # row 2, col 1
    'tile_1_2048_2048_1024_1024', # row 2, col 2
    'tile_1_3072_2048_1024_1024', # row 2, col 3
    'tile_1_4096_2048_1024_1024', # row 2, col 4
    'tile_1_5120_2048_496_1024',  # row 2, col 5

    'tile_1_0_3072_1024_672',    # row 3, col 0
    'tile_1_1024_3072_1024_672', # row 3, col 1
    'tile_1_2048_3072_1024_672', # row 3, col 2
    'tile_1_3072_3072_1024_672', # row 3, col 3
    'tile_1_4096_3072_1024_672', # row 3, col 4
    'tile_1_5120_3072_496_672',  # row 3, col 5
];
if ($expected_tile_ids !== array_column($tiles, 'id')) {
    echo 'Scale factor 1 (4 rows, 6 columns) - ';
    return false;
}


// Test DZI compliance. At a scale factor of 2, expect 6 tiles (2 rows, 3 columns) starting from 0,0 and ending at 1,2.
$tiles = compute_tiles_at_scale_factor(2, $source_width, $source_height);
$start_tile_region = false;
$end_tile_region = false;
foreach ($tiles as $tile) {
    if ($tile['row'] === 0 && $tile['column'] === 0) {
        $start_tile_region = true;
    } elseif ($tile['row'] === 1 && $tile['column'] === 2) {
        $end_tile_region = true;
    }
}
if (!($start_tile_region && $end_tile_region)) {
    echo 'DZI compliance at scale factor 2 - ';
    return false;
}



// Tear down
$preview_tile_size = $preview_tile_size_cache;
$preview_tile_scale_factors = $preview_tile_scale_factors_cache;
unset(
    $source_width,
    $source_height,
    $tiles,
    $expected_tile_ids,
    $start_tile_region,
    $end_tile_region,
    $preview_tile_size_cache,
    $preview_tile_scale_factors_cache
);

return true;

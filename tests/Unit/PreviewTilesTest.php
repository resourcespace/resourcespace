<?php

declare(strict_types=1);

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$saved_tile_settings = [];
beforeEach(function () use (&$saved_tile_settings): void {
    $saved_tile_settings = array_intersect_key($GLOBALS, ['preview_tile_size' => true, 'preview_tile_scale_factors' => true]);
    $GLOBALS['preview_tile_size'] = 1024;
    $GLOBALS['preview_tile_scale_factors'] = [1, 2, 4, 8];
});
afterEach(function () use (&$saved_tile_settings): void {
    unset($GLOBALS['preview_tile_size']);
    unset($GLOBALS['preview_tile_scale_factors']);
    foreach ($saved_tile_settings as $key => $value) {
        $GLOBALS[$key] = $value;
    }
});

it('computes the expected tile regions, including incomplete edges', function (int $scale, array $expected): void {
    expect(array_column(compute_tiles_at_scale_factor($scale, 5616, 3744), 'id'))->toBe($expected);
})->with([
    'scale larger than image' => [8, []],
    'scale 4' => [4, [
        'tile_4_0_0_4096_3744', 'tile_4_4096_0_1520_3744',
    ]],
    'scale 2' => [2, [
        'tile_2_0_0_2048_2048', 'tile_2_2048_0_2048_2048', 'tile_2_4096_0_1520_2048',
        'tile_2_0_2048_2048_1696', 'tile_2_2048_2048_2048_1696', 'tile_2_4096_2048_1520_1696',
    ]],
    'scale 1' => [1, [
        'tile_1_0_0_1024_1024', 'tile_1_1024_0_1024_1024', 'tile_1_2048_0_1024_1024',
        'tile_1_3072_0_1024_1024', 'tile_1_4096_0_1024_1024', 'tile_1_5120_0_496_1024',
        'tile_1_0_1024_1024_1024', 'tile_1_1024_1024_1024_1024', 'tile_1_2048_1024_1024_1024',
        'tile_1_3072_1024_1024_1024', 'tile_1_4096_1024_1024_1024', 'tile_1_5120_1024_496_1024',
        'tile_1_0_2048_1024_1024', 'tile_1_1024_2048_1024_1024', 'tile_1_2048_2048_1024_1024',
        'tile_1_3072_2048_1024_1024', 'tile_1_4096_2048_1024_1024', 'tile_1_5120_2048_496_1024',
        'tile_1_0_3072_1024_672', 'tile_1_1024_3072_1024_672', 'tile_1_2048_3072_1024_672',
        'tile_1_3072_3072_1024_672', 'tile_1_4096_3072_1024_672', 'tile_1_5120_3072_496_672',
    ]],
]);

it('assigns zero-based Deep Zoom tile positions', function (): void {
    $tiles = compute_tiles_at_scale_factor(2, 5616, 3744);
    expect(array_column($tiles, 'row'))->toBe([0, 0, 0, 1, 1, 1])
        ->and(array_column($tiles, 'column'))->toBe([0, 1, 2, 0, 1, 2]);
});

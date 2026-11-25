<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/include/collections_functions.php';

it('batches normal and smart featured collection tiles as one ordered list', function (): void {
    $tile_data = [
        'featured' => [['ref' => 10], ['ref' => 20]],
        'smart' => [['ref' => 30]],
    ];

    $first_batch = get_featured_collection_tile_batch($tile_data, 0, 1);
    expect(array_column($first_batch['featured'], 'ref'))->toBe([10])
        ->and($first_batch['smart'])->toBe([])
        ->and($first_batch['offset'])->toBe(1)
        ->and($first_batch['has_more'])->toBeTrue()
        ->and($first_batch['total'])->toBe(3);

    $boundary_batch = get_featured_collection_tile_batch($tile_data, 1, 2);
    expect(array_column($boundary_batch['featured'], 'ref'))->toBe([20])
        ->and(array_column($boundary_batch['smart'], 'ref'))->toBe([30])
        ->and($boundary_batch['offset'])->toBe(3)
        ->and($boundary_batch['has_more'])->toBeFalse()
        ->and($boundary_batch['total'])->toBe(3);
});

it('returns every remaining tile when the batch limit is zero', function (): void {
    $tile_data = [
        'featured' => [['ref' => 10], ['ref' => 20]],
        'smart' => [['ref' => 30]],
    ];

    $batch = get_featured_collection_tile_batch($tile_data, 0, 0);

    expect(array_column($batch['featured'], 'ref'))->toBe([10, 20])
        ->and(array_column($batch['smart'], 'ref'))->toBe([30])
        ->and($batch['offset'])->toBe(3)
        ->and($batch['has_more'])->toBeFalse()
        ->and($batch['total'])->toBe(3);
});

it('calculates a row-aware server batch limit', function (
    int $offset,
    int $target,
    int $columns,
    bool $fill_row,
    int $expected,
): void {
    expect(get_featured_collection_row_aware_batch_limit($offset, $target, $columns, $fill_row))
        ->toBe($expected);
})->with([
    'first one-tile target completes a three-column row' => [0, 1, 3, false, 3],
    'partial row is completed after one loaded tile' => [1, 1, 3, false, 2],
    'later partial row is completed' => [7, 1, 3, false, 2],
    'resize requests only the missing cells' => [9, 1, 4, true, 3],
    'resize does not request an already complete row' => [8, 1, 4, true, 0],
    'large target extends to the next row boundary' => [100, 100, 3, false, 101],
    'zero target disables batching' => [0, 0, 4, false, 0],
]);

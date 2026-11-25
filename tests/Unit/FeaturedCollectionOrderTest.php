<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/include/collections_functions.php';

it('orders featured collections from explicit positions, category precedence and natural names', function (
    array $collections,
    array $expected,
    bool $descending = false,
): void {
    $previous = $GLOBALS['descthemesorder'] ?? null;
    $GLOBALS['descthemesorder'] = $descending;
    try {
        $records = array_map(
            static fn (array $entry): array => array_combine(['name', 'order_by', 'has_resources'], $entry),
            $collections
        );
        usort($records, 'order_featured_collections');
        expect(array_column($records, 'name'))->toBe($expected);
    } finally {
        if ($previous === null) {
            unset($GLOBALS['descthemesorder']);
        } else {
            $GLOBALS['descthemesorder'] = $previous;
        }
    }
})->with([
    'ordering explicitly positioned entries' => [[['B', 40, 0], ['A', 30, 0], ['C', 50, 0]], ['A', 'B', 'C']],
    'descending positions' => [[['B', 40, 0], ['A', 30, 0], ['C', 50, 0]], ['C', 'B', 'A'], true],
    'categories then names' => [
        [['A collection', 0, 1], ['Category 10', 0, 0], ['Category 2', 0, 0]],
        ['Category 2', 'Category 10', 'A collection'],
    ],
    'starred names precede unstarred names in each group' => [
        [['Category', 0, 0], ['*Category', 0, 0], ['Collection', 0, 1], ['*Collection', 0, 1]],
        ['*Category', 'Category', '*Collection', 'Collection'],
    ],
    'default positions fall back to category and name' => [
        [['B', 40, 0], ['A', 30, 0], ['C', 50, 0], ['D', 0, 0], ['Axis', 0, 0], ['X', 10, 1]],
        ['A', 'Axis', 'B', 'C', 'D', 'X'],
    ],
]);

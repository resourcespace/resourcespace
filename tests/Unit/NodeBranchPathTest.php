<?php

declare(strict_types=1);

$tree = [
    ['ref' => 1, 'parent' => null, 'name' => 'A'],
    ['ref' => 2, 'parent' => 1, 'name' => 'B'],
    ['ref' => 5, 'parent' => 2, 'name' => 'D'],
    ['ref' => 3, 'parent' => 1, 'name' => 'C'],
    ['ref' => 4, 'parent' => 1, 'name' => 'E'],
    ['ref' => 6, 'parent' => 4, 'name' => 'F'],
];

it('returns the ordered ancestors of a node, including the node itself', function (
    int $ref,
    array $expected
) use ($tree): void {
    expect(array_column(compute_node_branch_path($tree, $ref), 'name'))->toBe($expected);
})->with([
    'root' => [1, ['A']],
    'first branch' => [2, ['A', 'B']],
    'second branch' => [4, ['A', 'E']],
    'leaf in the first branch' => [5, ['A', 'B', 'D']],
    'leaf in the second branch' => [6, ['A', 'E', 'F']],
    'unknown node' => [2342, []],
]);

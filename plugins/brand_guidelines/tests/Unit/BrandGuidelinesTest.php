<?php

declare(strict_types=1);

use function Montala\ResourceSpace\Plugins\BrandGuidelines\group_content_items;

require_once dirname(__DIR__, 2) . '/include/brand_guidelines_functions.php';

beforeAll(function (): void {
    // The plugin normally defines these during its HookBrand_guidelinesAllInitialise hook.
    if (!defined('BRAND_GUIDELINES_CONTENT_TYPES')) {
        define('BRAND_GUIDELINES_CONTENT_TYPES', [
            'text' => 0,
            'resource' => 1,
            'colour' => 2,
            'group' => 100,
        ]);
    }
});

test('groups Brand Guidelines content in display order', function (array $content, array $expected): void {
    $types = BRAND_GUIDELINES_CONTENT_TYPES;
    $items = [];
    foreach ($content as $ref => $kind) {
        $is_resource = in_array($kind, ['thumbnail', 'half-width', 'full-width'], true);
        $items[$ref] = [
            'ref' => $ref,
            'type' => $types[$is_resource ? 'resource' : $kind],
            'content' => $is_resource ? ['layout' => $kind] : [],
        ];
    }

    $expected_items = array_map(
        static fn (int|array $block): array => (
            is_int($block)
                ? $items[$block]
                : [
                    'type' => $types['group'],
                    'members' => array_map(static fn (int $ref): array => $items[$ref], $block),
                ]
        ),
        $expected,
    );

    expect(group_content_items($items))->toBe($expected_items);
})->with([
    'empty page' => [[], []],
    'adjacent colours' => [
        [34 => 'colour', 12 => 'colour', 56 => 'colour'],
        [[34, 12, 56]],
    ],
    'adjacent thumbnails' => [
        [34 => 'thumbnail', 12 => 'thumbnail', 56 => 'thumbnail'],
        [[34, 12, 56]],
    ],
    'three half-width resources form a pair and a single-item group' => [
        [34 => 'half-width', 12 => 'half-width', 56 => 'half-width'],
        [[34, 12], [56]],
    ],
    'text breaks a run of colours' => [
        [34 => 'colour', 12 => 'text', 56 => 'colour'],
        [[34], 12, [56]],
    ],
    'full-width resources stay standalone and break a run of thumbnails' => [
        [34 => 'thumbnail', 12 => 'full-width', 56 => 'full-width', 78 => 'thumbnail'],
        [[34], 12, 56, [78]],
    ],
    'adjacent resources with different layouts stay in separate groups' => [
        [34 => 'thumbnail', 12 => 'half-width', 56 => 'half-width', 78 => 'thumbnail'],
        [[34], [12, 56], [78]],
    ],
]);

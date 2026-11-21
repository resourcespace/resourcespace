<?php

declare(strict_types=1);

use function Montala\ResourceSpace\Plugins\BrandGuidelines\create_content_item;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\create_page;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\delete_pages;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\get_page_contents;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\reorder_page_content;

$previous_plugins = [];
$enabled_plugin = false;

beforeAll(function () use (&$previous_plugins, &$enabled_plugin): void {
    $previous_plugins = $GLOBALS['plugins'];
    if (!is_plugin_activated('brand_guidelines')) {
        $enabled_plugin = activate_plugin('brand_guidelines');
        expect($enabled_plugin)->toBeTrue();
    }

    if (!in_array('brand_guidelines', $GLOBALS['plugins'], true)) {
        $GLOBALS['plugins'][] = 'brand_guidelines';
    }

    CheckDBStruct('plugins/brand_guidelines/dbstruct');
    if (!defined('BRAND_GUIDELINES_DB_COLS_CONTENT')) {
        require_once dirname(__DIR__, 2) . '/hooks/all.php';
        HookBrand_guidelinesAllInitialise();
    }
});

afterEach(function (): void {
    if (!empty($this->page_refs)) {
        expect(delete_pages($this->page_refs))->toBeTrue();
    }
});

afterAll(function () use (&$previous_plugins, &$enabled_plugin): void {
    if ($enabled_plugin) {
        deactivate_plugin('brand_guidelines');
    }
    $GLOBALS['plugins'] = $previous_plugins;
});

test('moving a whole colour group up and down preserves its display order', function (bool $swap_members): void {
    $this->page_refs = [];
    $prefix = 'Guidelines reorder ' . generateRandomTestID(16);
    $section = create_page($prefix . ' section', 0);
    expect($section)->toBeGreaterThan(0);
    $this->page_refs[] = $section;
    $page = create_page($prefix . ' page', $section);
    expect($page)->toBeGreaterThan(0);
    $this->page_refs[] = $page;

    $add_item = static function (string $type, array $values) use ($page): int {
        $fields = [];
        foreach ($values as $id => $value) {
            $fields[] = ['id' => $id, 'type' => FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'value' => $value];
        }
        $ref = create_content_item($page, [
            'type' => BRAND_GUIDELINES_CONTENT_TYPES[$type],
            'fields' => $fields,
            'position_after' => 0,
        ]);
        expect($ref)->toBeGreaterThan(0);

        return $ref;
    };

    $intro = $add_item('text', ['richtext' => '<p>Introduction</p>']);
    $usage = $add_item('text', ['richtext' => '<p>Usage</p>']);
    $red = $add_item('colour', ['name' => 'Red', 'hex' => 'FF0000', 'rgb' => '255,0,0', 'cmyk' => '0,100,100,0']);
    $green = $add_item('colour', [
        'name' => 'Green',
        'hex' => '00FF00',
        'rgb' => '0,255,0',
        'cmyk' => '100,0,100,0',
    ]);
    $blue = $add_item('colour', ['name' => 'Blue', 'hex' => '0000FF', 'rgb' => '0,0,255', 'cmyk' => '100,100,0,0']);
    $notes = $add_item('text', ['richtext' => '<p>Notes</p>']);

    $members = [$red, $green, $blue];
    if ($swap_members) {
        $contents = array_column(get_page_contents($page), null, 'ref');
        expect(reorder_page_content('down', $contents, [$red]))->toBeTrue();
        $members = [$green, $red, $blue];
    }

    $contents = array_column(get_page_contents($page), null, 'ref');
    expect(array_keys($contents))->toBe([$intro, $usage, ...$members, $notes]);

    expect(reorder_page_content('up', $contents, $members))->toBeTrue();

    $contents = array_column(get_page_contents($page), null, 'ref');
    expect(array_keys($contents))->toBe([$intro, ...$members, $usage, $notes]);

    expect(reorder_page_content('down', $contents, $members))->toBeTrue();
    expect(array_column(get_page_contents($page), 'ref'))->toBe([$intro, $usage, ...$members, $notes]);
})->with([
    'members in creation order' => [false],
    'members in a different display order' => [true],
]);

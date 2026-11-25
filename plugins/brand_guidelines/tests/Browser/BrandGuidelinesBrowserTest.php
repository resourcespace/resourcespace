<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;

use function Montala\ResourceSpace\Plugins\BrandGuidelines\create_content_item;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\create_page;
use function Montala\ResourceSpace\Plugins\BrandGuidelines\delete_pages;

$guidelines_fixture = new class () {
    public string $prefix;
    public string $guidelinesUrl;
    public int|false|null $resource = null;
};

$previous_plugins = [];
$enabled_plugin = false;

beforeAll(function () use (&$previous_plugins, &$enabled_plugin): void {
    $previous_plugins = $GLOBALS['plugins'];
    $enabled_plugin = !is_plugin_activated('brand_guidelines');
    if ($enabled_plugin) {
        expect(activate_plugin('brand_guidelines'))->toBeTrue();
    }

    if (!in_array('brand_guidelines', $GLOBALS['plugins'], true)) {
        $GLOBALS['plugins'][] = 'brand_guidelines';
    }
    CheckDBStruct('plugins/brand_guidelines/dbstruct');
    if (!defined('BRAND_GUIDELINES_CONTENT_TYPES')) {
        require_once dirname(__DIR__, 2) . '/hooks/all.php';
        HookBrand_guidelinesAllInitialise();
    }
});

beforeEach(function () use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $guidelines_fixture->prefix = 'Guidelines ' . generateRandomTestID(16);
    $guidelines_fixture->guidelinesUrl = $this->browserUrl('/plugins/brand_guidelines/pages/guidelines.php');
    $guidelines_fixture->resource = null;
});

afterEach(function () use ($guidelines_fixture): void {
    $refs = ps_array('SELECT ref AS value FROM brand_guidelines_pages WHERE name LIKE ?', ['s', $guidelines_fixture->prefix . ' %']);
    $pages_deleted = $refs === [] || delete_pages(array_map('intval', $refs));

    $resource_deleted = true;
    if (is_int($guidelines_fixture->resource)) {
        $deletion_state = $GLOBALS['resource_deletion_state'];
        $GLOBALS['resource_deletion_state'] = null;
        try {
            $resource_deleted = delete_resource($guidelines_fixture->resource);
        } finally {
            $GLOBALS['resource_deletion_state'] = $deletion_state;
        }
    }

    expect($pages_deleted)->toBeTrue()->and($resource_deleted)->toBeTrue();
});

afterAll(function () use (&$previous_plugins, &$enabled_plugin): void {
    if ($enabled_plugin) {
        deactivate_plugin('brand_guidelines');
    }
    $GLOBALS['plugins'] = $previous_plugins;
});

/**
 * Create an empty guidelines page for a content workflow.
 *
 * @param string $prefix Unique prefix shared by the fixture records for cleanup
 * @return int Page reference
 */
function createBrandGuidelinesTestPage(string $prefix): int
{
    $section = create_page($prefix . ' section', 0);
    expect($section)->toBeGreaterThan(0);
    $page = create_page($prefix . ' page', $section);
    expect($page)->toBeGreaterThan(0);

    return $page;
}

/**
 * Seed a text or colour item for tests that exercise moving content.
 *
 * @param int $page Page reference
 * @param string $type Content type name
 * @param array<string, string> $values Field values keyed by field ID
 * @return int Content item reference
 */
function createBrandGuidelinesContentFixture(int $page, string $type, array $values): int
{
    $fields = [];
    foreach ($values as $id => $value) {
        $fields[] = ['id' => $id, 'type' => FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'value' => $value];
    }
    $item = create_content_item($page, [
        'type' => BRAND_GUIDELINES_CONTENT_TYPES[$type],
        'fields' => $fields,
        'position_after' => 0,
    ]);
    expect($item)->toBeGreaterThan(0);

    return $item;
}

/**
 * Use an item's contextual menu and wait for its modal or updated guidelines.
 *
 * @param BrowserPage $page Current guidelines browser session
 * @param string $item Selector for the row, content item or group
 * @param string $icon Menu action icon class
 */
function brandGuidelinesItemAction(BrowserPage $page, string $item, string $icon): void
{
    if ($icon === 'icon-trash-2') {
        // Pest's browser API auto-dismisses native dialogs. Accept this confirmation once.
        $page->script(<<<'JS'
            (() => {
                const confirm = window.confirm;
                window.confirm = () => {
                    window.confirm = confirm;
                    return true;
                };
            })()
        JS);
    }

    $page
        ->hover($item)
        ->click($item . ' > .top-right-menu')
        ->click("#menu-individual button:has(> i.{$icon})")
        ->waitForAjax();
}

/**
 * Verify the order of test sections and pages as displayed in the table of contents.
 *
 * @param BrowserPage $page Current guidelines browser session
 * @param string $prefix Unique prefix for this test's names
 * @param list<string> $names Expected labels without the prefix
 */
function assertBrandGuidelinesToc(BrowserPage $page, string $prefix, array $names): void
{
    $js_prefix = encode_js_value($prefix . ' ');
    $page->assertScript(<<<JS
        [...document.querySelectorAll('.guidelines-sidebar li > h2, .guidelines-sidebar li > a')]
            .map(item => item.textContent.trim())
            .filter(label => label.startsWith({$js_prefix}))
            .map(label => label.slice({$js_prefix}.length))
    JS, $names);
}

test('adds, renames, moves and deletes Brand Guidelines sections and pages', function () use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    // Pest detects browser tests outside tests/Browser by the visit() call in the test body.
    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $page->openAndWaitFor($guidelines_fixture->guidelinesUrl, '#toggle-view-edit-mode')->click('#toggle-view-edit-mode');
    $prefix = $guidelines_fixture->prefix;
    $section_row = static fn (string $name): string => (
        '.guidelines-sidebar li:has(> h2:text-is(' . encode_js_value("{$prefix} {$name}") . '))'
    );
    $page_row = static fn (string $name): string => (
        '.guidelines-sidebar li:has(> a:text-is(' . encode_js_value("{$prefix} {$name}") . '))'
    );
    $save = static fn (string $name) => $page
        ->fill('#manage_toc #name', "{$prefix} {$name}")
        ->click('#manage_toc input[name="toc_submit"]')
        ->waitForAjax();

    foreach (['Original', 'Second'] as $name) {
        $page->click('.guidelines-sidebar h2 > a.new')->waitForAjax();
        $save($name);
    }
    brandGuidelinesItemAction($page, $section_row('Original'), 'icon-square-pen');
    $save('First');
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Second']);

    foreach (['Overview', 'Examples'] as $name) {
        $page->click($section_row('First') . ' ~ li > a.new >> nth=0')->waitForAjax();
        $save($name);
        $page->assertSeeIn('.guidelines-content h1', "{$prefix} {$name}");
    }

    brandGuidelinesItemAction($page, $page_row('Examples'), 'icon-chevron-up');
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Examples', 'Overview', 'Second']);
    brandGuidelinesItemAction($page, $page_row('Examples'), 'icon-chevron-down');
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Overview', 'Examples', 'Second']);

    brandGuidelinesItemAction($page, $page_row('Examples'), 'icon-square-pen');
    $page->select('#manage_toc #parent', "{$prefix} Second");
    $save('Usage');
    $page->assertSeeIn('.guidelines-content h1', "{$prefix} Usage");
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Overview', 'Second', 'Usage']);

    brandGuidelinesItemAction($page, $section_row('Second'), 'icon-chevron-up');
    assertBrandGuidelinesToc($page, $prefix, ['Second', 'Usage', 'First', 'Overview']);
    brandGuidelinesItemAction($page, $section_row('Second'), 'icon-chevron-down');
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Overview', 'Second', 'Usage']);

    brandGuidelinesItemAction($page, $page_row('Overview'), 'icon-trash-2');
    assertBrandGuidelinesToc($page, $prefix, ['First', 'Second', 'Usage']);
    brandGuidelinesItemAction($page, $section_row('Second'), 'icon-trash-2');
    assertBrandGuidelinesToc($page, $prefix, ['First']);
    expect(ps_array(
        'SELECT name AS value FROM brand_guidelines_pages WHERE name LIKE ?',
        ['s', $prefix . ' %'],
    ))->toBe(["{$prefix} First"]);
});

test('adds, edits, renders and deletes rich text', function () use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $ref = createBrandGuidelinesTestPage($guidelines_fixture->prefix);
    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $editor = '#manage_content iframe#richtext_ifr';
    $item = '.guidelines-content div[id^="page-content-item-"]';
    $page
        ->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '#toggle-view-edit-mode')
        ->click('#toggle-view-edit-mode')
        ->click('#add-new-content-end')
        ->click('#menu-content button:has(> .icon-text-align-start)')
        ->waitForAjax();

    $page->withinFrame($editor, static function ($frame): void {
        $frame->fill('body#tinymce', 'Use the primary logo');
    });
    $page
        ->click('#manage_content input[name="content_submit"]')
        ->waitForAjax()
        ->assertSeeIn($item . ' .rich-text-content', 'Use the primary logo');

    brandGuidelinesItemAction($page, $item, 'icon-square-pen');
    $page->withinFrame($editor, static function ($frame): void {
        $frame
            ->assertSee('Use the primary logo')
            ->fill('body#tinymce', 'Keep the clear space')
            ->keys('body#tinymce', ['Control+a', 'Control+b']);
    });
    $page
        ->click('#manage_content input[name="content_submit"]')
        ->waitForAjax()
        ->assertSeeIn($item . ' .rich-text-content strong', 'Keep the clear space')
        ->assertDontSee('Use the primary logo');

    $page
        ->click('#toggle-view-edit-mode')
        ->waitForAjax()
        ->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '#toggle-view-edit-mode')
        ->assertVisible($item . ' .rich-text-content strong')
        ->click('#toggle-view-edit-mode');
    brandGuidelinesItemAction($page, $item, 'icon-trash-2');
    $page->assertNotPresent($item)->assertVisible('#add-new-content-end');
});

test('adds, edits, renders and deletes a colour swatch', function () use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $ref = createBrandGuidelinesTestPage($guidelines_fixture->prefix);
    $item = '.guidelines-content .group > .guidelines-colour-block[id]';
    $swatch = $item . ' .guidelines-colour-block--colour';
    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $page
        ->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '#toggle-view-edit-mode')
        ->click('#toggle-view-edit-mode')
        ->click('#add-new-content-end')
        ->click('#menu-content button:has(> .icon-palette)')
        ->waitForAjax()
        ->fill('#manage_content #name', 'Primary red')
        ->fill('#manage_content #hex', 'FF0000')
        ->click('#manage_content #name')
        ->assertValue('#manage_content #rgb', '255,0,0')
        ->assertValue('#manage_content #cmyk', '0,100,100,0')
        ->click('#manage_content input[name="content_submit"]')
        ->waitForAjax()
        ->assertSeeIn($item, 'Primary red')
        ->assertSeeIn($item, '#FF0000')
        ->assertSeeIn($item, '255,0,0')
        ->assertSeeIn($item, '0,100,100,0')
        ->assertScript("getComputedStyle(document.querySelector('{$swatch}')).backgroundColor", 'rgb(255, 0, 0)');

    brandGuidelinesItemAction($page, $item, 'icon-square-pen');
    $page
        ->assertValue('#manage_content #name', 'Primary red')
        ->fill('#manage_content #name', 'Primary blue')
        ->fill('#manage_content #rgb', '0,0,255')
        ->click('#manage_content #name')
        ->assertValue('#manage_content #hex', '0000FF')
        ->assertValue('#manage_content #cmyk', '100,100,0,0')
        ->click('#manage_content input[name="content_submit"]')
        ->waitForAjax()
        ->assertSeeIn($item, 'Primary blue')
        ->assertSeeIn($item, '#0000FF')
        ->assertSeeIn($item, '0,0,255')
        ->assertSeeIn($item, '100,100,0,0')
        ->assertScript("getComputedStyle(document.querySelector('{$swatch}')).backgroundColor", 'rgb(0, 0, 255)')
        ->assertDontSee('Primary red');

    brandGuidelinesItemAction($page, $item, 'icon-trash-2');
    $page->assertNotPresent($item)->assertVisible('#add-new-content-end');
});

test('adds, moves, edits, renders and deletes a resource in the :dataset layout', function (
    string $label,
    string $layout_class,
    string $preview_size,
    string $edited_label,
    string $edited_class,
    string $edited_size,
) use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $ref = createBrandGuidelinesTestPage($guidelines_fixture->prefix);
    $intro = createBrandGuidelinesContentFixture($ref, 'text', ['richtext' => '<p>Logo guidance</p>']);
    $resource_type = 1;
    $resource = create_resource($resource_type, 0, user: 1, file_extension: 'jpg');
    expect($resource)->toBeInt()->toBeGreaterThan(0);
    $guidelines_fixture->resource = $resource;
    $title = $guidelines_fixture->prefix . ' logo';
    $title_field = $GLOBALS['view_title_field'];
    // Fixture setup runs in PHP, before the administrator logs in through Playwright.
    $previous_permissions = $GLOBALS['userpermissions'] ?? null;
    try {
        $updated = bypass_permissions(["ert{$resource_type}"], update_field(...), [$resource, $title_field, $title]);
    } finally {
        $GLOBALS['userpermissions'] = $previous_permissions;
    }
    expect($updated)->toBeTrue();
    expect(get_data_by_field($resource, $title_field))->toBe($title);

    // Use prebuilt images so the layout test is independent of preview generation.
    foreach ([$preview_size, $edited_size] as $size) {
        $path = get_resource_path($resource, true, $size, true, 'jpg');
        expect(copy(testFixture('Images/image_1.jpg'), $path))->toBeTrue();
    }

    $item = ".guidelines-content div[id^='page-content-item-']:not(#page-content-item-{$intro})";
    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $assert_resource = static function (string $class, string $size) use ($page, $item, $title, $resource): void {
        $layout = $item . '.' . $class;
        $page
            ->assertVisible($layout . ' img')
            ->assertAttribute($layout . ' img', 'alt', $title)
            ->assertAttributeContains($layout . ' a:has(img)', 'href', '?r=' . $resource);
        $preview = get_resource_path($resource, false, $size, false, 'jpg');
        $actual = $page->attribute($layout . ' img', 'src');
        expect($actual)->toBeString()->not()->toBeEmpty();
        foreach ([PHP_URL_PATH, PHP_URL_QUERY] as $part) {
            expect(parse_url($actual, $part))->toBe(parse_url($preview, $part));
        }
        $image = encode_js_value($layout . ' img');
        $page->assertScript("document.querySelector({$image})?.naturalWidth > 0");
    };

    $page
        ->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '#toggle-view-edit-mode')
        ->click('#toggle-view-edit-mode')
        ->click("#add-new-content-after-{$intro}")
        ->click('#menu-content button:has(> .icon-image)')
        ->waitForAjax()
        ->fill('#manage_content #resource_id', (string) $resource)
        ->select('#manage_content #layout', $label);
    if ($label === 'Half-width') {
        $page->fill('#manage_content #caption', 'Primary logo caption');
    }
    $page->click('#manage_content input[name="content_submit"]')->waitForAjax();
    $assert_resource($layout_class, $preview_size);
    if ($label === 'Half-width') {
        $page->assertSeeIn($item . ' p', 'Primary logo caption');
    }

    $item_id = (int) str_replace('page-content-item-', '', (string) $page->attribute($item, 'id'));
    expect($item_id)->toBeGreaterThan(0);
    $resource_block = $label === 'Full-width' ? $item_id : [$item_id];
    assertBrandGuidelinesContentOrder($page, [$intro, $resource_block]);
    brandGuidelinesItemAction($page, $item, 'icon-chevron-up');
    assertBrandGuidelinesContentOrder($page, [$resource_block, $intro]);
    brandGuidelinesItemAction($page, $item, 'icon-chevron-down');
    assertBrandGuidelinesContentOrder($page, [$intro, $resource_block]);

    brandGuidelinesItemAction($page, $item, 'icon-square-pen');
    $page->assertValue('#manage_content #resource_id', $resource)
        ->select('#manage_content #layout', $edited_label);
    if ($edited_label === 'Half-width') {
        $page->fill('#manage_content #caption', 'Updated logo caption');
    }
    $page->click('#manage_content input[name="content_submit"]')->waitForAjax();
    $assert_resource($edited_class, $edited_size);
    $page->assertNotPresent($item . '.' . $layout_class);
    if ($edited_label === 'Half-width') {
        $page->assertSeeIn($item . ' p', 'Updated logo caption');
    }

    brandGuidelinesItemAction($page, $item, 'icon-trash-2');
    $page->assertNotPresent($item)->assertSeeIn("#page-content-item-{$intro}", 'Logo guidance');
})->with([
    'thumbnail' => ['Thumbnail', 'image-thumbnail', 'thm', 'Half-width', 'image-half-width', 'pre'],
    'half-width' => ['Half-width', 'image-half-width', 'pre', 'Full-width', 'resource-content-full-width', 'scr'],
    'full-width' => ['Full-width', 'resource-content-full-width', 'scr', 'Thumbnail', 'image-thumbnail', 'thm'],
]);

/**
 * Check the displayed content order and the actual items in each group.
 *
 * @param BrowserPage $page Current guidelines browser session
 * @param list<int|list<int>> $expected Standalone item references and ordered groups
 */
function assertBrandGuidelinesContentOrder(BrowserPage $page, array $expected): void
{
    $page->assertScript(<<<'JS'
        (() => {
            const item = '[id^="page-content-item-"]';
            const getRef = element => Number(element.id.replace('page-content-item-', ''));
            return [...document.querySelectorAll(`.guidelines-content > ${item}, .guidelines-content > .group`)]
                .map(element => element.matches(item)
                    ? getRef(element)
                    : [...element.querySelectorAll(item)].map(getRef));
        })()
    JS, $expected);
}

test('reorders standalone items, group members and whole groups', function () use ($guidelines_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $ref = createBrandGuidelinesTestPage($guidelines_fixture->prefix);
    $create_item = static fn (string $type, array $values): int => createBrandGuidelinesContentFixture(
        $ref,
        $type,
        $values,
    );

    $intro = $create_item('text', ['richtext' => '<p>Introduction</p>']);
    $usage = $create_item('text', ['richtext' => '<p>Usage</p>']);
    $red = $create_item('colour', ['name' => 'Red', 'hex' => 'FF0000', 'rgb' => '255,0,0', 'cmyk' => '0,100,100,0']);
    $green = $create_item('colour', [
        'name' => 'Green',
        'hex' => '00FF00',
        'rgb' => '0,255,0',
        'cmyk' => '100,0,100,0',
    ]);
    $blue = $create_item('colour', ['name' => 'Blue', 'hex' => '0000FF', 'rgb' => '0,0,255', 'cmyk' => '100,100,0,0']);
    $notes = $create_item('text', ['richtext' => '<p>Notes</p>']);
    $colours = [$red, $green, $blue];
    $original = [$intro, $usage, $colours, $notes];

    $login_page = visit($this->browserUrl('/login.php'));
    $page = new BrowserPage($this->loginAsAdmin($login_page));
    $page->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '#toggle-view-edit-mode')->click('#toggle-view-edit-mode');
    assertBrandGuidelinesContentOrder($page, $original);

    $moves = [
        [$usage, 'up', [$usage, $intro, $colours, $notes]],
        [$usage, 'down', $original],
        [$red, 'right', [$intro, $usage, [$green, $red, $blue], $notes]],
        [$red, 'left', $original],
        [$red, 'up', [$intro, [$red], $usage, [$green, $blue], $notes]],
        [$blue, 'down', [$intro, [$red], $usage, [$green], $notes, [$blue]]],
        [$blue, 'up', [$intro, [$red], $usage, [$green, $blue], $notes]],
        [$red, 'down', $original],
        [$usage, 'down', [$intro, $colours, $usage, $notes]],
        [$usage, 'up', $original],
    ];
    foreach ($moves as [$item, $direction, $expected]) {
        brandGuidelinesItemAction($page, "#page-content-item-{$item}", 'icon-chevron-' . $direction);
        assertBrandGuidelinesContentOrder($page, $expected);
    }

    $group = '.guidelines-content > .group[data-members="' . implode(',', $colours) . '"]';
    brandGuidelinesItemAction($page, $group, 'icon-chevron-down');
    assertBrandGuidelinesContentOrder($page, [$intro, $usage, $notes, $colours]);
    $page->openAndWaitFor($guidelines_fixture->guidelinesUrl . '?spage=' . $ref, '.guidelines-content');
    assertBrandGuidelinesContentOrder($page, [$intro, $usage, $notes, $colours]);
});

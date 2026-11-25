<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\Browser\ResourceTypesPage;
use Montala\ResourceSpace\Tests\Support\Browser\SimpleSearchPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
$fixture_type_names = [];
$original_order = [];
beforeEach(function () use (&$fixtures, &$fixture_type_names, &$original_order): void {
    $fixtures = new IntegrationFixtures(['download_access_cache']);
    $fixture_type_names = [];
    $original_order = [];
});
afterEach(function () use (&$fixtures, &$fixture_type_names, &$original_order): void {
    try {
        $fixtures?->cleanUp();
    } finally {
        foreach ($fixture_type_names as $name) {
            ps_query('DELETE FROM resource_type WHERE name = ?', ['s', $name]);
        }
        foreach ($original_order as $ref => $order) {
            ps_query('UPDATE resource_type SET order_by = ? WHERE ref = ?', ['i', $order, 'i', $ref]);
        }
        clear_restype_cache();
        clear_query_cache('schema');
    }
});

test('new resource types appear in simple and advanced search', function () use (&$fixture_type_names): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $name = 'Search type ' . generateRandomTestID(12);
    $fixture_type_names[] = $name;
    $page = $this->loginAsAdmin();
    (new ResourceTypesPage($page))->open();
    $page->fill('newtype', $name);
    $page->page()->locator('form:has(#newtype) input[name=Submit]')->click(['noWaitAfter' => true]);
    $page->page()->locator('input[name=allowed_extensions]')->waitFor(['state' => 'visible']);
    $page->assertValue('name', $name);

    $browser = new BrowserPage($page);
    $browser->openAndWaitFor($this->browserUrl('/pages/search.php'), '.header-search-field');
    (new SimpleSearchPage($page))
        ->toggleSearchFilterPanel()
        ->openResourceTypeSelector()
        ->assertSeeIn('#select2-restypes-results', $name);
    $browser->openAndWaitFor(
        $this->browserUrl('/pages/search_advanced.php'),
        '.SearchTypeItemCheckbox[name=resourcetype1]'
    );
    $page->assertSee($name);
});

test('resource types are shown in configured display order in simple and advanced search', function () use (&$fixture_type_names, &$original_order): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $suffix = generateRandomTestID(12);
    $names = ['Alpha ' . $suffix, 'Beta ' . $suffix, 'Charlie ' . $suffix];
    $original_order = array_column(ps_query('SELECT ref, order_by FROM resource_type'), 'order_by', 'ref');
    $fixture_type_names = $names;
    $refs = array_map(static fn (string $name): int => bypass_permissions(['a'], create_resource_type(...), [$name]), $names);
    expect($refs)->each->toBeInt();
    $expected_names = [$names[2], $names[0], $names[1]];
    $expected_refs = [$refs[2], $refs[0], $refs[1]];
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);

    $browser->openAndWaitFor(
        $this->browserUrl('/pages/admin/admin_resource_types.php?restype_order_by=order_by&restype_sort=asc'),
        "#restype_sort_{$refs[2]} .moveuplink"
    )->startAjaxTracking()
        ->click("#restype_sort_{$refs[2]} .moveuplink")
        ->waitForAjax()
        ->click("#restype_sort_{$refs[2]} .moveuplink")
        ->waitForAjax();
    $browser->openAndWaitFor($this->browserUrl('/pages/search.php'), '.header-search-field');
    $labels = encode_js_value($names);
    (new SimpleSearchPage($page))->toggleSearchFilterPanel()->openResourceTypeSelector()
        ->assertScript(<<<JS
            [...document.querySelectorAll('#select2-restypes-results > li')]
                .map(option => option.textContent.trim())
                .filter(label => {$labels}.includes(label))
                .join('|')
            JS, implode('|', $expected_names));

    $browser->openAndWaitFor($this->browserUrl('/pages/search_advanced.php'), '.SearchTypeItemCheckbox[name=resourcetype1]');
    $ids = encode_js_value($refs);
    $page->assertScript(<<<JS
        [...document.querySelectorAll('.SearchTypeItemCheckbox')]
            .map(field => +field.name.replace('resourcetype', ''))
            .filter(id => {$ids}.includes(id))
            .join('|')
        JS, implode('|', $expected_refs));
});

it('displays resource type icon in search result thumbnails', function () use (&$fixtures, &$fixture_type_names): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $name = 'Icon type ' . generateRandomTestID(12);
    $fixture_type_names[] = $name;
    $type = bypass_permissions(['a'], create_resource_type(...), [$name]);
    expect($type)->toBeInt();
    $resource = $fixtures->resource($type, 0);
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $browser->openAndWaitFor(
        $this->browserUrl("/pages/admin/admin_resource_type_edit.php?ref={$type}"),
        '#iconpicker-input'
    );
    $page->page()->locator('#iconpicker-button')->click(['noWaitAfter' => true]);
    $page->page()->locator('.iconpicker-content-icon[data-icon="alarm-clock-check"]')->click(['noWaitAfter' => true]);
    $page->assertValue('#iconpicker-input', 'alarm-clock-check');
    $page->script('window.resourceTypeEditBeforeSave = true;');
    $page->page()->locator('input[name=save]')->click(['noWaitAfter' => true]);
    $page->assertScript(<<<'JS'
        !window.resourceTypeEditBeforeSave && document.querySelector('#restype_save')?.value === ''
            && document.querySelector('input[name=icon]')?.value === 'alarm-clock-check'
        JS);
    expect(ps_value('SELECT icon AS value FROM resource_type WHERE ref = ?', ['i', $type], null))
        ->toBe('alarm-clock-check');
    $browser->openAndWaitFor($this->browserUrl("/pages/search.php?search=%21list{$resource}"), "#ResourceShell{$resource}");
    $page->assertPresent("#ResourceShell{$resource} .resource-card-type .icon-alarm-clock-check");
});

test('the uploader accepts allowed resource type files and rejects other formats', function () use (&$fixtures, &$fixture_type_names): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $name = 'File type allow list ' . generateRandomTestID(12);
    $fixture_type_names[] = $name;
    $type = bypass_permissions(['a'], create_resource_type(...), [$name]);
    expect($type)->toBeInt();
    expect(bypass_permissions(['a'], save_resource_type(...), [$type, ['allowed_extensions' => 'image/jpeg, image/png']]))
        ->toBeTrue();
    $accepted = $fixtures->resource($type, 0);
    $rejected = $fixtures->resource($type, 0);
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);

    $replacement_url = fn (int $ref): string => generateURL($this->browserUrl('/pages/upload_batch.php'), [
        'replace_resource' => $ref, 'ref' => $ref, 'resource_type' => $type,
        'redirecturl' => generateURL($this->browserUrl('/pages/view.php'), ['ref' => $ref]),
    ]);
    $jpg = testFixture('Images/image_1.jpg');
    $browser->openAndWaitFor($replacement_url($accepted), '.uppy-Dashboard');
    $file_input = '.uppy-Dashboard input[type=file]';
    $page->page()->locator($file_input)->first()->setInputFiles($jpg);
    $page->assertScript('uppy.getFiles().length', 1);
    $browser->uploadAndWaitFor('#ResourceDownloadOptions');
    $original = get_resource_path($accepted, true, '', false, 'jpg');
    expect(hash_file('sha256', $original))->toBe(hash_file('sha256', $jpg));

    $browser->openAndWaitFor($replacement_url($rejected), '.uppy-Dashboard');
    $page->page()->locator($file_input)->first()->setInputFiles(testFixture('Videos/short_5seconds.mp4'));
    $page->click(UiElementSelector::UppyActionUploadButton->value);
    $page->assertScript('document.querySelector("#upload_log").value.includes("Invalid file type")');
    expect(get_resource_data($rejected, false)['file_extension'])->toBeEmpty();
});

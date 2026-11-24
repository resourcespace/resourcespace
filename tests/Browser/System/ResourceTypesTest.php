<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\ResourceTypesPage;
use Montala\ResourceSpace\Tests\Support\Browser\SimpleSearchPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;

afterAll(fn() => setTestConfiguration(''));

test('create a new resource type, ensure it is shown in expected areas (Simple Search, Advanced search etc)', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_type_name = 'RT 1';

    $this
        ->loginAsAdmin()
        ->click('#header-container .actions .get-menu-content:first-of-type')
        ->assertSee('System')
        ->click('li[title="System setup area"] a')
        ->assertSee('Resource types')
        ->click('li[title="Create and configure resource types"] a')
        ->assertPathIs('/pages/admin/admin_resource_types.php')
        ->fill('newtype', $resource_type_name)
        ->click('.Inline > input[type="submit"]')
        ->assertSee('Saved') // toast notification
        ->refresh()
        // Simple search
        ->click('.header-search-field .input-wrapper > button:last-of-type')
        ->click('.header-search-field .field-input .select2 .select2-selection--multiple')
        ->assertSee($resource_type_name)
        // Advanced search
        ->click('Advanced search')
        ->assertSee($resource_type_name);
});

test('resource types are shown in configured display order in simple and advanced search', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $rt_a = 'Order Test - Alpha';
    $rt_b = 'Order Test - Beta';
    $rt_c = 'Order Test - Charlie';
    $rts = [
        'rt_a' => bypass_permissions(['a'], create_resource_type(...), [$rt_a]),
        'rt_b' => bypass_permissions(['a'], create_resource_type(...), [$rt_b]),
        'rt_c' => bypass_permissions(['a'], create_resource_type(...), [$rt_c]),
    ];

    $assert_simple_search_sort_order = static function ($page, array $rts) {
        $js_rts = encode_js_value($rts);

        $ss = (new SimpleSearchPage($page))
            ->toggleSearchFilterPanel()
            ->openResourceTypeSelector()
            ->assertScript(<<<JS
                (() => {
                    const rts = {$js_rts};

                    return [...document.querySelectorAll('#select2-restypes-results > li')]
                        .map(el => el.textContent.trim())
                        .filter(label => rts.includes(label))
                        .join('|');
                })()
            JS, implode('|', $rts));

        $page = (new SimpleSearchPage($ss->getPage()))->toggleSearchFilterPanel();
    };

    $assert_advanced_search_sort_order = static function ($page, array $rts) {
        $js_rts = encode_js_value($rts);

        $page->assertScript(<<<JS
            (() => {
                const rt_ids = {$js_rts};

                return [...document.querySelectorAll('.SearchTypeCheckbox.SearchTypeItemCheckbox')]
                    .map(el => el.name)
                    .map(value => value.match(/^resourcetype(\d+)$/))
                    .filter(Boolean)
                    .map(match => Number(match[1]))
                    .filter(id => rt_ids.includes(id))
                    .join('|');
            })()
        JS, implode('|', $rts));
    };

    $rt_page = (new ResourceTypesPage($this->loginAsAdmin()))
        ->open()
        ->getPage();

    $assert_simple_search_sort_order($rt_page, [$rt_a, $rt_b, $rt_c]);
    $assert_advanced_search_sort_order(
        (new SimpleSearchPage($rt_page))->goToAdvancedSearch(),
        [$rts['rt_a'], $rts['rt_b'], $rts['rt_c']],
    );

    // Re-order the resource types
    $rt_page = (new ResourceTypesPage($rt_page))
        ->open()
        ->click('.BasicsBox > a[href*="restype_order_by=order_by"][href*="restype_sort=asc"]')
        ->waitForAjax()
        ->drag("#restype_sort_{$rts['rt_c']}", "#restype_sort_{$rts['rt_a']}")
        ->refresh()
        ->getPage();

    $assert_simple_search_sort_order($rt_page, [$rt_c, $rt_a, $rt_b]);
    $assert_advanced_search_sort_order(
        (new SimpleSearchPage($rt_page))->goToAdvancedSearch(),
        [$rts['rt_c'], $rts['rt_a'], $rts['rt_b']],
    );
});

it('displays resource type icon in search result thumbnails', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $rt_with_icon = bypass_permissions(['a'], create_resource_type(...), ['RT w/ icon']);
    $resource = create_resource($rt_with_icon, 0);

    (new ResourceTypesPage($this->loginAsAdmin()))
        ->open()
        ->selectResourceType($rt_with_icon)
        ->waitForAjax()
        ->click('#iconpicker-input')
        ->typeSlowly('#iconpicker-filter', 'alarm-clock-check')
        ->click('.iconpicker-content-icon[data-icon="alarm-clock-check"]')
        ->click('Save')
        ->navigate($this->browserUrl("/pages/search.php?search=%21list{$resource}"))
        ->waitForAjax()
        ->assertPresent("#ResourceShell{$resource} .resource-card-type .icon-alarm-clock-check");
});

test('users can only upload the allowed file types for resource types', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration('$show_resource_type = true;');

    $rt = bypass_permissions(['a'], create_resource_type(...), ['RT with file types allow list']);
    expect($rt)->toBeInt();

    // Edit a resource type, and specify the allowed file types for it
    $page = (new ResourceTypesPage($this->loginAsAdmin()))
        ->open()
        ->selectResourceType($rt)
        ->waitForAjax()
        ->fill('allowed_extensions', 'image/jpeg, image/png')
        ->click('Save');

    // Ensure you can only upload the allowed file types
    $resource_jpg = create_resource($rt, 0);
    expect($resource_jpg)->toBeInt();

    $page
        ->navigate($this->browserUrl("/pages/view.php?ref={$resource_jpg}"))
        ->waitForAjax()
        ->click('#view_replace_link')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->assertSeeIn('.DownloadFileName', '300 × 50 pixels');

    // Assert that file types outside the allow list will fail
    $resource_mp4 = create_resource($rt, 0);
    expect($resource_mp4)->toBeInt();

    $page
        ->navigate($this->browserUrl("/pages/view.php?ref={$resource_mp4}"))
        ->waitForAjax()
        ->click('#view_replace_link')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Videos/short_5seconds.mp4'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax();

    expect($page->value('#upload_log'))
        ->toContain('Invalid file type')
        ->toContain('mp4');
});

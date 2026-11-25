<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
});
afterEach(function () use (&$fixtures): void {
    setTestConfiguration('');
    $fixtures?->cleanUp();
});

test('a metadata phrase search preserves results and sorting as the user browses pages and opens a resource', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration(<<<'CONFIG'
        $results_display_array = [1, 20, 50];
        $default_perpage = 1;
        $order_by_resource_id = true;
        $default_display = 'thumbs';
        $resource_view_modal = false;
        CONFIG);
    $suffix = generateRandomTestID(16);
    $shortname = 'browserphrase';
    $field = $fixtures->field('Phrase search field', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $shortname, true);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($first, $field, "winter {$suffix} sky", $errors))->toBeTrue()
        ->and(update_field($second, $field, "winter {$suffix} lake", $errors))->toBeTrue()
        ->and(update_field($control, $field, "unrelated spring sky", $errors))->toBeTrue();

    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $phrase = "{$shortname}:\"winter {$suffix}\"";
    $search = generateURL($this->browserUrl('/pages/search.php'), [
        'search' => $phrase, 'per_page' => 20, 'order_by' => 'resourceid', 'sort' => 'ASC',
        'display' => 'thumbs',
    ]);
    $first_result = "#CentralSpace #ResourceShell{$first}";
    $second_result = "#CentralSpace #ResourceShell{$second}";
    $browser->openAndWaitFor($search, $first_result);
    $page->assertVisible($second_result)->assertNotPresent("#CentralSpace #ResourceShell{$control}");

    $browser->click('.update_result_order_button')->waitForAjax();
    $per_page = $page->page()->locator('#resultsdisplay option[value*="per_page=1&"]')->getAttribute('value');
    expect($per_page)->toBeString();
    $page->page()->locator('#resultsdisplay')->selectOption($per_page);
    $browser->waitForAjax();
    $page->assertVisible($second_result);
    $page->assertNotPresent($first_result);
    $browser->click('#CentralSpace .nextPageLink')->waitForAjax();
    $page->assertVisible($first_result)->assertNotPresent($second_result);

    parse_str((string) parse_url($page->page()->url(), PHP_URL_QUERY), $before);
    $page->page()->locator("{$first_result} a.resource-card-image")->click(['noWaitAfter' => true]);
    $page->page()->locator('.backtoresults .upLink')->waitFor(['state' => 'visible']);
    $page->assertPathIs('/pages/view.php');
    $browser->click('.backtoresults .upLink')->waitForAjax();
    $page->assertVisible($first_result)->assertNotPresent($second_result);
    parse_str((string) parse_url($page->page()->url(), PHP_URL_QUERY), $params);
    expect($params['search'] ?? null)->toBe($before['search'])
        ->and($params['sort'] ?? null)->toBe('DESC')
        ->and($params['per_page'] ?? null)->toBe('1');
});

test('keeps shift and page selections when switching search layouts and can clear them', function () use (
    &$fixtures,
): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration(<<<'CONFIG'
        $results_display_array = [3, 10];
        $default_perpage = 3;
        $use_selection_collection = true;
        $thumbs_default = 'actions';
        CONFIG);
    $selection = get_user_selection_collection((int) $GLOBALS['userref']);
    expect($selection)->toBeInt();
    $user = (int) $GLOBALS['userref'];
    $original_collection = (int) get_user($user)['current_collection'];
    $target = $fixtures->collection($user, 'Selected results ' . generateRandomTestID(12));
    $fixtures->onCleanup(static fn () => set_user_collection($user, $original_collection));
    $previous = get_collection_resources($selection);
    $fixtures->onCleanup(static function () use ($selection, $previous): void {
        remove_all_resources_from_collection($selection);
        collection_add_resources($selection, $previous);
    });
    remove_all_resources_from_collection($selection);

    $refs = [];
    for ($index = 0; $index < 5; $index++) {
        $refs[] = $fixtures->resource(1, 0);
    }
    [$first, $second, $third, $fourth, $control] = $refs;
    $search_url = $this->browserUrl('/pages/search.php');
    $search_parameters = [
        'search' => '!list' . implode(':', $refs), 'order_by' => 'resourceid', 'sort' => 'ASC',
        'display' => 'thumbs', 'archive' => 0,
    ];
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $selection_link = '#CentralSpace #SearchResultFound .SelectionCollectionLink';
    $count = $selection_link . ' .Selected';
    $browser->openAndWaitFor(
        generateURL($search_url, $search_parameters),
        "#ResourceShell{$first} .resource-card-action-bar label"
    );
    $page->page()->locator("label:has(input#check{$first})")->click(['noWaitAfter' => true]);
    $browser->waitForAjax(15_000, 500);
    expect(get_collection_resources($selection))->toBe([$first]);
    $page->page()->locator("label:has(input#check{$third})")->click(['modifiers' => ['Shift'], 'noWaitAfter' => true]);
    $browser->waitForAjax(15_000, 500);
    $page->assertChecked("#check{$second}");
    expect(get_collection_resources($selection))->toEqualCanonicalizing([$first, $second, $third]);

    $browser->click('#CentralSpace .nextPageLink')->waitForAjax();
    $page->page()->locator("label:has(input#check{$fourth})")->click(['noWaitAfter' => true]);
    $browser->waitForAjax(15_000, 500);
    $page->assertNotChecked("#check{$control}");
    expect(get_collection_resources($selection))->toEqualCanonicalizing([$first, $second, $third, $fourth]);

    $browser->click('#CentralSpace .prevPageLink')->waitForAjax();
    $page->assertChecked("#check{$first}")->assertChecked("#check{$third}");
    $list = generateURL($search_url, $search_parameters, ['display' => 'list']);
    $browser->openAndWaitFor($list, "#CentralSpace tr#ResourceShell{$first}");
    $page->assertVisible("tr#ResourceShell{$first}")->assertChecked("#check{$first}")->assertSeeIn($count, '4');
    $page->page()->locator("tr#ResourceShell{$first} input#check{$first}")->click(['noWaitAfter' => true]);
    $browser->waitForAjax(15_000, 500);
    expect(get_collection_resources($selection))->toEqualCanonicalizing([$second, $third, $fourth]);

    $page->assertSeeIn($count, '3');
    $page->page()->locator($selection_link)->click(['noWaitAfter' => true]);
    $browser->waitForAjax();
    foreach ([$second, $third, $fourth] as $ref) {
        $page->assertVisible("#CentralSpace #ResourceShell{$ref}");
    }
    $page->assertNotPresent("#CentralSpace #ResourceShell{$first}")
        ->assertNotPresent("#CentralSpace #ResourceShell{$control}")
        ->assertVisible('#EditSelectedResourcesBtn');

    $current_collection = '#CollectionDiv select[name=collection]';
    $page->page()->locator($current_collection)->selectOption($target);
    $browser->waitForAjax();
    $page->assertValue($current_collection, (string) $target);
    $actions = '#CentralSpace select:has(option[value=save_search_items_to_collection])';
    $add_selected = $page->page()->locator($actions);
    expect(trim((string) $add_selected->locator('option[value=save_search_items_to_collection]')->textContent()))
        ->toBe($GLOBALS['lang']['add_selected_to_collection']);
    expect($add_selected->locator('option[value=editsearchresults]')->count())->toBe(0);
    $add_selected->selectOption('save_search_items_to_collection');
    $browser->waitForAjax();
    expect(get_collection_resources($target))->toEqualCanonicalizing([$second, $third, $fourth]);

    $browser->click('#ClearSelectedResourcesBtn')->waitForAjax();
    $page->assertNotPresent('#EditSelectedResourcesBtn')->assertNotPresent('#ClearSelectedResourcesBtn');
    expect(get_collection_resources($selection))->toBeEmpty();
});

test('switches between resource metadata and featured collection criteria on Advanced Search', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration('$search_includes_themes = true;');
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $checkbox = '#SearchFeaturedCollectionsCheckbox';
    $resources = '#AdvancedSearchGlobalSectionHead';
    $collections = '#AdvancedSearchFeaturedCollectionsSectionHead';
    $browser->openAndWaitFor($this->browserUrl('/pages/search_advanced.php'), $checkbox);
    expect($page->script(<<<'JS'
        (() => {
            const featured = document.getElementById('SearchFeaturedCollectionsCheckbox');
            const types = [...document.querySelectorAll('.SearchTypeItemCheckbox')];
            return types.length > 0 && types.every(
                checkbox => (checkbox.compareDocumentPosition(featured) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0
            );
        })()
        JS))->toBeTrue();

    $page->assertVisible($resources);
    $page->page()->locator($collections)->waitFor(['state' => 'hidden']);
    $page->page()->locator($checkbox)->check();
    $page->assertVisible($collections);
    $page->page()->locator($resources)->waitFor(['state' => 'hidden']);

    $page->page()->locator($checkbox)->uncheck();
    $page->assertVisible($resources);
    $page->page()->locator($collections)->waitFor(['state' => 'hidden']);
});

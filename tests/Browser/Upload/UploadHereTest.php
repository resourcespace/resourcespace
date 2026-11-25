<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\Browser\SimpleSearchPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

/**
 * Search for Active Photos tagged with United Kingdom using the advanced search form.
 *
 * @param SimpleSearchPage $page Authenticated browser page
 *
 * @return SimpleSearchPage Search results with the requested criteria
 */
function searchForUploadHere(SimpleSearchPage $page): SimpleSearchPage
{
    $page
        ->goToAdvancedSearch()
        ->waitForAjax()
        ->assertPathIs('/pages/search_advanced.php')
        // Clear all resource types before selecting Photo (default resource type 1).
        ->check('#SearchGlobal')
        ->uncheck('#SearchGlobal')
        ->check('input[name="resourcetype1"]');

    $selected_states = $page->script(<<<'JS'
        Array.from(document.querySelectorAll('#question_archive input:checked'), input => input.value)
    JS);
    foreach ($selected_states as $state) {
        $page->uncheck(sprintf('#question_archive input[value="%d"]', (int) $state));
    }

    $page
        ->check('#question_archive input[value="0"]')
        // Country is default field 3; United Kingdom is default node 232.
        ->fill('#field_3_search_adv_selector', 'United Kingdom')
        ->click('.ui-autocomplete:visible .ui-menu-item-wrapper:text-is("United Kingdom")')
        ->assertSeeIn('#nodeselected232', 'United Kingdom')
        ->click('#advancedform > .QuestionSubmit:last-of-type input[name="dosearch"]')
        ->waitForAjax()
        ->assertPathIs('/pages/search.php')
        ->assertQueryStringHas('archive', '0')
        ->assertQueryStringHas('restypes', '1')
        ->assertQueryStringHas('search', ', ' . NODE_TOKEN_PREFIX . '232')
        ->assertVisible('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")');

    return $page;
}

test('Upload here preserves advanced search values through upload', function (bool $upload_then_edit): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();

    $title = 'UploadHere - ' . generateRandomTestID(16);
    $template = -$GLOBALS['userref'];
    $previous_review_resources = get_collection_resources($template);

    try {
        // Each dataset case needs fresh configuration for subsequent browser requests.
        setTestConfiguration('');
        setTestConfiguration(
            '$upload_then_edit = ' . ($upload_then_edit ? 'true' : 'false') . ';'
            . "\n" . '$show_status_and_access_on_upload = true;'
        );
        clear_resource_data($template);

        $search = searchForUploadHere(new SimpleSearchPage($this->loginAsAdmin()))
            ->click('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")')
            ->waitForAjax();
        $page = new EditPage($search->getPage());

        if ($upload_then_edit) {
            $page
                ->assertPathIs('/pages/upload_batch.php')
                ->click('#UploadOptionsSectionHead')
                ->select('collection_add', 'false')
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->click(UiElementSelector::UppyActionUploadButton->value)
                ->waitForAjax();
        }

        // Inspect the inherited values before changing any metadata.
        $page
            ->assertPathIs('/pages/edit.php')
            ->assertSelected('resource_type', '1')
            ->assertSelected('status', '0')
            ->assertSeeIn('#field_3_selected #nodeselected232', 'United Kingdom')
            ->assertValue('input[name="nodes[3][]"]', '232')
            ->fillTitle($title);

        if ($upload_then_edit) {
            $page->refineMetadataSaveAndNext()->waitForAjax();
        } else {
            $page
                ->select('collection_add', 'false')
                ->click('Next')
                ->waitForAjax()
                ->assertPathIs('/pages/upload_batch.php')
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->uploadSavedMetadata();
        }

        $resource = $this->expectResourceIdByNodeName($title);
        $data = get_resource_data($resource, false);

        expect($data)->not->toBeFalse();
        expect((int) $data['archive'])->toBe(0);
        expect((int) $data['resource_type'])->toBe(1);
        expect(array_map('intval', get_resource_nodes($resource, 3)))->toBe([232]);
    } finally {
        setTestConfiguration('');
        clear_resource_data($template);

        // Include untitled uploads if an assertion failed before the title was saved.
        $resources = ps_array(
            'SELECT DISTINCT rn.resource AS value FROM resource_node rn'
            . ' JOIN node n ON n.ref = rn.node WHERE n.name = ? AND rn.resource > 0',
            ['s', $title]
        );
        if ($upload_then_edit) {
            $resources = array_merge(
                $resources,
                array_diff(get_collection_resources($template), $previous_review_resources)
            );
        }

        foreach (array_unique($resources) as $resource) {
            // Honour the configured deletion lifecycle, then permanently remove the fixture.
            delete_resource($resource);
            if (get_resource_data($resource, false) !== false) {
                delete_resource($resource);
            }
        }
    }
})->with([
    'edit then upload' => [false],
    'upload then edit' => [true],
]);

test('Upload here adds resources to the viewed collection', function (bool $upload_then_edit): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();

    $test_id = generateRandomTestID(16);
    $title = "UploadHere collection - {$test_id}";
    $existing_title = "UploadHere existing - {$test_id}";
    $user = $GLOBALS['userref'];
    expect($user)->toBe(1);
    $template = -$user;
    $previous_review_resources = get_collection_resources($template);
    $previous_collection = (int) ps_value(
        'SELECT current_collection AS value FROM user WHERE ref = ?',
        ['i', $user],
        0
    );
    $collections = [];
    $resources = [];

    try {
        setTestConfiguration('');
        setTestConfiguration('$upload_then_edit = ' . ($upload_then_edit ? 'true' : 'false') . ';');
        clear_resource_data($template);

        $collection = create_collection($user, "UploadHere target - {$test_id}");
        $collections[] = $collection;
        $other_collection = create_collection($user, "UploadHere other - {$test_id}");
        $collections[] = $other_collection;

        $existing_resource = create_resource(1, 0, $user);
        expect($existing_resource)->toBeInt();
        $resources[] = $existing_resource;
        expect(bypass_permissions(
            ['e0', 'ert1'],
            update_field(...),
            [$existing_resource, $GLOBALS['view_title_field'], $existing_title]
        ))->toBeTrue();
        expect(add_resource_to_collection($existing_resource, $collection))->toBeTrue();

        $page = (new EditPage($this->loginAsAdmin()))
            ->navigate($this->browserUrl("/pages/search.php?search=!collection{$collection}"))
            ->waitForAjax()
            ->assertPathIs('/pages/search.php')
            ->assertQueryStringHas('search', "!collection{$collection}")
            ->assertSeeIn('#CentralSpace .resource-card-title', $existing_title);

        // Set browser state through the collection bar after login, rather than changing it in PHP.
        if ($page->script("document.querySelector('.collection-bar-holder').classList.contains('state-closed')")) {
            $page->click(UiElementSelector::CollectionBarToggleButton->value);
        }
        $page
            ->assertVisible('.collection-bar-actions-section #collection')
            ->assertPresent(".collection-bar-actions-section #collection option[value='{$other_collection}']")
            ->select('.collection-bar-actions-section #collection', (string) $other_collection)
            ->waitForAjax()
            ->assertSelected('.collection-bar-actions-section #collection', (string) $other_collection)
            // Changing the current collection must leave the viewed collection's results in place.
            ->assertQueryStringHas('search', "!collection{$collection}")
            ->assertSeeIn('#CentralSpace .resource-card-title', $existing_title)
            ->assertVisible('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")');

        $upload_handler = $page->script(<<<'JS'
            document.querySelector('#CentralSpace .InpageNavLeftBlock button[onclick*="upload_here="]')
                ?.getAttribute('onclick') ?? ''
        JS);
        $expected_endpoint = $upload_then_edit ? '/pages/upload_batch.php' : '/pages/edit.php';
        expect($upload_handler)->toContain($expected_endpoint);
        $page
            ->click('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")')
            ->waitForAjax()
            ->assertQueryStringHas('collection_add', (string) $collection);

        if ($upload_then_edit) {
            $page
                ->assertPathIs('/pages/upload_batch.php')
                ->click('#UploadOptionsSectionHead')
                ->assertSelected('#CentralSpace select[name="collection_add"]', (string) $collection)
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->uploadAndWaitFor('#CentralSpace input[name="field_' . $GLOBALS['view_title_field'] . '"]')
                ->assertPathIs('/pages/edit.php')
                ->fillTitle($title)
                ->refineMetadataSaveAndNext()
                ->waitForAjax();
        } else {
            $page
                ->assertPathIs('/pages/edit.php')
                ->assertSelected('#CentralSpace select[name="collection_add"]', (string) $collection)
                ->fillTitle($title)
                ->click('Next')
                ->waitForAjax()
                ->assertPathIs('/pages/upload_batch.php')
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->uploadSavedMetadata();
        }

        $resource = $this->expectResourceIdByNodeName($title);
        $resources[] = $resource;
        expect(array_map('intval', get_collection_resources($collection)))
            ->toEqualCanonicalizing([$existing_resource, $resource]);
        expect(get_collection_resources($other_collection))->toBe([]);

        $page
            ->navigate($this->browserUrl("/pages/search.php?search=!collection{$collection}"))
            ->waitForAjax()
            ->assertSeeIn('#CentralSpace .resource-card-title', $title)
            ->assertSeeIn('#CentralSpace .resource-card-title', $existing_title);
    } finally {
        setTestConfiguration('');
        clear_resource_data($template);
        set_user_collection($user, $previous_collection);

        // Collect partial uploads as well, so failed assertions do not leave fixtures behind.
        foreach ($collections as $collection) {
            $resources = array_merge($resources, get_collection_resources($collection));
        }
        if ($upload_then_edit) {
            $resources = array_merge(
                $resources,
                array_diff(get_collection_resources($template), $previous_review_resources)
            );
        }
        foreach (array_unique($resources) as $resource) {
            delete_resource($resource);
            if (get_resource_data($resource, false) !== false) {
                delete_resource($resource);
            }
        }
        foreach ($collections as $collection) {
            delete_collection($collection);
        }
    }
})->with([
    'edit then upload' => [false],
    'upload then edit' => [true],
]);

test('Upload here inherits metadata from a smart collection', function (bool $upload_then_edit): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();

    $test_id = generateRandomTestID(16);
    $title = "UploadHere smart - {$test_id}";
    $existing_title = "UploadHere smart existing - {$test_id}";
    $user = $GLOBALS['userref'];
    $template = -$user;
    $previous_review_resources = get_collection_resources($template);
    $previous_collection = (int) ps_value(
        'SELECT current_collection AS value FROM user WHERE ref = ?',
        ['i', $user],
        0
    );
    $collection = 0;
    $savedsearch = 0;
    $resources = [];

    try {
        setTestConfiguration('');
        setTestConfiguration(
            '$upload_then_edit = ' . ($upload_then_edit ? 'true' : 'false') . ';'
            . "\n" . '$allow_smart_collections = true;'
        );
        clear_resource_data($template);

        $existing_resource = create_resource(1, 0, $user);
        expect($existing_resource)->toBeInt();
        $resources[] = $existing_resource;
        expect(update_field($existing_resource, $GLOBALS['view_title_field'], $existing_title))->toBeTrue();
        // Default nodes: Country = United Kingdom, Keywords - Subject = Product.
        expect(update_field($existing_resource, 3, '232', nodevalues: true))->toBeTrue();
        expect(update_field($existing_resource, 73, '274', nodevalues: true))->toBeTrue();

        // Prepare the saved search separately from the UI test for saving smart collections.
        $collection = create_collection($user, "UploadHere smart collection - {$test_id}");
        $search = NODE_TOKEN_PREFIX . '232 ' . NODE_TOKEN_PREFIX . '274';
        ps_query(
            'INSERT INTO collection_savedsearch (collection, search, restypes, archive, starsearch)'
            . ' VALUES (?, ?, ?, ?, ?)',
            ['i', $collection, 's', $search, 's', '1', 's', '0', 'i', DEPRECATED_STARSEARCH]
        );
        $savedsearch = (int) sql_insert_id();
        expect($savedsearch)->toBeGreaterThan(0);
        save_collection($collection, ['savedsearch' => $savedsearch]);
        expect((int) get_collection($collection)['savedsearch'])->toBe($savedsearch);

        $page = (new EditPage($this->loginAsAdmin()))->waitForAjax();
        if ($page->script("document.querySelector('.collection-bar-holder').classList.contains('state-closed')")) {
            $page->click(UiElementSelector::CollectionBarToggleButton->value);
        }
        $page
            ->assertPresent(".collection-bar-actions-section #collection option[value='{$collection}']")
            ->select('.collection-bar-actions-section #collection', (string) $collection)
            ->waitForAjax()
            ->assertPresent(
                "#collections_action_selection_bottom_{$collection} option[value='view_all_resources_in_collection']"
            )
            ->select("#collections_action_selection_bottom_{$collection}", 'view_all_resources_in_collection')
            ->waitForAjax()
            ->assertPathIs('/pages/search.php')
            ->assertQueryStringHas('search', "!collection{$collection}")
            ->assertSeeIn('#CentralSpace .resource-card-title', $existing_title)
            ->assertVisible('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")')
            ->click('#CentralSpace .InpageNavLeftBlock button:text-is("Upload here")')
            ->waitForAjax();

        if ($upload_then_edit) {
            $page
                ->assertPathIs('/pages/upload_batch.php')
                ->click('#UploadOptionsSectionHead')
                ->select('collection_add', 'false')
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->click(UiElementSelector::UppyActionUploadButton->value)
                ->waitForAjax();
        }

        $page
            ->assertPathIs('/pages/edit.php')
            ->assertSeeIn('#field_3_selected #nodeselected232', 'United Kingdom')
            ->assertValue('input[name="nodes[3][]"]', '232')
            ->assertChecked('#nodes_274')
            ->fillTitle($title);

        if ($upload_then_edit) {
            $page->refineMetadataSaveAndNext()->waitForAjax();
        } else {
            $page
                ->select('collection_add', 'false')
                ->click('Next')
                ->waitForAjax()
                ->assertPathIs('/pages/upload_batch.php')
                ->addFileToUppy(testFixture('Images/image_1.jpg'))
                ->uploadSavedMetadata();
        }

        $resource = $this->expectResourceIdByNodeName($title);
        $resources[] = $resource;
        expect(array_map('intval', get_resource_nodes($resource, 3)))->toBe([232]);
        expect(array_map('intval', get_resource_nodes($resource, 73)))->toBe([274]);
    } finally {
        setTestConfiguration('');
        clear_resource_data($template);
        set_user_collection($user, $previous_collection);

        $resources = array_merge($resources, ps_array(
            'SELECT DISTINCT rn.resource AS value FROM resource_node rn'
            . ' JOIN node n ON n.ref = rn.node WHERE n.name = ? AND rn.resource > 0',
            ['s', $title]
        ));
        if ($upload_then_edit) {
            $resources = array_merge(
                $resources,
                array_diff(get_collection_resources($template), $previous_review_resources)
            );
        }
        // Smart results may contain other resources; only delete this test's own fixtures.
        foreach (array_unique($resources) as $resource) {
            delete_resource($resource);
            if (get_resource_data($resource, false) !== false) {
                delete_resource($resource);
            }
        }
        if ($savedsearch > 0) {
            remove_saved_search($collection, $savedsearch);
        }
        if ($collection > 0) {
            delete_collection($collection);
        }
    }
})->with([
    'edit then upload' => [false],
    'upload then edit' => [true],
]);

test('a search by two metadata fields can be saved and reopened as a smart collection', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();

    $title = 'UploadHere saved search - ' . generateRandomTestID(16);
    $user = $GLOBALS['userref'];
    $previous_collection = (int) ps_value(
        'SELECT current_collection AS value FROM user WHERE ref = ?',
        ['i', $user],
        0
    );
    $previous_collections = ps_array('SELECT ref AS value FROM collection WHERE user = ?', ['i', $user]);
    $resource = false;

    try {
        setTestConfiguration('');
        setTestConfiguration('$allow_smart_collections = true;');

        $resource = create_resource(1, 0, $user);
        expect($resource)->toBeInt();
        expect(update_field($resource, $GLOBALS['view_title_field'], $title))->toBeTrue();
        expect(update_field($resource, 3, '232', nodevalues: true))->toBeTrue();
        expect(update_field($resource, 73, '274', nodevalues: true))->toBeTrue();

        // Exercise saving the search through Actions; metadata inheritance is covered separately.
        $search = NODE_TOKEN_PREFIX . '232 ' . NODE_TOKEN_PREFIX . '274';
        $search_url = generateURL($this->browserUrl('/pages/search.php'), [
            'search' => $search,
            'restypes' => '1',
            'archive' => '0',
        ]);
        $page = (new SimpleSearchPage($this->loginAsAdmin()))
            ->navigate($search_url)
            ->waitForAjax()
            ->assertSeeIn('#CentralSpace .resource-card-title', $title)
            ->assertPresent('#search_action_selection option[value="save_search_smart_collection"]')
            ->select('#search_action_selection', 'save_search_smart_collection')
            ->waitForAjax();

        $collection = (int) ps_value(
            'SELECT current_collection AS value FROM user WHERE ref = ?',
            ['i', $user],
            0
        );
        expect($collection)->toBeGreaterThan(0);
        expect(array_map('intval', $previous_collections))->not->toContain($collection);
        $saved_searches = get_saved_searches($collection);
        expect($saved_searches)->toHaveCount(1);
        $saved = $saved_searches[0];
        expect(array_map('intval', resolve_nodes_from_string($saved['search'])))
            ->toEqualCanonicalizing([232, 274]);
        expect((string) $saved['restypes'])->toBe('1');
        expect((string) $saved['archive'])->toBe('0');
        expect((int) get_collection($collection)['savedsearch'])->toBe((int) $saved['ref']);

        if ($page->script("document.querySelector('.collection-bar-holder').classList.contains('state-closed')")) {
            $page->click(UiElementSelector::CollectionBarToggleButton->value);
        }
        $page
            ->assertSelected('.collection-bar-actions-section #collection', (string) $collection)
            ->select("#collections_action_selection_bottom_{$collection}", 'view_all_resources_in_collection')
            ->waitForAjax()
            ->assertPathIs('/pages/search.php')
            ->assertQueryStringHas('search', "!collection{$collection}")
            ->assertSeeIn('#CentralSpace .resource-card-title', $title);
    } finally {
        setTestConfiguration('');
        set_user_collection($user, $previous_collection);

        // The UI generates the collection name. Also find it if an assertion failed before its ID was read.
        $created_collections = array_diff(
            ps_array('SELECT ref AS value FROM collection WHERE user = ?', ['i', $user]),
            $previous_collections
        );
        foreach ($created_collections as $collection) {
            foreach (get_saved_searches($collection) as $saved) {
                remove_saved_search($collection, $saved['ref']);
            }
            delete_collection($collection);
        }
        if ($resource !== false) {
            delete_resource($resource);
            if (get_resource_data($resource, false) !== false) {
                delete_resource($resource);
            }
        }
    }
});

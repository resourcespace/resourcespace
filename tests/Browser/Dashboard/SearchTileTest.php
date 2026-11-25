<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

require_once RESOURCESPACE_BASE_PATH . '/include/dash_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['valid_upload_paths']);
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
});
afterEach(function () use (&$fixtures): void {
    setTestConfiguration('');
    $fixtures?->cleanUp();
});

test('saves a search to the Dashboard, reopens the same results, and edits and deletes the tile', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration(<<<'CONFIG'
        $home_dash = true;
        $managed_home_dash = false;
        CONFIG);
    $user = (int) $GLOBALS['userref'];
    $term = 'Tiletest' . generateRandomTestID(14);
    $renamed = $term . ' edited';
    $resource = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
    expect(upload_file($resource, no_exif: true, file_path: testFixture('Images/image_1.jpg'), deletesource: false))
        ->toBeTrue();
    $errors = [];
    expect(update_field($resource, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
    $fixtures->onCleanup(static function () use ($term, $renamed): void {
        foreach (ps_array('SELECT ref AS value FROM dash_tile WHERE title IN (?, ?)', ['s', $term, 's', $renamed]) as $tile) {
            delete_dash_tile($tile, true, true);
        }
    });

    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $search = generateURL($this->browserUrl('/pages/search.php'), ['search' => $term, 'archive' => 0]);
    $result = "#CentralSpace #ResourceShell{$resource}";
    $browser->openAndWaitFor($search, $result)->waitForAjax();
    $actions = $page->page()->locator('#CentralSpace select:has(option[value^="save_search_to_dash"])');
    $option = $actions->locator('option[value^="save_search_to_dash"]')->getAttribute('value');
    expect($option)->toBeString();
    $actions->selectOption($option);

    $submit = '#create_dash input[name=Submit]';
    $title_input = '#create_dash #previewtitle';
    $page->page()->locator($title_input)->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $page->assertValue($title_input, $term);
    $page->page()->locator('#previewdashtile img')->first()->waitFor(['state' => 'visible']);
    $page->page()->locator('#create_dash #all_users_false')->check();
    $page->page()->locator($submit)->click(['noWaitAfter' => true]);

    $tile_ref = static fn (string $title): int => (int) ps_value(
        'SELECT ref AS value FROM dash_tile WHERE title = ?', ['s', $title], 0
    );
    $page->page()->locator('#CentralSpace #HomePanelContainer')->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $tile = $tile_ref($term);
    expect($tile)->toBeGreaterThan(0);
    $tile_link = "#CentralSpace a.DashTile[tile='{$tile}']";
    $page->page()->locator($tile_link)->waitFor(['state' => 'visible']);
    $page->page()->locator($tile_link)->click(['noWaitAfter' => true]);
    $page->page()->locator($result)->waitFor(['state' => 'visible']);
    $page->assertNotPresent("#CentralSpace #ResourceShell{$control}");

    $browser->openAndWaitFor($this->browserUrl('/pages/home.php'), $tile_link);
    $edit = "{$tile_link} .tool.edit a[href*=\"edit={$tile}\"]";
    $page->hover($tile_link);
    $page->page()->locator($edit)->click(['noWaitAfter' => true]);
    $page->page()->locator($title_input)->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $page->fill($title_input, $renamed)->fill('#create_dash #previewtext', 'Revised tile copy');
    $page->page()->locator('#DashTileSize')->selectOption('double');
    $page->page()->locator('#create_dash .tlstyle[value=blank]')->check();
    $page->check('#create_dash #resource_count');
    $page->page()->locator($submit)->click(['noWaitAfter' => true]);
    $page->page()->locator('#CentralSpace #HomePanelContainer')->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $updated = $tile_ref($renamed);
    expect($updated)->toBeGreaterThan(0);
    $updated_link = "#CentralSpace a.DashTile[tile='{$updated}']";
    $page->page()->locator("{$updated_link}.DoubleWidthDashTile")->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $page->assertSeeIn($updated_link, $renamed)->assertSeeIn($updated_link, 'Revised tile copy');

    $record = get_tile($updated);
    expect((int) $record['resource_count'])->toBe(1)
        ->and($record['url'])->toContain('tlstyle=blank');
    $element_id = $page->page()->locator($updated_link)->getAttribute('id');
    expect($element_id)->toMatch('/^user_tile\d+$/');
    $user_tile = (int) substr($element_id, strlen('user_tile'));
    expect((int) get_user_tile($user_tile, $user)['dash_tile'])->toBe($updated);

    $page->hover($updated_link);
    $page->page()->locator(".dash-delete_{$user_tile}")->click(['noWaitAfter' => true]);
    $confirmation = $page->page()->locator('.ui-dialog[aria-describedby="trash_bin_delete_dialog"]');
    $confirmation->getByRole('button', ['name' => $GLOBALS['lang']['confirmdashtiledelete'], 'exact' => true])
        ->click(['noWaitAfter' => true]);
    $page->page()->locator($updated_link)->waitFor(['state' => 'detached']);
    expect(get_user_tile($user_tile, $user))->toBeFalse();
});

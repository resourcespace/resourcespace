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

test('keeps dark-mode navigation and all collection bar states usable at 360 pixels', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration(<<<'CONFIG'
        $user_pref_appearance = 'device';
        $thumbs_default = 'hide';
        $resource_view_modal = false;
        CONFIG);
    $user = (int) $GLOBALS['userref'];
    $previous = (int) get_user($user)['current_collection'];
    $collection = $fixtures->collection($user, 'Responsive ' . generateRandomTestID(12));
    $fixtures->onCleanup(static fn () => set_user_collection($user, $previous));
    set_user_collection($user, $collection);
    $refs = [];
    for ($i = 0; $i < 8; $i++) {
        $ref = $fixtures->resource(1, 0);
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
        $refs[] = $ref;
    }

    $login = visit($this->browserUrl('/login.php'))->inDarkMode();
    $page = $this->loginAsAdmin($login);
    $browser = new BrowserPage($page);
    $holder = '#CollectionDiv .collection-bar-holder';
    $toggle = "{$holder} .collection-bar-toggle-button";
    $bar = "{$holder} .collection-bar-actions-section";
    $search = '#header-container #SearchBox input[name=search]';
    $assert_shell = static function () use ($page, $search): void {
        $page->assertVisible('#header-container')->assertVisible($search)->assertVisible('#CentralSpace');
        expect($page->script(<<<'JS'
            (() => {
                const style = getComputedStyle(document.documentElement);
                const dark = style.getPropertyValue('--colour-neutral-1000').trim();
                return dark !== '' && dark === style.getPropertyValue('--colour-surface-50').trim();
            })()
            JS))->toBeTrue();
    };

    $page->page()->setViewportSize(360, 780);
    $assert_shell();
    $page->page()->locator("{$holder}.state-closed")->waitFor(['state' => 'visible']);
    $page->click($toggle)->assertVisible("{$holder}.state-actions .collection-bar-actions-section");
    $page->assertVisible("{$bar} select[name=collection]");
    $page->click($toggle)->assertVisible("{$holder}.state-closed");
    $page->click($toggle)->click("{$bar} .show-thumbs-button");
    $page->assertVisible("{$holder}.state-thumbs .collection-bar-thumbnail-row");
    expect($page->page()->locator("{$holder} .collection-bar-resource-card")->count())->toBe(8);

    $scroller = "{$holder} .collection-bar-thumbnail-row";
    $page->click("{$holder} button.thumbnail-row-arrow.right");
    $page->script(<<<JS
        (() => new Promise((resolve, reject) => {
            const row = document.querySelector('{$scroller}');
            const start = performance.now();
            const check = () => {
                if (row.scrollLeft > 0) return resolve(true);
                if (performance.now() - start > 1500) return reject('Collection thumbnail row did not scroll');
                requestAnimationFrame(check);
            };
            check();
        }))()
        JS);
    $page->click($toggle)->assertVisible("{$holder}.state-closed");

    $query = generateURL($this->browserUrl('/pages/search.php'), [
        'search' => '!collection' . $collection, 'display' => 'thumbs',
    ]);
    $first_result = "#CentralSpace #ResourceShell{$refs[0]}";
    $browser->openAndWaitFor($query, $first_result);
    $assert_shell();
    $page->page()->setViewportSize(1280, 800);
    $assert_shell();
    $page->page()->setViewportSize(360, 780);
    $page->page()->locator($first_result . ' a.resource-card-image')->click(['noWaitAfter' => true]);
    $page->page()->locator('.backtoresults .upLink')->waitFor(['state' => 'visible']);
    $assert_shell();
});

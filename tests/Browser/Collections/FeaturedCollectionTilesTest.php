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
    setTestConfiguration('$featured_collection_tiles_per_batch_target = 1;');
});
afterEach(function () use (&$fixtures): void {
    setTestConfiguration('');
    $fixtures?->cleanUp();
});

test('keeps loaded featured collection tiles in complete rows after a responsive resize', function () use (
    &$fixtures,
): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $user = (int) $GLOBALS['userref'];
    $suffix = generateRandomTestID(12);
    $parent = $fixtures->collection($user, "Responsive FC parent {$suffix}");
    save_collection($parent, [
        'featured_collections_changes' => [
            'update_parent' => 0,
            'force_featured_collection_type' => true,
        ],
    ]);

    for ($index = 0; $index < 40; $index++) {
        $child = $fixtures->collection($user, "Responsive FC {$index} {$suffix}");
        save_collection($child, [
            'featured_collections_changes' => [
                'update_parent' => $parent,
                'force_featured_collection_type' => true,
            ],
        ]);
    }

    $page = $this->loginAsAdmin(visit($this->browserUrl('/login.php')));
    $page->page()->setViewportSize(1000, 420);
    $browser = new BrowserPage($page);
    $url = generateURL($this->browserUrl('/pages/collections_featured.php'), ['parent' => $parent]);
    $browser->openAndWaitFor($url, '.BasicsBox.FeaturedSimpleLinks')->startAjaxTracking()->waitForAjax(20_000, 750);

    $row_state = static function () use ($page): array {
        return $page->script(<<<'JS'
            (() => {
                const container = document.querySelector('.BasicsBox.FeaturedSimpleLinks');
                const columns = getComputedStyle(container).gridTemplateColumns.split(' ').filter(Boolean).length;
                const tiles = container.querySelectorAll(
                    'a.FeaturedSimpleTile:not(.featured-collection-action-tile)'
                ).length;

                return {
                    columns,
                    tiles,
                    placeholders: container.querySelectorAll('.featured-collection-row-placeholder').length,
                    hasMore: document.getElementById('featured-collection-batch-loading') !== null,
                };
            })()
            JS);
    };

    $initial = $row_state();
    expect($initial['columns'])->toBe(3)
        ->and($initial['tiles'])->toBeGreaterThan(0)
        ->and($initial['tiles'] % $initial['columns'])->toBe(0)
        ->and($initial['placeholders'])->toBe(0)
        ->and($initial['hasMore'])->toBeTrue();

    $page->page()->setViewportSize(1200, 420);
    $browser->waitForAjax(20_000, 750);

    $resized = $row_state();
    expect($resized['columns'])->toBe(4)
        ->and($resized['tiles'])->toBeGreaterThanOrEqual($initial['tiles'])
        ->and($resized['tiles'] % $resized['columns'])->toBe(0)
        ->and($resized['placeholders'])->toBe(0)
        ->and($resized['hasMore'])->toBeTrue();
});

test('ignores an obsolete batch callback after CentralSpace navigation', function (
    string $navigationMethod,
) use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $user = (int) $GLOBALS['userref'];
    $suffix = generateRandomTestID(12);
    $parents = [];
    $childCounts = [2, 16];

    foreach ($childCounts as $parentIndex => $childCount) {
        $parent = $fixtures->collection($user, "Batch race parent {$parentIndex} {$suffix}");
        save_collection($parent, [
            'featured_collections_changes' => [
                'update_parent' => 0,
                'force_featured_collection_type' => true,
            ],
        ]);
        $parents[] = $parent;

        for ($index = 0; $index < $childCount; $index++) {
            $childName = "Batch race {$parentIndex} child {$index} {$suffix}";
            $child = $fixtures->collection($user, $childName);
            save_collection($child, [
                'featured_collections_changes' => [
                    'update_parent' => $parent,
                    'force_featured_collection_type' => true,
                ],
            ]);
        }
    }

    [$parentA, $parentB] = $parents;
    $page = $this->loginAsAdmin(visit($this->browserUrl('/login.php')));
    $page->page()->setViewportSize(1000, 420);
    $urlA = generateURL($this->browserUrl('/pages/collections_featured.php'), ['parent' => $parentA]);
    $urlB = generateURL($this->browserUrl('/pages/collections_featured.php'), ['parent' => $parentB]);

    $page->script(sprintf(<<<'JS'
        (() => {
            const originalAjax = window.jQuery.ajax.bind(window.jQuery);
            const gates = {
                pageA: {parent: %d, captured: false, ready: false, aborted: false},
                pageB: {parent: %d, captured: false, ready: false, aborted: false},
            };

            window.__featuredCollectionBatchRace = {
                gates,
                release(name) {
                    const gate = gates[name];
                    if (!gate.ready) {
                        throw new Error(`Featured Collection batch gate ${name} is not ready`);
                    }
                    gate.success?.();
                    gate.error?.();
                    gate.complete?.();
                    gate.released = true;
                },
            };

            window.jQuery.ajax = function(url, options) {
                const settings = typeof url === 'object' ? url : options;
                const requestUrl = typeof url === 'object' ? settings?.url : url;
                const parent = parseInt(settings?.data?.parent, 10);
                const gate = Object.values(gates).find(function(candidate) {
                    return candidate.parent === parent && !candidate.captured;
                });

                if (!requestUrl?.includes('featured_collection_tiles.php') || !gate) {
                    return originalAjax(...arguments);
                }

                gate.captured = true;
                const gatedSettings = {
                    ...settings,
                    success: function(...args) {
                        const callbackContext = this;
                        gate.success = function() {
                            settings.success?.apply(callbackContext, args);
                        };
                    },
                    error: function(...args) {
                        const callbackContext = this;
                        gate.error = function() {
                            settings.error?.apply(callbackContext, args);
                        };
                    },
                    complete: function(...args) {
                        const callbackContext = this;
                        gate.complete = function() {
                            settings.complete?.apply(callbackContext, args);
                        };
                        gate.ready = true;
                    },
                };

                const request = typeof url === 'object'
                    ? originalAjax(gatedSettings)
                    : originalAjax(url, gatedSettings);
                const originalAbort = request.abort.bind(request);
                request.abort = function(...args) {
                    gate.aborted = true;
                    return originalAbort(...args);
                };

                return request;
            };
        })()
        JS, $parentA, $parentB));

    $page->script('CentralSpaceLoad(' . encode_js_value($urlA) . ', true)');
    $page->script(<<<'JS'
        (() => new Promise((resolve, reject) => {
            const deadline = Date.now() + 15000;
            const check = () => {
                if (window.__featuredCollectionBatchRace.gates.pageA.ready) {
                    resolve(true);
                    return;
                }
                if (Date.now() >= deadline) {
                    reject(new Error('Timed out waiting for the delayed page A batch'));
                    return;
                }
                window.setTimeout(check, 25);
            };
            check();
        }))()
        JS);

    if ($navigationMethod === 'CentralSpaceLoad') {
        $page->script('CentralSpaceLoad(' . encode_js_value($urlB) . ', true)');
    } else {
        $searchTerm = encode_js_value("Batch race search {$suffix}");
        $page->script(<<<JS
            (() => {
                const form = document.getElementById('simple_search_form');
                window.__featuredCollectionBatchRace.originalContainer =
                    document.querySelector('.BasicsBox.FeaturedSimpleLinks');
                form.querySelector('[name="search"]').value = {$searchTerm};
                CentralSpacePost(form, true);
            })()
            JS);
        $page->script(<<<'JS'
            (() => new Promise((resolve, reject) => {
                const deadline = Date.now() + 15000;
                const check = () => {
                    const race = window.__featuredCollectionBatchRace;
                    if (
                        !document.documentElement.contains(race.originalContainer)
                        && race.gates.pageA.aborted
                    ) {
                        resolve(true);
                        return;
                    }
                    if (Date.now() >= deadline) {
                        reject(new Error('Timed out waiting for CentralSpacePost cleanup'));
                        return;
                    }
                    window.setTimeout(check, 25);
                };
                check();
            }))()
            JS);
        $page->script('CentralSpaceLoad(' . encode_js_value($urlB) . ', true)');
    }
    $page->script(sprintf(<<<'JS'
        (() => new Promise((resolve, reject) => {
            const deadline = Date.now() + 15000;
            const check = () => {
                const container = document.querySelector('.BasicsBox.FeaturedSimpleLinks');
                const loader = container?.querySelector('#featured-collection-batch-loading');
                if (
                    window.featuredCollectionConfig?.parent === %d
                    && window.__featuredCollectionBatchRace.gates.pageB.ready
                    && loader
                ) {
                    resolve(true);
                    return;
                }
                if (Date.now() >= deadline) {
                    reject(new Error('Timed out waiting for the delayed page B batch'));
                    return;
                }
                window.setTimeout(check, 25);
            };
            check();
        }))()
        JS, $parentB));

    $page->script("window.__featuredCollectionBatchRace.release('pageA')");
    $pageBState = $page->script(<<<'JS'
        (() => {
            const container = document.querySelector('.BasicsBox.FeaturedSimpleLinks');
            return {
                parent: window.featuredCollectionConfig?.parent,
                obsoleteRequestAborted: window.__featuredCollectionBatchRace.gates.pageA.aborted,
                hasLoader: Boolean(container?.querySelector('#featured-collection-batch-loading')),
                tiles: container?.querySelectorAll(
                    'a.FeaturedSimpleTile:not(.featured-collection-action-tile)'
                ).length,
            };
        })()
        JS);
    expect($pageBState['parent'])->toBe($parentB)
        ->and($pageBState['obsoleteRequestAborted'])->toBeTrue()
        ->and($pageBState['hasLoader'])->toBeTrue()
        ->and($pageBState['tiles'])->toBeGreaterThan(0)
        ->and($pageBState['tiles'])->toBeLessThan($childCounts[1]);

    $page->script("window.__featuredCollectionBatchRace.release('pageB')");
    $finalTileCount = $page->script(sprintf(<<<'JS'
        (() => new Promise((resolve, reject) => {
            const expectedTiles = %d;
            const deadline = Date.now() + 20000;
            const check = () => {
                const container = document.querySelector('.BasicsBox.FeaturedSimpleLinks');
                const loader = container?.querySelector('#featured-collection-batch-loading');
                const tiles = container?.querySelectorAll(
                    'a.FeaturedSimpleTile:not(.featured-collection-action-tile)'
                ).length ?? 0;

                if (tiles === expectedTiles && !loader) {
                    resolve(tiles);
                    return;
                }
                if (Date.now() >= deadline) {
                    reject(new Error(`Timed out with ${tiles} of ${expectedTiles} page B tiles loaded`));
                    return;
                }
                loader?.scrollIntoView({block: 'center'});
                window.setTimeout(check, 50);
            };
            check();
        }))()
        JS, $childCounts[1]));

    expect($finalTileCount)->toBe($childCounts[1]);
})->with(['CentralSpaceLoad', 'CentralSpacePost']);

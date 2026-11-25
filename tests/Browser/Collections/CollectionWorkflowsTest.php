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

test('a collection can be created and renamed and its resource membership can be changed', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $user = (int) $GLOBALS['userref'];
    $previous = (int) get_user($user)['current_collection'];
    $fixtures->onCleanup(fn () => set_user_collection($user, $previous));
    $resource = $fixtures->resource(1, 0);
    $name = 'Collection ' . generateRandomTestID(16);
    $renamed = $name . ' renamed';

    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $browser->openAndWaitFor($this->browserUrl('/pages/collection_manage.php'), '#newcollection');
    $page->fill('#newcollection', $name);
    $page->page()->locator('form:has(#newcollection) input[type=submit]')->click(['noWaitAfter' => true]);
    $page->page()->locator('#collectionform input[name=name]')->waitFor(['state' => 'visible']);
    parse_str((string) parse_url($page->page()->url(), PHP_URL_QUERY), $params);
    $collection = (int) ($params['ref'] ?? 0);
    expect($collection)->toBeGreaterThan(0);
    $fixtures->trackCollection($collection);
    $page->assertValue('#collectionform input[name=name]', $name)->fill('#collectionform input[name=name]', $renamed);
    $browser->click('#collectionform input[name=save]')->waitForAjax();
    expect(get_collection($collection)['name'])->toBe($renamed);

    $view = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $resource]);
    $browser->openAndWaitFor($view, 'a.addToCollection');
    $page->script("document.querySelector('a.addToCollection').click()");
    $browser->waitForAjax();
    expect(array_map('intval', get_collection_resources($collection)))->toContain($resource);

    $collection_view = generateURL($this->browserUrl('/pages/view.php'), [
        'ref' => $resource, 'search' => '!collection' . $collection,
    ]);
    $browser->openAndWaitFor($collection_view, 'a.removeFromCollection');
    $page->script('window.confirm = () => true');
    $page->script("document.querySelector('a.removeFromCollection').click()");
    $browser->waitForAjax();
    expect(array_map('intval', get_collection_resources($collection)))->not->toContain($resource);
});

test('a visitor loses access when an administrator revokes a collection share', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration('$share_password_required = false;');
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Shared ' . generateRandomTestID(16));
    $resource = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($resource, $collection))->toBeTrue();

    $admin = $this->loginAsAdmin();
    $browser = new BrowserPage($admin);
    $share = generateURL($this->browserUrl('/pages/collection_share.php'), [
        'ref' => $collection,
        'generateurl' => 'true',
    ]);
    $generate = '#collectionform input[name=generateurl]';
    $url_input = '#collectionform input.URLDisplay[value*="k="]';
    $browser->openAndWaitFor($share, $generate);
    $admin->page()->locator($generate)->click(['noWaitAfter' => true]);
    $admin->page()->locator($url_input)->waitFor(['state' => 'visible']);
    $link = $admin->page()->locator($url_input)->getAttribute('value');
    expect($link)->toBeString()->not->toBeEmpty();
    parse_str((string) parse_url($link, PHP_URL_QUERY), $params);
    $key = $params['k'] ?? '';
    expect($key)->not->toBeEmpty();
    $fixtures->onCleanup(fn () => delete_collection_access_key($collection, $key));

    $visitor = visit($link);
    $resource_result = "#CentralSpace #ResourceShell{$resource}";
    $visitor->assertVisible($resource_result);

    $manage = generateURL($this->browserUrl('/pages/collection_share.php'), ['ref' => $collection]);
    $revoke = 'a[onclick*="deleteaccess"][onclick*="' . $key . '"]';
    $browser->openAndWaitFor($manage, $revoke);
    $admin->script('window.confirm = () => true');
    $admin->page()->locator($revoke)->click(['noWaitAfter' => true]);
    $admin->page()->locator($revoke)->waitFor(['state' => 'detached']);
    expect(check_access_key($resource, $key))->toBeFalse();

    $visitor->page()->goto($link, ['waitUntil' => 'domcontentloaded']);
    $visitor->assertNotPresent($resource_result);
});

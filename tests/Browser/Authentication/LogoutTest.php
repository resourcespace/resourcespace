<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;

test('user can log out', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = $this
        ->loginAsAdmin()
        ->click('#header-container .actions .get-menu-content[title="My account"]')
        ->assertNoJavaScriptErrors();

    $page->page()->locator('#modal .TileNav a[href*="logout=true"]')
        ->click(['noWaitAfter' => true, 'timeout' => 15_000]);
    $page->page()->locator('#loginform input[name="username"]')->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $page->assertPathIs('/login.php');
});

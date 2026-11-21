<?php

declare(strict_types=1);

test('user can log out', function (): void {
    $this
        ->loginAsAdmin()
        ->click('#header-container .actions .get-menu-content[title="My account"]')
        ->assertNoJavaScriptErrors()
        ->click('#modal .TileNav ul > li[title="Log out of ResourceSpace"]')
        ->assertPathIs('/login.php');
});

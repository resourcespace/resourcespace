<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;

test('installation Check page (system menu) reads all OK', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $this
        ->loginAsAdmin()
        ->click('#header-container .actions .get-menu-content:first-of-type')
        ->assertSee('System')
        ->click('li[title="System setup area"] a')
        ->assertSee('Installation check')
        ->click('li[title="Check the system setup and dependencies"] a')
        ->wait(1)
        ->assertPathIs('/pages/check.php')
        ->assertDontSeeIn('.InfoTable', 'Fail');
});

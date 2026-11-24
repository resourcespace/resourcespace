<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;

test('search filter panel can be opened and closed', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $this
        ->loginAsAdmin()
        ->click('.header-search-field .input-wrapper > button:last-of-type')
        ->assertVisible('.header-search-field #search-filters-panel')
        ->click('.header-search-field .input-wrapper > button:last-of-type')
        ->assertMissing('.header-search-field #search-filters-panel');
});

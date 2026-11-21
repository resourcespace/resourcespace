<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

final class ResourceTypesPage extends BrowserPage
{
    public function open(): self
    {
        $this->page
            ->click('#header-container .actions .get-menu-content:first-of-type')
            ->click('li[title="System setup area"] a')
            ->click('li[title="Create and configure resource types"] a')
            ->assertPathIs('/pages/admin/admin_resource_types.php');

        $this->waitForAjax();

        $this->page->assertPresent('.BasicsBox > a[href*="restype_order_by"]');

        return $this;
    }

    public function create(string $name): self
    {
        $this->page
            ->assertPathIs('/pages/admin/admin_resource_types.php')
            ->fill('newtype', $name)
            ->click('.Inline > input[type="submit"]');

        return $this;
    }

    public function selectResourceType(int $id): self
    {
        $this->page->click("#restype_sort_{$id} > td:nth-child(2) .ListTitle > a");

        return $this;
    }
}

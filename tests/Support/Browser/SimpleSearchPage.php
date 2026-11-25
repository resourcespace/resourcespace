<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

final class SimpleSearchPage extends BrowserPage
{
    public function toggleSearchFilterPanel(): self
    {
        $this->page->click('.header-search-field .input-wrapper > button:last-of-type');

        return $this;
    }

    public function openResourceTypeSelector(): self
    {
        $this->page->click('.header-search-field .field-input .select2 .select2-selection--multiple');

        return $this;
    }

    public function goToAdvancedSearch(): self
    {
        $this->toggleSearchFilterPanel();
        $this->page->page()->getByRole('link', ['name' => 'Advanced search', 'exact' => true])
            ->click(['noWaitAfter' => true]);

        return $this;
    }
}

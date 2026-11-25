<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

use DateTimeInterface;

final class EditPage extends BrowserPage
{
    public function selectResourceType(string $name): self
    {
        $this->page->select('resource_type', $name);
        $this->waitForAjax();

        return $this;
    }

    public function fillTitle(string $value): self
    {
        $this->page->fill("field_{$GLOBALS['view_title_field']}", $value);

        return $this;
    }

    /**
     * Upload an original whose metadata was saved before upload.
     * Some workflow states leave the uploader open after processing.
     * @return self The current edit page wrapper.
     */
    public function uploadSavedMetadata(): self
    {
        $browser = $this->page->page();
        $browser->locator(UiElementSelector::UppyActionUploadButton->value)->click(['noWaitAfter' => true]);
        $this->page->assertScript(<<<'JS'
            document.querySelector('#CentralSpace #SearchResultFound') !== null
                || window.upRedirBlock === true
                || window.resource_keys?.length === 1
            JS);
        $this->waitForAjax();
        expect($this->page->script('window.upRedirBlock === true'))->toBeFalse('Resource processing failed.');

        return $this;
    }

    public function refineMetadataLockField(int $id): self
    {
        $this->page->click("#lock_icon_{$id}");

        return $this;
    }

    public function refineMetadataSaveAndNext(): self
    {
        $this->page->click('#edit_save_button');

        return $this;
    }

    public function refineMetadataSaveAllWithLockedValues(): self
    {
        $this->page->click('#edit_save_auto_button');

        return $this;
    }

    /**
     * Fill a (non-native) date metadata field, supporting complete and partial dates.
     *
     * @param DateTimeInterface|int $year A date object or year
     * @param string|null $month Month name (e.g. October)
     * @param string|null $day Day of the month
     *
     * @return self
     */
    public function fillDateParts(
        int $field_id,
        DateTimeInterface|int $year,
        ?string $month = null,
        ?string $day = null,
        ?string $hour = null,
        ?string $minute = null,
    ): self {
        if ($year instanceof DateTimeInterface) {
            $hour = $year->format('H');
            $minute = $year->format('i');
            $day = $year->format('d');
            $month = $year->format('F');
            $year = $year->format('Y');
        }

        $field_part = prefix_value("field_{$field_id}");

        $this->page->fill($field_part('-y'), (string) $year);
        $this->page->select($field_part('-m'), $month ?? '');
        $this->page->select($field_part('-d'), $day ?? '');

        if ($hour !== null || $hour !== '00') {
            $this->page->select($field_part('-h'), $hour);
        }

        if ($minute !== null || $minute !== '00') {
            $this->page->select($field_part('-i'), $minute);
        }

        return $this;
    }
}

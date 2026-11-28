<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;

it('autosaves a checkbox and refreshes its dependent setting', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $browser = (new BrowserPage($this->loginAsAdmin()))
        ->navigate($this->browserUrl('/pages/admin/admin_system_config.php'), [
            'waitUntil' => 'domcontentloaded',
            'timeout' => 15000,
        ]);
    $page = $browser->getPage();
    $browser_page = $page->page();
    $section = $browser_page->locator('h3:has(+ #SystemConfigUploadingSection)');
    $option = $browser_page->locator('#replace_resource_preserve_option');
    $dependent = $browser_page->locator('#replace_resource_preserve_default');

    $option->waitFor(['state' => 'attached']);
    $section->click();
    $page->assertNotChecked('#replace_resource_preserve_option');
    $dependent->waitFor(['state' => 'detached']);
    $option->check();
    $dependent->waitFor(['state' => 'visible', 'timeout' => 15000]);
    $page->assertChecked('#replace_resource_preserve_option');

    $option->uncheck();
    $dependent->waitFor(['state' => 'detached', 'timeout' => 15000]);
    $page->assertNotChecked('#replace_resource_preserve_option');
})->after(function (): void {
    remove_config_option(null, 'replace_resource_preserve_option');
});

it('edits download usage options without reloading System Configuration', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $browser = (new BrowserPage($this->loginAsAdmin()))
        ->navigate($this->browserUrl('/pages/admin/admin_system_config.php'), [
            'waitUntil' => 'domcontentloaded',
            'timeout' => 15000,
        ]);
    $page = $browser->getPage();
    $browser_page = $page->page();
    $option = $browser_page->locator('#download_usage');
    $editor_selector = '#question_download_usage_options';
    $editor = $browser_page->locator($editor_selector);

    $option->waitFor(['state' => 'attached']);
    $browser_page->locator('h3:has(+ #SystemConfigDownloadsSection)')->click();
    $editor->waitFor(['state' => 'hidden']);
    $page->script('window.downloadUsageOriginalPage = true');
    $option->check();
    $editor->waitFor(['state' => 'visible']);
    expect($page->script('window.downloadUsageOriginalPage === true'))->toBeTrue();
    expect($page->script(<<<'JS'
        (() => {
            const question = document.getElementById('question_download_usage_options');
            const left = element => element.getBoundingClientRect().left;
            const inputs = Array.from(question.querySelectorAll('[data-option-id] input'));
            const column = left(inputs[0]);
            return getComputedStyle(question).display === 'flex'
                && inputs.every(input => left(input) === column)
                && left(question.querySelector('.FormHelp')) === column
                && left(question.querySelector('label')) < column;
        })()
        JS))->toBeTrue();
    $browser_page->locator("$editor_selector [data-remove-option] .icon-x")->first()
        ->waitFor(['state' => 'visible']);

    $browser_page->locator("$editor_selector [data-option-id='0'] input")->fill('Printed publications');
    $browser_page->locator("$editor_selector [data-option-id='1'] [data-remove-option]")->click();
    $browser_page->locator("$editor_selector [data-add-option]")->click();
    $browser_page->locator("$editor_selector [data-option-id='5'] input")->fill('Exhibition');
    $browser_page->locator("$editor_selector [data-option-id='5'] [data-move-option-up]")->click();
    $page->script(<<<'JS'
        (() => {
            const input = document.getElementById('download_usage_options');
            window.downloadUsageVersion = input.dataset.optionVersion;
            input.dataset.optionVersion = 'outdated';
        })()
        JS);
    $browser_page->locator("$editor_selector [data-save-options]")->click();
    $page->assertSeeIn(
        '#AutoSaveStatus-download_usage_options',
        'Could not save the options. Refresh the page to get the latest options.'
    );
    $page->script(<<<'JS'
        document.getElementById('download_usage_options').dataset.optionVersion = window.downloadUsageVersion
        JS);
    $browser_page->locator("$editor_selector [data-save-options]")->click();
    $browser_page->locator('#AutoSaveStatus-download_usage_options')->waitFor(['state' => 'hidden']);
    $stored = null;
    expect(get_config_option([], 'download_usage_options', $stored))->toBeTrue();
    expect(json_decode($stored, true))->toBe([
        'active' => [0 => 'Printed publications', 2 => 'Web', 3 => 'TV', 5 => 'Exhibition', 4 => 'Other'],
        'retired' => [1 => 'Print'],
        'order' => [0, 2, 3, 5, 4],
    ]);

    $option->uncheck();
    $editor->waitFor(['state' => 'hidden']);
    expect($page->script('window.downloadUsageOriginalPage === true'))->toBeTrue();
})->after(function (): void {
    remove_config_option(null, 'download_usage');
    remove_config_option(null, 'download_usage_options');
});

it('adds, reorders and removes metadata fields using the search field pickers', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $browser = (new BrowserPage($this->loginAsAdmin()))
        ->navigate($this->browserUrl('/pages/admin/admin_system_config.php'), [
            'waitUntil' => 'domcontentloaded',
            'timeout' => 15000,
        ]);
    $page = $browser->getPage();
    $browser_page = $page->page();
    $browser_page->locator('h3:has(+ #SystemConfigSearchSection)')->click();
    foreach (CONFIG_SEARCH_FIELD_LISTS as $setting) {
        $picker = $browser_page->locator("#question_{$setting} select[data-option-value]");
        $picker->first()->waitFor(['state' => 'visible']);
    }

    $editor = '#question_sort_fields';
    $browser_page->locator("$editor [data-add-option]")->click();
    $browser_page->locator("$editor [data-option-id='1'] select")->selectOption('8');
    $browser_page->locator("$editor [data-option-id='1'] [data-move-option-up]")->click();
    $browser_page->locator("$editor [data-save-options]")->click();
    $browser_page->locator('#AutoSaveStatus-sort_fields')->waitFor(['state' => 'hidden', 'timeout' => 15000]);
    $stored = null;
    expect(get_config_option([], 'sort_fields', $stored))->toBeTrue()->and($stored)->toBe('8,12');
    $browser_page->locator('#default_sort option[value="field8"]')->waitFor(['state' => 'attached']);

    $browser_page->locator("$editor [data-option-id='0'] [data-remove-option]")->click();
    $browser_page->locator("$editor [data-save-options]")->click();
    $browser_page->locator('#AutoSaveStatus-sort_fields')->waitFor(['state' => 'hidden', 'timeout' => 15000]);
    expect(get_config_option([], 'sort_fields', $stored))->toBeTrue()->and($stored)->toBe('8');
    $browser_page->locator('#default_sort option[value="field12"]')->waitFor(['state' => 'detached']);
})->after(function (): void {
    remove_config_option(null, 'sort_fields');
});

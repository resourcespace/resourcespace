<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

/**
 * Browser interactions for managing a resource's alternative files.
 */
final class AlternativeFilesPage extends BrowserPage
{
    /**
     * Open the full-page alternative file manager.
     *
     * @param int $resource Resource reference
     * @return self The current browser wrapper
     */
    public function manage(int $resource): self
    {
        $origin = $this->page->script('window.location.origin');
        $this->page->navigate(generateURL("{$origin}/pages/alternative_files.php", ['ref' => $resource]));

        return $this->waitForAjax()->assertPresent('#altlistitems');
    }

    /**
     * Open the resource View page, including its alternative download options.
     *
     * @param int $resource Resource reference
     * @return self The current browser wrapper
     */
    public function view(int $resource): self
    {
        $origin = $this->page->script('window.location.origin');
        $this->page->navigate(generateURL("{$origin}/pages/view.php", ['ref' => $resource]));

        return $this->waitForAjax()->assertPresent('#ResourceDownloadOptions');
    }

    /**
     * Upload fixtures through the alternative file manager and return to View.
     *
     * @param int $resource Resource reference
     * @param string ...$paths Absolute fixture paths to queue in Uppy
     * @return self The current browser wrapper
     */
    public function uploadFiles(int $resource, string ...$paths): self
    {
        $this
            ->manage($resource)
            ->click('a[href*="/pages/upload_batch.php"][href*="alternative="]')
            ->waitForAjax()
            ->assertPathIs('/pages/upload_batch.php')
            ->assertQueryStringHas('alternative', (string) $resource);

        foreach ($paths as $path) {
            $this->addFileToUppy($path);
        }

        return $this
            ->uploadAndWaitFor('#ResourceDownloadOptions')
            ->assertPathIs('/pages/view.php')
            ->assertQueryStringHas('ref', (string) $resource);
    }

    /**
     * Resolve an uploaded alternative belonging to this resource by its fixture filename.
     *
     * @param int $resource Resource reference
     * @param string $fixture Relative path within Fixtures/Files
     * @return int Alternative reference; fails the test unless exactly one matches
     */
    public function expectUploadedFileId(int $resource, string $fixture): int
    {
        $files = array_values(array_filter(
            get_alternative_files($resource),
            static fn (array $file): bool => $file['name'] === basename($fixture),
        ));
        expect($files)->toHaveCount(1);

        return (int) $files[0]['ref'];
    }

    /**
     * Verify a loaded browser image serves the bytes of the expected generated file.
     *
     * @param string $selector Image selector
     * @param string $path Expected preview file on disk
     * @return self The current wrapper; fails for missing/broken images or different bytes
     */
    public function assertImageMatchesFile(string $selector, string $path): self
    {
        expect($path)->toBeFile();
        $js_selector = encode_js_value($selector);
        $expected_dimensions = getimagesize($path);
        expect($expected_dimensions)->not->toBeFalse();
        $width = $expected_dimensions[0];
        $height = $expected_dimensions[1];
        $this->assertVisible($selector)->assertScript(<<<JS
            () => {
                const image = document.querySelector({$js_selector});
                return Boolean(image && image.complete && image.naturalWidth === {$width}
                    && image.naturalHeight === {$height});
            }
        JS);
        $hash = $this->script(<<<JS
            async () => {
                const image = document.querySelector({$js_selector});
                const response = await fetch(image.currentSrc, {credentials: 'same-origin'});
                if (!response.ok) throw new Error('Preview request failed: ' + response.status);
                const digest = await crypto.subtle.digest('SHA-256', await response.arrayBuffer());
                return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
            }
        JS);
        expect($hash)->toBe(hash_file('sha256', $path));

        return $this;
    }
}

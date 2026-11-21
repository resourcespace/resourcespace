<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

use Pest\Browser\Api\AwaitableWebpage;
use RuntimeException;

/**
 * Generic browser session wrapper for ResourceSpace browser tests.
 *
 * Navigation does not change the wrapper type. Subclasses add convenient feature-specific interactions.
 *
 * Browser API calls that aren't implemented by the page object itself are delegated to Pest's AwaitableWebpage instance.
 *
 * @mixin AwaitableWebpage
 */
class BrowserPage
{
    public function __construct(
        protected AwaitableWebpage $page,
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->page->{$method}(...$arguments);

        return $result === $this->page ? $this : $result;
    }

    /**
     * Install jQuery AJAX request tracking on the current page.
     *
     * Each active request is recorded when jQuery fires `ajaxSend` and removed when `ajaxComplete` is called. The
     * recorded data is used by {@see self::waitForAjax()} to provide useful diagnostics if AJAX activity fails to settle.
     *
     * Calling this method more than once is safe; the event handlers are only installed once per page load.
     *
     * @return static The current browser wrapper for fluent chaining
     */
    public function startAjaxTracking(): static
    {
        $this->page->script(<<<'JS'
            (() => {
                if (typeof window.jQuery === 'undefined') {
                    return true;
                }

                if (window.__pestAjaxTrackingInstalled) {
                    return true;
                }

                window.__pestAjaxTrackingInstalled = true;
                window.__pestAjaxRequests = new Map();

                window.jQuery(document).on(
                    'ajaxSend.pestAjaxTracking',
                    (_event, xhr, settings) => {
                        window.__pestAjaxRequests.set(xhr, {
                            url: settings?.url ?? '(unknown URL)',
                            method: settings?.type ?? settings?.method ?? 'GET',
                            data: settings?.data ?? null,
                            contentType: settings?.contentType ?? null,
                            startedAt: Date.now(),
                        });
                    }
                );

                window.jQuery(document).on(
                    'ajaxComplete.pestAjaxTracking',
                    (_event, xhr) => {
                        window.__pestAjaxRequests.delete(xhr);
                    }
                );

                return true;
            })()
        JS);

        return $this;
    }

    /**
     * Wait until all jQuery AJAX activity on the page has settled.
     *
     * AJAX is considered complete once `jQuery.active` remains at zero for a short stability period. This avoids
     * returning during the small gap between chained AJAX requests.
     * Background polling can start after that period; a separate request-count assertion would race with it.
     *
     * CentralSpaceLoad can update the URL before replacing #CentralSpace, so wait before interacting with new content.
     *
     * If AJAX does not settle before the timeout, an exception is raised with diagnostic information about the current
     * number of active requests, the page URL and any outstanding AJAX requests recorded by {@see self::startAjaxTracking()}.
     *
     * Pages that do not use jQuery are treated as already settled.
     *
     * @param int $timeout Maximum wait in milliseconds
     * @param int $settle_time Required period of AJAX inactivity in milliseconds
     *
     * @return static The current browser wrapper for fluent chaining
     */
    public function waitForAjax(
        int $timeout = 10000,
        int $settle_time = 300,
    ): static
    {
        $this->page->script(<<<JS
            (() => new Promise((resolve, reject) => {
                const timeoutMs = {$timeout};
                const settleMs = {$settle_time};
                const pollIntervalMs = 50;

                const startedAt = Date.now();
                let idleSince = null;

                const getActiveRequests = () => {
                    if (!(window.__pestAjaxRequests instanceof Map)) {
                        return [];
                    }

                    const now = Date.now();

                    return [...window.__pestAjaxRequests.values()].map(request => ({
                        method: request.method,
                        url: request.url,
                        data: request.data,
                        contentType: request.contentType,
                        elapsedMs: now - request.startedAt,
                    }));
                };

                const check = () => {
                    // The page either doesn't use jQuery or it hasn't been initialised yet => nothing to wait for
                    if (typeof window.jQuery === 'undefined') {
                        resolve(true);
                        return;
                    }

                    const active = window.jQuery.active;

                    if (active === 0) {
                        idleSince ??= Date.now();

                        if (Date.now() - idleSince >= settleMs) {
                            resolve(true);
                            return;
                        }
                    } else {
                        idleSince = null;
                    }

                    if (Date.now() - startedAt >= timeoutMs) {
                        const activeRequests = getActiveRequests();
                        const requestDetails = activeRequests.length > 0
                            ? activeRequests
                                .map(request => {
                                    const data = request.data
                                        ? ` data=\${typeof request.data === 'string'
                                            ? request.data
                                            : JSON.stringify(request.data)}`
                                        : '';

                                    return `\n - \${request.method} \${request.url} (\${request.elapsedMs}ms)\${data}`;
                                })
                                .join(', ')
                            : 'No tracked request details available, please call `startAjaxTracking()` as early as possible after loading the page';

                        reject(new Error(
                            `Timed out waiting for AJAX after \${timeoutMs}ms. `
                            + `\nContext: jQuery.active=\${active}, url=\${window.location.href}. `
                            + `\nActive requests: \${requestDetails}`
                        ));
                        return;
                    }

                    setTimeout(check, pollIntervalMs);
                };

                check();
            }))()
        JS);

        return $this;
    }

    public function getPage(): AwaitableWebpage
    {
        return $this->page;
    }

    /**
     * Adds a local file fixture directly to the pages' Uppy instance.
     *
     * The file is read by PHP, base64 encoded, reconstructed as a JavaScript File object in the browser, and then added
     * to the globally exposed `uppy` instance.
     *
     * IMPORTANT: This only adds the file to Uppy's queue, it doesn't start the upload.
     *
     * @param string $path Absolute path to the local file fixture
     *
     * @throws RuntimeException If the file doesn't exist or can't be read
     */
    public function addFileToUppy(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Missing/Unable to read the upload file fixture: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to get contents of the upload file fixture: {$path}");
        }

        $base64 = json_encode(base64_encode($contents), JSON_THROW_ON_ERROR);
        $filename = json_encode(basename($path), JSON_THROW_ON_ERROR);
        $mimeType = json_encode(get_mime_type($path, file_based_detection: false)[0], JSON_THROW_ON_ERROR);

        $this->page->script(<<<JS
            (() => {
                if (typeof uppy === 'undefined') {
                    throw new Error('Uppy instance is not available on the page.');
                }

                const binary = atob({$base64});
                const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
                const file = new File([bytes], {$filename}, {type: {$mimeType}});

                uppy.addFile({
                    name: file.name,
                    type: file.type,
                    data: file,
                    source: 'browser-test',
                    isRemote: false,
                });
            })();
        JS);

        return $this;
    }

    /**
     * Start uploading the queued Uppy files and wait for the expected destination to be ready.
     *
     * Uppy transfers are not tracked by jQuery, so AJAX inactivity alone doesn't mean that uploading and server-side
     * processing have finished. Wait for the destination element to become visible, then wait for jQuery AJAX activity
     * to settle.
     *
     * The selector should identify something that appears after completion, rather than an element already present on
     * the upload page. Choose it for the expected use case flow, configuration, permissions, etc.
     *
     * @param string $selector Selector identifying the expected destination element
     * @param int $timeout Maximum time in milliseconds to wait for the destination element
     */
    public function uploadAndWaitFor(string $selector, int $timeout = 60000): static
    {
        $this->page->click(UiElementSelector::UppyActionUploadButton->value);
        $this->page->page()->waitForSelector($selector, ['state' => 'visible', 'timeout' => $timeout]);

        return $this->waitForAjax();
    }

    /**
     * Scroll the first element matching the given CSS selector into view.
     *
     * If no matching element is found, no action is taken.
     *
     * @param string $selector CSS selector used to locate the element
     */
    public function scrollIntoView(string $selector): static
    {
        $this->page->script(sprintf('document.querySelector(%s)?.scrollIntoView();', encode_js_value($selector)));

        return $this;
    }

    /**
     * Assert that the selected options of a <select> element match the expected option labels.
     *
     * Option values are ignored, use {@see assertSelected} for that instead. The selected options are compared using
     * their visible text. The order of the selected options does not matter.
     *
     * @param string $selector CSS selector used to locate the element
     * @param list<string> $expected The expected selected option labels
     */
    public function assertSelectedOptions(string $selector, array $expected): static
    {
        $selected = $this->page->script(
            sprintf(
                "[...document.querySelectorAll(%s + ' option:checked')].map(option => option.textContent.trim())",
                encode_js_value($selector),
            ),
        );

        expect($selected)->toEqualCanonicalizing($expected);

        return $this;
    }
}

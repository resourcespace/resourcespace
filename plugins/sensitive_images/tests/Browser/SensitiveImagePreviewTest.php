<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

it('blurs flagged thumbnails and reveals an unblurred preview in the lightbox', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Sensitive image previews require ImageMagick.');
    }
    require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $fixtures = new IntegrationFixtures(['data_joins']);
    try {
        $plugin = 'sensitive_images';
        $fixtures->plugin($plugin);
        $fixtures->plugin('lightbox_preview');
        $field = $fixtures->field('Sensitive image reason', 1);
        $GLOBALS['data_joins'][] = $field;
        check_db_structs();
        $flagged = $fixtures->resource(1, 0, file_extension: 'jpg');
        $standard = $fixtures->resource(1, 0, file_extension: 'jpg');
        foreach ([$flagged, $standard] as $resource) {
            $original = get_resource_path($resource, true, '', true, 'jpg');
            expect(copy(testFixture('Images/simple_with_embedded_metadata.jpg'), $original))->toBeTrue();
            create_previews($resource, extension: 'jpg', ingested: true, checksum_required: false);
            expect(get_resource_path($resource, true, 'pre', false, 'jpg'))->toBeFile();
        }
        $reason = 'Sensitive catalogue image';
        $errors = [];
        expect(update_field($flagged, $field, $reason, $errors))->toBeTrue();
        set_plugin_config($plugin, ['sensitive_images_field' => $field, 'sensitive_images_blur_level' => 8]);

        $login_page = visit($this->browserUrl('/login.php'));
        $page = $this->loginAsAdmin($login_page);
        $browser = new BrowserPage($page);
        $search = generateURL($this->browserUrl('/pages/search.php'), [
            'search' => '!list' . $flagged . ':' . $standard, 'display' => 'thumbs',
        ]);
        $flagged_card = '#CentralSpaceResources #ResourceShell' . $flagged;
        $standard_card = '#CentralSpaceResources #ResourceShell' . $standard;
        $browser->openAndWaitFor($search, $flagged_card . ' img');
        $page->assertScript("getComputedStyle(document.querySelector('{$flagged_card} img')).filter", 'blur(8px)')
            ->assertScript("getComputedStyle(document.querySelector('{$flagged_card}'), '::before').content", '"!"')
            ->assertScript("getComputedStyle(document.querySelector('{$flagged_card}'), '::before').color", 'rgb(255, 255, 255)')
            ->assertScript("getComputedStyle(document.querySelector('{$standard_card} img')).filter", 'none');

        set_plugin_config($plugin, ['sensitive_images_field' => $field, 'sensitive_images_blur_level' => 18]);
        $browser->openAndWaitFor($search, $flagged_card . ' img');
        $page->assertScript("getComputedStyle(document.querySelector('{$flagged_card} img')).filter", 'blur(18px)');
        $view = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $flagged]);
        $browser->openAndWaitFor($view, '#previewimage');
        $page->assertScript("getComputedStyle(document.querySelector('#previewimage')).filter", 'blur(34px)')
            ->assertScript("getComputedStyle(document.querySelector('#previewimagewrapper'), '::before').content.includes('{$reason}')");
        $page->page()->locator('#previewimage')->click([
            'position' => ['x' => 10, 'y' => 10],
            'noWaitAfter' => true,
        ]);
        $image = '#lightbox .lb-image';
        $page->page()->locator($image)->waitFor(['state' => 'visible', 'timeout' => 15_000]);
        $page->assertScript("getComputedStyle(document.querySelector('{$image}')).filter", 'none')
            ->assertScript("document.querySelector('{$image}').naturalWidth > 0");
    } finally {
        $fixtures->cleanUp();
        check_db_structs();
    }
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

beforeAll(fn () => setTestConfiguration(<<<'PHP'
    $upload_then_edit = true;
PHP));

afterAll(fn () => setTestConfiguration(''));

beforeEach(function (): void {
    $this->refineMetadataTitleSelector = "#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']";
});

test('upload page is displayed when files are uploaded before setting metadata', function (): void {
    (new BrowserPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->assertPathIs('/pages/upload_batch.php');
});

test('uploaded resources can be added to an existing collection', function (): void {
    $test_id = generateRandomTestID(16);
    $resource_title = "UploadThenEdit - {$test_id}";

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'Default Collection')
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources can be added to a new named collection', function (): void {
    $test_id = generateRandomTestID(16);
    $collection_name = "Collection - {$test_id}";
    $resource_title = "UploadThenEdit - {$test_id}";

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'new')
        ->fill('entercolname', $collection_name)
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSelectedOptions('.collection-bar-actions-section #collection', [$collection_name])
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources are not added to a collection (when intended so)', function (): void {
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'false')
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax();

    $resource_not_in_collections = ps_value(
        'SELECT r.ref AS `value`
           FROM `resource` r
           JOIN resource_node rn ON rn.resource = r.ref
           JOIN node n ON n.ref = rn.node
          WHERE n.name = ?
            AND r.ref > 0
            AND NOT EXISTS (SELECT 1 FROM collection_resource cr WHERE cr.resource = r.ref)',
        ['s', $resource_title],
        0,
    ) > 0;

    expect($resource_not_in_collections)->toBeTrue();
});

test('embedded metadata is not imported when disabled for an upload', function (): void {
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    // Upload a file
    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->check('no_exif')
        ->select('collection_add', 'false')
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax();

    $resource = $this->expectResourceIdByNodeName($resource_title);

    // Check embedded metadata is missing on the resource view page
    $page
        ->navigate($this->browserUrl("/pages/view.php?ref={$resource}"))
        ->waitForAjax()
        ->assertSee($resource_title)
        ->assertDontSee('Example image')
        ->assertDontSee('A description of the image');
});

test('locked metadata values can be applied to all uploaded resources (single field)', function (): void {
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->addFileToUppy(testFixture('Videos/short_5seconds.mp4'))
        ->uploadAndWaitFor($this->refineMetadataTitleSelector)
        ->assertPathIs('/pages/edit.php')
        ->assertQueryStringHas('upload_review_mode', '1')
        ->fillTitle($resource_title)
        ->refineMetadataLockField($GLOBALS['view_title_field'])
        ->refineMetadataSaveAllWithLockedValues()
        ->waitForAjax()
        // Check the first 3 resources all have the same title
        ->assertScript(
            sprintf(
                '[...document.querySelectorAll(\'.resource-card-title\')]
                    .slice(0, 3)
                    .every(el => el.textContent.trim() === %s);',
                encode_js_value($resource_title),
            ),
            true,
        );
});

test('locked metadata values can be applied to all uploaded resources (multiple fields)', function (): void {
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->addFileToUppy(testFixture('Videos/short_5seconds.mp4'))
        ->uploadAndWaitFor($this->refineMetadataTitleSelector)
        ->assertPathIs('/pages/edit.php')
        ->assertQueryStringHas('upload_review_mode', '1')
        ->fillTitle($resource_title)
        ->refineMetadataLockField($GLOBALS['view_title_field'])
        ->waitForAjax()
        // Select Keywords - Subject: Product
        ->check('nodes[73][]', '274')
        ->refineMetadataLockField(73)
        // Select Keywords - Event: Photo shoot
        ->check('nodes[74][]', '266')
        ->refineMetadataLockField(74)
        ->refineMetadataSaveAllWithLockedValues()
        ->waitForAjax();

    $resources = $page->script(
        sprintf(
            "[...document.querySelectorAll('.resource-card-title')]
                .slice(0, 3)
                .filter(el => el.textContent.trim() === %s)
                .map(el => el.querySelector('a').href);",
            encode_js_value($resource_title),
        ),
    );

    expect(count($resources))->toBe(3);

    foreach ($resources as $resource_url) {
        $page
            ->navigate($resource_url)
            ->waitForAjax()
            ->assertSee('Product')
            ->assertSee('Photo shoot');
    }
});

test('missing a single required field triggers a validation error and preserves entered date values', function (): void {
    $date = new DateTimeImmutable('2026-10-09 14:03');
    $date_field_part = prefix_value("field_{$GLOBALS['date_field']}");

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillDateParts($GLOBALS['date_field'], $date)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->assertSeeIn('.toastify.toastify-Error', 'This is a required field')
        ->assertPresent(".Question.FieldSaveError:has(> label[for='field_{$GLOBALS['view_title_field']}'])")
        ->assertValue($date_field_part('-y'), $date->format('Y'))
        ->assertSelected($date_field_part('-m'), $date->format('m'))
        ->assertSelected($date_field_part('-d'), $date->format('d'))
        ->assertSelected($date_field_part('-h'), $date->format('H'))
        ->assertSelected($date_field_part('-i'), $date->format('i'));
});

test('missing multiple required fields trigger a validation error and preserve entered date values', function (): void {
    $test_id = generateRandomTestID(16);
    $date = new DateTimeImmutable('2026-10-09 14:05');
    $date_field_part = prefix_value("field_{$GLOBALS['date_field']}");

    $this->rtf = create_resource_type_field("Test required field - {$test_id}");
    expect($this->rtf)->toBeInt();
    expect(
        save_resource_type_field(
            $this->rtf,
            get_resource_type_field_columns(),
            array_merge(get_resource_type_field($this->rtf), ['required' => 1, 'order_by' => 15]),
        ),
    )->toBeTrue();

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillDateParts($GLOBALS['date_field'], $date)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->assertPresent(".Question.FieldSaveError:has(> label[for='field_{$GLOBALS['view_title_field']}'])")
        ->assertPresent(".Question.FieldSaveError:has(> label[for='field_{$this->rtf}'])")
        ->assertCount('.toastify.toastify-Error:has-text("This is a required field")', 2)
        ->assertValue($date_field_part('-y'), $date->format('Y'))
        ->assertSelected($date_field_part('-m'), $date->format('m'))
        ->assertSelected($date_field_part('-d'), $date->format('d'))
        ->assertSelected($date_field_part('-h'), $date->format('H'))
        ->assertSelected($date_field_part('-i'), $date->format('i'));
})->after(fn () => delete_resource_type_field($this->rtf));

test('PDF and MP3 resources have a "View in browser" download option on their View page', function (): void {
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Audio/simple_short.mp3'))
        ->addFileToUppy(testFixture('Documents/basic.pdf'))
        ->uploadAndWaitFor($this->refineMetadataTitleSelector)
        ->assertQueryStringHas('upload_review_mode', '1')
        ->fillTitle($resource_title)
        ->refineMetadataLockField($GLOBALS['view_title_field'])
        ->waitForAjax()
        ->refineMetadataSaveAllWithLockedValues()
        ->waitForAjax();

    $resources = $this->expectResourceIdsByNodeName($resource_title);
    foreach ($resources as $resource) {
        $page
            ->navigate($this->browserUrl("/pages/view.php?ref={$resource}"))
            ->waitForAjax()
            ->assertSee($resource_title)
            ->assertVisible('.DownloadButton a:text-is("View in browser")');
    }
});

test('text from an uploaded PDF is stored in the configured extracted text field', function (): void {
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();

    expect(get_utility_path('pdftotext'))
        ->not
        ->toBeFalse('PDF text extraction requires pdftotext and a configured $pdftotext_path.');
    $text_field = (int) ($GLOBALS['extracted_text_field'] ?? 0);
    expect($text_field)->toBeGreaterThan(0, 'PDF text extraction requires $extracted_text_field.');

    $review_collection = -$GLOBALS['userref'];

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'false')
        ->addFileToUppy(testFixture('Documents/text_extraction.pdf'))
        ->uploadAndWaitFor($this->refineMetadataTitleSelector)
        ->assertQueryStringHas('upload_review_mode', '1');

    $resources = get_collection_resources($review_collection);
    expect($resources)->toHaveCount(1);

    $extracted_text = get_data_by_field($resources[0], $text_field);
    expect(preg_replace('/\s+/u', ' ', trim($extracted_text)))
        ->toBe('ResourceSpace PDF text extraction regression fixture. The uploaded document contains searchable text.');
});

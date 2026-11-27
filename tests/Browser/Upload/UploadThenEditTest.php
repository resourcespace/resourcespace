<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

$upload_fixture = new class () {
    public string $refineMetadataTitleSelector;
    public int|false|null $rtf = null;
};

beforeAll(fn () => setTestConfiguration(<<<'PHP'
    $upload_then_edit = true;
PHP));

afterAll(fn () => setTestConfiguration(''));

beforeEach(function () use ($upload_fixture): void {
    $upload_fixture->refineMetadataTitleSelector = "#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']";
});

test('upload page is displayed when files are uploaded before setting metadata', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    (new BrowserPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->assertPathIs('/pages/upload_batch.php');
});

test('completes a new or resumed local TUS upload', function (string $upload): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $browser = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'));
    $page = $browser->getPage();
    $upload_mode = encode_js_value($upload);
    $page->script(<<<JS
        (async () => {
            const mode = {$upload_mode};
            const tus = uppy.getPlugin('Tus');
            const {endpoint, headers, onBeforeRequest} = tus.opts;
            window.tusUploadRequests = {creationKeys: [], resumeUrls: []};
            tus.setOptions({
                onBeforeRequest(request, file) {
                    const result = onBeforeRequest(request, file);
                    if (request.getMethod() === 'HEAD') {
                        window.tusUploadRequests.resumeUrls.push(request.getURL());
                    }
                    if (request.getMethod() === 'POST' && request.getURL() === endpoint) {
                        window.tusUploadRequests.creationKeys.push(request.getHeader('Upload-Key'));
                    }
                    return result;
                },
            });

            if (mode === 'fresh') {
                return;
            }

            // Stage a TUS resource and its original browser record as if a previous
            // page had created it. The queued file has none of that original metadata.
            const [file] = uppy.getFiles();
            const uuid = '01234567-89ab-4def-a123-456789abcdef';
            const encodedName = btoa('image_1') + '.jpg';
            const metadata = {name: encodedName};
            const creationHeaders = {
                ...headers,
                'Tus-Resumable': '1.0.0',
                'Upload-Length': String(file.size),
            };
            if (mode === 'uuid') {
                metadata.uploadTempId = uuid;
                metadata.name = uuid + '-' + encodedName;
                creationHeaders['Upload-Key'] = uuid;
            } else if (mode === 'legacy with key') {
                metadata.key = '2';
                metadata.name = '2-' + encodedName;
            }
            creationHeaders['Upload-Metadata'] = Object.entries(metadata)
                .map(([key, value]) => key + ' ' + btoa(value)).join(',');
            const response = await fetch(endpoint, {method: 'POST', headers: creationHeaders});
            if (response.status !== 201 || !response.headers.get('Location')) {
                throw new Error('Unable to stage TUS upload: ' + response.status);
            }
            const uploadUrl = new URL(response.headers.get('Location'), endpoint).href;
            window.stagedTusUploadUrl = uploadUrl;
            localStorage.setItem('tus::tus-' + file.id + '-' + endpoint + '::fixture', JSON.stringify({
                uploadUrl,
                size: file.size,
                metadata,
                creationTime: new Date().toString(),
            }));
        })()
        JS);

    $browser->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']");
    $requests = $page->script('window.tusUploadRequests');
    if ($upload === 'fresh') {
        expect($requests['resumeUrls'])->toBe([])
            ->and($requests['creationKeys'])->toHaveCount(1)
            ->and($requests['creationKeys'][0])->toMatch(
                '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/'
            );
    } else {
        expect($requests['creationKeys'])->toBe([])
            ->and($requests['resumeUrls'])->toBe([$page->script('window.stagedTusUploadUrl')]);
    }
})->with(['fresh', 'uuid', 'legacy without key', 'legacy with key']);

test('uploaded resources can be added to an existing collection', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $test_id = generateRandomTestID(16);
    $resource_title = "UploadThenEdit - {$test_id}";

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'Default Collection')
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources can be added to a new named collection', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $test_id = generateRandomTestID(16);
    $collection_name = "Collection - {$test_id}";
    $resource_title = "UploadThenEdit - {$test_id}";

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'new')
        ->fill('entercolname', $collection_name)
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSelectedOptions('.collection-bar-actions-section #collection', [$collection_name])
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources are not added to a collection (when intended so)', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->select('collection_add', 'false')
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
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
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    // Upload a file
    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('#UploadOptionsSectionHead')
        ->check('no_exif')
        ->select('collection_add', 'false')
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
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

test('locked metadata values can be applied to all uploaded resources (single field)', function () use ($upload_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->addFileToUppy(testFixture('Videos/short_5seconds.mp4'))
        ->uploadAndWaitFor($upload_fixture->refineMetadataTitleSelector)
        ->assertPathIs('/pages/edit.php')
        ->assertQueryStringHas('upload_review_mode', '1')
        ->fillTitle($resource_title)
        ->refineMetadataLockField($GLOBALS['view_title_field'])
        ->refineMetadataSaveAllWithLockedValues()
        ->waitForAjax()
        ->assertPathIs('/pages/search.php');

    expect($this->expectResourceIdsByNodeName($resource_title))->toHaveCount(3);
});

test('locked metadata values can be applied to all uploaded resources (multiple fields)', function () use ($upload_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->addFileToUppy(testFixture('Videos/short_5seconds.mp4'))
        ->uploadAndWaitFor($upload_fixture->refineMetadataTitleSelector)
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

    $page->assertPathIs('/pages/search.php');
    $resources = $this->expectResourceIdsByNodeName($resource_title);
    expect($resources)->toHaveCount(3);
    foreach ($resources as $ref) {
        expect(get_resource_nodes($ref, 73))->toContain(274);
        expect(get_resource_nodes($ref, 74))->toContain(266);
    }
});

test('missing a single required field triggers a validation error and preserves entered date values', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $date = new DateTimeImmutable('2026-10-09 14:03');
    $date_field_part = prefix_value("field_{$GLOBALS['date_field']}");

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
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

test('missing multiple required fields trigger a validation error and preserve entered date values', function () use ($upload_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $test_id = generateRandomTestID(16);
    $date = new DateTimeImmutable('2026-10-09 14:05');
    $date_field_part = prefix_value("field_{$GLOBALS['date_field']}");

    $upload_fixture->rtf = create_resource_type_field("Test required field - {$test_id}");
    expect($upload_fixture->rtf)->toBeInt();
    expect(
        save_resource_type_field(
            $upload_fixture->rtf,
            get_resource_type_field_columns(),
            array_merge(get_resource_type_field($upload_fixture->rtf), ['required' => 1, 'order_by' => 15]),
        ),
    )->toBeTrue();

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->uploadAndWaitFor("#CentralSpace #mainform input[name='field_{$GLOBALS['view_title_field']}']")
        ->fillDateParts($GLOBALS['date_field'], $date)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->assertPresent(".Question.FieldSaveError:has(> label[for='field_{$GLOBALS['view_title_field']}'])")
        ->assertPresent(".Question.FieldSaveError:has(> label[for='field_{$upload_fixture->rtf}'])")
        ->assertCount('.toastify.toastify-Error:has-text("This is a required field")', 2)
        ->assertValue($date_field_part('-y'), $date->format('Y'))
        ->assertSelected($date_field_part('-m'), $date->format('m'))
        ->assertSelected($date_field_part('-d'), $date->format('d'))
        ->assertSelected($date_field_part('-h'), $date->format('H'))
        ->assertSelected($date_field_part('-i'), $date->format('i'));
})->after(function () use ($upload_fixture): void {
    if (is_int($upload_fixture->rtf ?? null)) {
        delete_resource_type_field($upload_fixture->rtf);
    }
    $upload_fixture->rtf = null;
});

test('PDF and MP3 resources have a "View in browser" download option on their View page', function () use ($upload_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource_title = 'UploadThenEdit - ' . generateRandomTestID(16);

    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->addFileToUppy(testFixture('Audio/simple_short.mp3'))
        ->addFileToUppy(testFixture('Documents/basic.pdf'))
        ->uploadAndWaitFor($upload_fixture->refineMetadataTitleSelector)
        ->assertQueryStringHas('upload_review_mode', '1')
        ->fillTitle($resource_title)
        ->refineMetadataLockField($GLOBALS['view_title_field'])
        ->waitForAjax()
        ->refineMetadataSaveAllWithLockedValues()
        ->waitForAjax();

    $resources = $this->expectResourceIdsByNodeName($resource_title);
    expect($resources)->toHaveCount(2);
    $extensions = [];
    foreach ($resources as $resource) {
        $extensions[] = get_resource_data($resource, false)['file_extension'];
        $page->openAndWaitFor(
            $this->browserUrl("/pages/view.php?ref={$resource}"),
            '.DownloadButton a:text-is("View in browser")'
        )->assertSee($resource_title);
    }
    expect($extensions)->toEqualCanonicalizing(['pdf', 'mp3']);
});

test('text from an uploaded PDF is stored in the configured extracted text field', function () use ($upload_fixture): void {
    /** @var ResourceSpaceBrowserTestCase $this */
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
        ->uploadAndWaitFor($upload_fixture->refineMetadataTitleSelector)
        ->assertQueryStringHas('upload_review_mode', '1');

    $resources = get_collection_resources($review_collection);
    expect($resources)->toHaveCount(1);

    $extracted_text = get_data_by_field($resources[0], $text_field);
    expect(preg_replace('/\s+/u', ' ', trim($extracted_text)))
        ->toBe('ResourceSpace PDF text extraction regression fixture. The uploaded document contains searchable text.');
});

test('queues uploads from the file input and from a drop onto the dashboard', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = $this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value);
    $page->assertPathIs('/pages/upload_batch.php');
    $dashboard = '.uppy-Dashboard-inner';
    $page->assertVisible($dashboard . ' .uppy-Dashboard-browse');
    $page->page()->locator($dashboard . ' input[type=file]')->first()->setInputFiles(
        testFixture('Images/image_1.jpg')
    );
    $page->assertVisible($dashboard . ' .uppy-Dashboard-Item-name:has-text("image_1.jpg")');

    $drop_url = encode_js_value($this->browserUrl('/tests/Fixtures/Files/Images/simple_with_embedded_metadata.jpg'));
    $page->script(<<<JS
        (async () => {
            const response = await fetch({$drop_url});
            const bytes = await response.blob();
            const file = new File([bytes], 'dropped-metadata.jpg', {type: 'image/jpeg'});
            const transfer = new DataTransfer();
            transfer.items.add(file);
            const target = document.querySelector('{$dashboard}');
            target.dispatchEvent(new DragEvent('dragenter', {dataTransfer: transfer, bubbles: true, cancelable: true}));
            target.dispatchEvent(new DragEvent('dragover', {dataTransfer: transfer, bubbles: true, cancelable: true}));
            target.dispatchEvent(new DragEvent('drop', {dataTransfer: transfer, bubbles: true, cancelable: true}));
        })()
        JS);
    $page->assertVisible($dashboard . ' .uppy-Dashboard-Item-name:has-text("dropped-metadata.jpg")');
    expect($page->page()->locator($dashboard . ' .uppy-Dashboard-Item')->count())->toBe(2);
});

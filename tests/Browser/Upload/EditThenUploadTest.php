<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;

beforeAll(fn() => setTestConfiguration(<<<'PHP'
    $upload_then_edit = false;
PHP));

afterAll(fn() => setTestConfiguration(''));

beforeEach(fn() => clear_resource_data(-1));

test('users can set metadata before uploading', function (): void {
    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->assertPathIs('/pages/edit.php')
        ->assertQueryStringHas('ref', '-1')
        ->assertQueryStringHas('collection_add', '');
});

test('changing the resource type displays the associated metadata fields', function (): void {
    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->selectResourceType('Video')
        ->assertSee('Video size')
        ->assertDontSee('Keywords - Landmark');
});

test('users can copy metadata from an existing resource when uploading', function (): void {
    $resource = create_resource(1, 0, 1);
    expect($resource)->toBeInt();

    $title = 'Test copy data from this resource';
    bypass_permissions(
        ['e0', 'ert1'],
        update_field(...),
        [$resource, $GLOBALS['view_title_field'], $title],
    );
    update_field($resource, 3, '181', nodevalues: true);
    update_field($resource, 73, '274', nodevalues: true);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fill('copyfrom', (string) $resource)
        ->click('Copy')
        ->waitForAjax()
        ->assertValue("input[name=field_{$GLOBALS['view_title_field']}]", $title)
        ->assertPresent('#nodeselected181')
        ->assertChecked('#nodes_274');
});

test('users cannot proceed when a required field is left blank', function (): void {
    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->click('Next')
        ->waitForAjax()
        ->assertSee('This is a required field');
});

test('users cannot proceed when at least one required field is left blank', function (): void {
    $rtf = create_resource_type_field('Test required field');
    expect($rtf)->toBeInt();

    expect(
        save_resource_type_field(
            $rtf,
            get_resource_type_field_columns(),
            array_merge(get_resource_type_field($rtf), ['required' => 1, 'order_by' => 15]),
        ),
    )->toBeTrue();

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fill("field_{$rtf}", 'Lorem ipsum')
        ->click('Next')
        ->waitForAjax()
        ->assertSee('This is a required field');

    // Quick cleanup or the extra required field will make the next tests break
    delete_resource_type_field($rtf);
});

test('uploaded resources can be added to an existing collection', function (): void {
    $resource_title = 'EditThenUpload - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->select('collection_add', 'Default Collection')
        ->click('Next')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources can be added to a new named collection', function (): void {
    $test_id = generateRandomTestID(16);
    $collection_name = "Collection - {$test_id}";
    $resource_title = "EditThenUpload - {$test_id}";

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->selectResourceType('Photo')
        ->fillTitle($resource_title)
        ->select('collection_add', 'new')
        ->fill('entercolname', $collection_name)
        ->click('Next')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->click(UiElementSelector::CollectionBarToggleButton->value)
        ->click(UiElementSelector::CollectionBarShowThumbsButton->value)
        ->assertSelectedOptions('.collection-bar-actions-section #collection', [$collection_name])
        ->assertSeeIn('.collection-bar-resource-card-title', $resource_title);
});

test('uploaded resources are not added to a collection (when intended so)', function (): void {
    $resource_title = 'EditThenUpload - ' . generateRandomTestID(16);

    (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->select('collection_add', 'false')
        ->click('Next')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
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
    $resource_title = 'EditThenUpload - ' . generateRandomTestID(16);

    // Upload a file
    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->check('no_exif')
        ->select('collection_add', 'false')
        ->click('Next')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/simple_with_embedded_metadata.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
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

test('resource log records creation from the Web Uploader', function (): void {
    $resource_title = 'EditThenUpload - ' . generateRandomTestID(16);

    // Upload a file
    $page = (new EditPage($this->loginAsAdmin()->click(UiElementSelector::HeaderUploadButton->value)))
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->select('collection_add', 'false')
        ->click('Next')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax();
    
    $ref = $this->expectResourceIdByNodeName($resource_title);

    // Check resource logs
    $page
        ->navigate($this->browserUrl("/pages/view.php?ref={$ref}"))
        ->waitForAjax()
        ->assertSee($resource_title)
        ->click('Log')
        ->waitForAjax()
        ->assertSee('Created from Web Uploader');
});

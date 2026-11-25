<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'valid_upload_paths', 'offline_job_queue', 'upload_then_process', 'enable_thumbnail_creation_on_upload',
        '_GET', '_POST', '_REQUEST', '_FILES', 'image_alternatives',
    ]);
    $_GET = $_POST = $_REQUEST = $_FILES = [];
    $GLOBALS['image_alternatives'] = [];
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
    $GLOBALS['offline_job_queue'] = false;
    $GLOBALS['upload_then_process'] = false;
    $GLOBALS['enable_thumbnail_creation_on_upload'] = true;
    setTestConfiguration(<<<'PHP'
        $edit_autosave = false;
        $offline_job_queue = false;
        $upload_then_process = false;
        $resource_deletion_state = 3;
        $resource_view_use_pre = true;
        PHP);
});
afterEach(function () use (&$fixtures): void {
    setTestConfiguration('');
    $fixtures?->cleanUp();
});

test('an edit to an existing resource is still present after reopening it', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource = $fixtures->resource(1, 0);
    $title = 'Edited ' . generateRandomTestID(12);
    $url = generateURL($this->browserUrl('/pages/edit.php'), ['ref' => $resource]);
    $title_field = (int) $GLOBALS['view_title_field'];
    $selector = "[name=field_{$title_field}]";
    $page = $this->loginAsAdmin();
    (new BrowserPage($page))->openAndWaitFor($url, $selector);

    $edit = new EditPage($page);
    $edit->fillTitle($title)->refineMetadataSaveAndNext()->waitForAjax();
    expect(get_data_by_field($resource, $title_field, true))->toBe($title);

    (new BrowserPage($page))->openAndWaitFor($url, $selector);
    $page->assertValue($selector, $title);
});

test('tabbing out of single and multiline metadata saves both changes and displays them on View', function () use (
    &$fixtures,
): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration('$edit_autosave = true;');
    $resource = $fixtures->resource(1, 0);
    $field = $fixtures->field('Edit notes', 1, FIELD_TYPE_TEXT_BOX_MULTI_LINE);
    $title_field = (int) $GLOBALS['view_title_field'];
    $title = 'Auto-saved ' . generateRandomTestID(10);
    $notes = "Catalogue first line\nCatalogue second line";
    $inputs = [$title_field => ['input', $title], $field => ['textarea', $notes]];
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $edit = generateURL($this->browserUrl('/pages/edit.php'), ['ref' => $resource]);
    $browser->openAndWaitFor($edit, "input[name=field_{$title_field}]");

    foreach ($inputs as $ref => [$tag, $value]) {
        $control = $page->page()->locator("{$tag}[name=field_{$ref}]");
        $control->fill($value);
        $control->press('Tab');
        $page->assertScript("document.getElementById('AutoSaveStatus{$ref}')?.textContent", $GLOBALS['lang']['saved']);
        $stored = get_data_by_field($resource, $ref, true);
        expect(str_replace("\r\n", "\n", $stored))->toBe($value);
    }

    $view = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $resource]);
    $browser->openAndWaitFor($view, '#CentralSpace .RecordHeader');
    $page->assertSee($title)->assertSee('Catalogue first line')->assertSee('Catalogue second line');
    $log = get_resource_log($resource, 50, ['r.type' => LOG_CODE_EDITED]);
    expect(array_column($log['data'], 'diff'))->toContain('+ ' . $title);
    $notes_log = array_values(array_filter($log['data'], static fn (array $row): bool => $row['title'] === 'Edit notes'));
    expect($notes_log)->toHaveCount(1);
    expect($notes_log[0]['diff'])->toContain('Catalogue first line', 'Catalogue second line');
});

test('autosaves date parts and rejects an impossible date before switching to the native date input', function () use (
    &$fixtures,
): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration(<<<'CONFIG'
        $edit_autosave = true;
        $use_native_input_for_date_field = false;
        CONFIG);
    $resource = $fixtures->resource(1, 0);
    $date = $fixtures->field('Catalogue date', 1, FIELD_TYPE_DATE);
    $date_time = $fixtures->field('Catalogue time', 1, FIELD_TYPE_DATE_AND_OPTIONAL_TIME);
    $url = generateURL($this->browserUrl('/pages/edit.php'), ['ref' => $resource]);
    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $browser->openAndWaitFor($url, "#field_{$date}-y");

    $save_parts = static function (int $field, array $parts) use ($page, $browser, $resource): void {
        foreach ($parts as $part => [$value, $stored]) {
            $input = $page->page()->locator("#field_{$field}-{$part}");
            if ($part === 'y') {
                $input->fill($value);
                $input->press('Tab');
            } else {
                $input->selectOption($value);
            }
            $browser->waitForAjax();
            expect(get_data_by_field($resource, $field))->toBe($stored);
        }
    };
    $save_parts($date, [
        'y' => ['2024', '2024-00-00'], 'm' => ['06', '2024-06-00'], 'd' => ['15', '2024-06-15'],
    ]);
    $save_parts($date_time, [
        'y' => ['2024', '2024-00-00'], 'm' => ['02', '2024-02-00'], 'd' => ['29', '2024-02-29'],
        'h' => ['14', '2024-02-29 14:00'], 'i' => ['05', '2024-02-29 14:05'],
    ]);

    $page->page()->locator("#field_{$date_time}-d")->selectOption('30');
    $alert = '.ui-dialog:visible';
    $page->assertSeeIn($alert, $GLOBALS['lang']['invalid_date_generic']);
    expect(get_data_by_field($resource, $date_time))->toBe('2024-02-29 14:05');

    setTestConfiguration('$use_native_input_for_date_field = true;');
    $native = "input#field_{$date}[type=date]";
    $browser->openAndWaitFor($url, $native);
    $page->assertValue($native, '2024-06-15')->assertValue("#field_{$date_time}-d", '29');
    $page->page()->locator($native)->fill('2024-06-17');
    $browser->waitForAjax();
    expect(get_data_by_field($resource, $date))->toBe('2024-06-17');
});

test('bulk editing changes the selected resources and leaves a control resource intact', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $user = (int) $GLOBALS['userref'];
    $collection = $fixtures->collection($user, 'Bulk edit ' . generateRandomTestID(12));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    $title_field = (int) $GLOBALS['view_title_field'];
    $medium = $fixtures->field('Batch media ' . generateRandomTestID(12), 1, FIELD_TYPE_CHECK_BOX_LIST);
    $nodes = [];
    for ($index = 1; $index <= 6; $index++) {
        $nodes[] = set_node(null, $medium, 'Medium ' . $index, null, $index);
    }
    add_resource_nodes($first, $nodes);
    add_resource_nodes($second, [$nodes[0]]);
    $title = 'Bulk title ' . generateRandomTestID(12);
    $control_title = get_data_by_field($control, $title_field, true);
    expect(add_resource_to_collection($first, $collection))->toBeTrue()
        ->and(add_resource_to_collection($second, $collection))->toBeTrue();

    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    $search_url = $this->browserUrl('/pages/search.php');
    $open_bulk_edit = static function (array $parameters, string $action) use ($browser, $page, $search_url, $first): void {
        $browser->openAndWaitFor(generateURL($search_url, $parameters), "#CentralSpace #ResourceShell{$first}")
            ->waitForAjax();
        $page->page()->locator("#CentralSpace select:has(option[value={$action}])")->selectOption($action);
        $page->page()->locator('#editmultipleresources')->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    };
    $open_bulk_edit(['search' => "!list{$first}:{$second}"], 'editsearchresults');
    $page->assertSeeIn('#mainform .BasicsBoxLeft', str_replace('%number', '2', $GLOBALS['lang']['resources_selected-2']));
    $open_bulk_edit(['collection' => $collection, 'search' => '!collection' . $collection], 'edit_all_in_collection');

    $medium_checkbox = "input[name=editthis_field_{$medium}]";
    $existing = "#mainform [data-rtfid='$medium'][id^=displayexisting_]";
    $page->assertNotPresent("#mainform [data-rtfid='$title_field'][id^=displayexisting_]");
    $page->check($medium_checkbox);
    $more = $existing . ' input[id^=displayexisting_options_more_]';
    $page->page()->locator($more)->waitFor(['state' => 'visible']);
    $visible_values = $existing . ' .currenteditmulti:visible';
    expect($page->page()->locator($visible_values)->count())->toBe(5);
    $page->assertSeeIn($existing, 'Medium 1 (2)');
    $page->page()->locator($more)->click();
    expect($page->page()->locator($visible_values)->count())->toBe(6);
    $page->assertMissing($more);
    $page->uncheck($medium_checkbox);
    $page->check("[name=editthis_field_{$title_field}]")->script('window.confirm = () => true');
    (new EditPage($page))->fillTitle($title)->refineMetadataSaveAndNext()->waitForAjax();

    expect(get_data_by_field($first, $title_field, true))->toBe($title)
        ->and(get_data_by_field($second, $title_field, true))->toBe($title)
        ->and(get_data_by_field($control, $title_field, true))->toBe($control_title);
});

test('shows expiry and metadata warnings before revealing the download tools', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    setTestConfiguration('$show_expiry_warning = true;');
    $expiry = $fixtures->field('Review expiry', 2, FIELD_TYPE_EXPIRY_DATE);
    $warning = $fixtures->field('Review warning', 2, FIELD_TYPE_WARNING_MESSAGE);
    $warning_text = 'Confirm the catalogue clearance before reuse';
    $resources = [];
    $errors = [];
    foreach ([$expiry => '2000-01-01', $warning => $warning_text] as $field => $value) {
        $ref = $fixtures->resource(2, 0, file_extension: 'txt');
        expect(file_put_contents(get_resource_path($ref, true, '', true, 'txt'), 'Catalogue note'))->toBe(14);
        expect(update_field($ref, $field, $value, $errors))->toBeTrue();
        $resources[$field] = $ref;
    }

    $page = $this->loginAsAdmin();
    $browser = new BrowserPage($page);
    foreach ([$expiry => ['#WarningOK', $GLOBALS['lang']['warningexpiredtext']],
        $warning => ['#WarningOK_' . $warning, $warning_text]] as $field => [$control, $text]) {
        $view = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $resources[$field]]);
        $browser->openAndWaitFor($view, $control . ' a');
        $page->assertSeeIn('.RecordStory', $text)->assertMissing('#RecordDownloadTabContainer');
        $page->page()->locator($control . ' a')->click(['noWaitAfter' => true]);
        $page->assertMissing($control)->assertVisible('#RecordDownloadTabContainer');
        $page->assertVisible('#ResourceDownloadOptions #downloadlink');
    }
});

test('replacing an original regenerates its preview', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource = $fixtures->resource(1, 0);
    expect(upload_file($resource, no_exif: true, file_path: testFixture('Images/image_1.jpg'), deletesource: false))
        ->toBeTrue();
    $original = get_resource_path($resource, true, '', false, 'jpg');
    $preview = get_resource_path($resource, true, 'pre', false, 'jpg');
    expect($preview)->toBeFile();
    $previous_preview = hash_file('sha256', $preview);
    $replacement = testFixture('Images/simple_with_embedded_metadata.jpg');

    $page = $this->loginAsAdmin();
    $url = generateURL($this->browserUrl('/pages/upload_batch.php'), [
        'ref' => $resource,
        'replace_resource' => $resource,
        'redirecturl' => generateURL($this->browserUrl('/pages/view.php'), ['ref' => $resource]),
    ]);
    (new BrowserPage($page))->openAndWaitFor($url, '.uppy-Dashboard');
    (new BrowserPage($page))->addFileToUppy($replacement)->uploadAndWaitFor('#ResourceDownloadOptions');

    clearstatcache();
    expect(hash_file('sha256', $original))->toBe(hash_file('sha256', $replacement))
        ->and($preview)->toBeFile()
        ->and(hash_file('sha256', $preview))->not->toBe($previous_preview);
});

test('a deleted resource can be restored through its workflow status', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $resource = $fixtures->resource(1, 0);
    expect(upload_file($resource, no_exif: true, file_path: testFixture('Images/image_1.jpg'), deletesource: false))
        ->toBeTrue();
    $page = $this->loginAsAdmin();
    $view = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $resource]);
    $delete = 'a[onclick*="delete_resource"]';
    (new BrowserPage($page))->openAndWaitFor($view, $delete);
    $page->script('window.confirm = () => true');
    (new BrowserPage($page))->click($delete)->waitForAjax();
    expect((int) get_resource_data($resource, false)['archive'])->toBe(3);

    $edit = generateURL($this->browserUrl('/pages/edit.php'), ['ref' => $resource, 'archive' => 3]);
    (new BrowserPage($page))->openAndWaitFor($edit, '#status');
    $page->select('#status', '0');
    (new EditPage($page))->fillTitle('Restored ' . generateRandomTestID(16))
        ->refineMetadataSaveAndNext()
        ->waitForAjax();
    expect((int) get_resource_data($resource, false)['archive'])->toBe(0);

    (new BrowserPage($page))->openAndWaitFor($view, '#ResourceDownloadOptions');
    $page->assertPathIs('/pages/view.php');
});

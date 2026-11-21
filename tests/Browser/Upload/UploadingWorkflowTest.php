<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\Browser\EditPage;
use Montala\ResourceSpace\Tests\Support\Browser\UiElementSelector;

beforeAll(function () {
    setTestConfiguration('$upload_then_edit = true;');

    $GLOBALS['shared_test_id'] = generateRandomTestID(16);
});

afterAll(function () {
    setTestConfiguration('');

    unset($GLOBALS['shared_test_id']);
});

test('general users can upload resources via Contributions and submit them for review', function (): void {
    $test_id = $GLOBALS['shared_test_id'];
    $username = "test-{$test_id}";
    $gen_user = new_user($username, 2);

    expect($gen_user)->toBeInt();
    expect(
        bypass_permissions(['u'], save_user(...), [$gen_user, ['fullname' => "Test {$test_id}", 'password' => $test_id]]
    ))->toBeTrue();

    $resource_title = "UploadWorkflow - {$test_id}";

    (new EditPage($this->loginAs($username, $test_id)))
        ->click('Contributions')
        ->click('Upload resources')
        ->waitForAjax()
        ->addFileToUppy(testFixture('Images/image_1.jpg'))
        ->click(UiElementSelector::UppyActionUploadButton->value)
        ->waitForAjax()
        ->fillTitle($resource_title)
        ->refineMetadataSaveAndNext()
        ->waitForAjax()
        ->assertSeeIn('.ui-dialog-title', 'Submit for review')
        ->click('.ui-dialog[aria-describedby="modal_dialog"] .ui-dialog-buttonpane button:text-is("Submit for review")')
        ->waitForAjax()
        ->assertPathIs('/pages/search.php')
        ->assertPresent(
            ".resource-card:has(.resource-card-title[title='{$resource_title}'])"
            . ":has(.resource-card-status.pending span:text-is('Pending review'))",
        );
});

test('admins can review pending resources and change their status using the Edit page and workflow actions', function (): void {
    $test_id = $GLOBALS['shared_test_id'];
    $resource_title = "UploadWorkflow - {$test_id}";
    $resource = $this->expectResourceIdByNodeName($resource_title);
    $edit_action_selector = "#CentralSpace_resource_actions tr:has(td:nth-child(3) a[href*='ref={$resource}&']) a[title='Edit']";
    $archive_workflow_selector = 'input[type="submit"][name^="rse_workflow_action_submit_"][value*="Archive"]';

    (new EditPage($this->loginAsAdmin()))
        ->click('a.get-menu-content[title="My account"]')
        ->click('#user_actions_link')
        ->waitForAjax()
        ->assertPresent($edit_action_selector)
        ->click($edit_action_selector)
        ->waitForAjax()
        ->select('status', 'Active')
        ->waitForAjax()
        // Now use workflow (action) buttons on the resource View page
        ->navigate($this->browserUrl("/pages/view.php?ref={$resource}"))
        ->waitForAjax()
        ->assertNotPresent('.RecordHeader span.ResourcePendingReviewTitle')
        ->click('#ResourceWorkflowActionsButton')
        ->assertPresent($archive_workflow_selector)
        ->click($archive_workflow_selector)
        ->waitForAjax()
        ->assertPresent('.RecordHeader span.ArchiveResourceTitle');
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;

test('user can log in', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $this->loginAsAdmin();
});

test('ensure required fields and anti-spam input need to be filled on "Apply for an account"', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = visit($this->browserUrl('/login.php'))
        // Login has the "Apply for an account" shown
        ->assertVisible('#account_apply')
        ->click('#account_apply')
        ->assertPathIs('/pages/user_request.php')
        // Required fields must be input on the user_request page
        ->click('#user_submit')
        ->assertPresent('.error-panel');

    $page
        ->fill('name', 'John Doe')
        ->fill('email', 'john.doe@example.montala.com')
        ->fill('antispam', $page->attribute('input[name="antispam_user_code"]', 'data-test-antispam-code'))
        ->click('label[for="login_opt_in"]')
        ->click('#user_submit')
        ->assertNotPresent('.error-panel')
        ->assertPathIs('/pages/done.php');
});

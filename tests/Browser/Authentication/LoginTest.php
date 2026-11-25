<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Pest\Browser\Playwright\Playwright;

test('user can log in', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $this->loginAsAdmin();
});

test('ensure required fields and anti-spam input need to be filled on "Apply for an account"', function (): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $page = visit($this->browserUrl('/login.php'));
    $browser = Playwright::usingTimeout(15_000, function () use ($page) {
        $browser = $page->page();
        $browser->locator('#account_apply')->click(['noWaitAfter' => true]);
        $browser->locator('#user_submit')->waitFor(['state' => 'visible']);

        return $browser;
    });
    $page->assertScript('typeof submitForm === "function" && typeof CentralSpacePost === "function"');
    $page->assertPathIs('/pages/user_request.php');

    $submit = $browser->locator('#user_submit');
    $error = $browser->locator('.error-panel');
    $submit->click(['noWaitAfter' => true]);
    $error->waitFor(['state' => 'visible', 'timeout' => 15_000]);

    $email = 'application-' . generateRandomTestID(16) . '@example.test';
    $browser->locator('#name')->fill('Test account applicant');
    $browser->locator('#email')->fill($email);
    $browser->locator('#antispam')->fill('invalid');
    $consent = $browser->locator('label[for="login_opt_in"]');
    $consent->click();
    $submit->click(['noWaitAfter' => true]);
    $page->assertScript(<<<'JS'
        !!document.querySelector(".error-panel") && document.querySelector("#user_submit")?.disabled === false
        JS);
    $page->assertSeeIn('.error-panel', $GLOBALS['lang']['requiredantispam']);

    $challenge = $browser->locator('[data-test-antispam-code]')->getAttribute('data-test-antispam-code');
    expect($challenge)->toBeString()->not->toBeEmpty();
    $browser->locator('#antispam')->fill($challenge);
    $consent->click();
    $submit->click(['noWaitAfter' => true]);
    $page->assertPathIs('/pages/done.php');
});

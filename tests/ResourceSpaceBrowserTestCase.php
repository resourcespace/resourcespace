<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Playwright;
use PHPUnit\Framework\TestCase;

abstract class ResourceSpaceBrowserTestCase extends TestCase
{
    public function browserUrl(string $path): string
    {
        return 'http://127.0.0.1:8081/' . ltrim($path, '/');
    }

    /**
     * Log in to ResourceSpace through the browser using the supplied credentials.
     *
     * Configures the browser to use the Europe/London timezone and English locale, submits the login form, and verifies
     * that authentication succeeded by checking the redirect to the home page, absence of an error panel and presence
     * of the ResourceSpace text.
     *
     * @param string $username Account username/email to log in as
     * @param string $password Account password
     * @param PendingAwaitablePage|null $login_page Login page from visit(), when called in the test body
     *
     * @return AwaitableWebpage Authenticated browser page for further interactions
     */
    public function loginAs(
        string $username,
        string $password,
        ?PendingAwaitablePage $login_page = null
    ): AwaitableWebpage {
        expect($username)->not()->toBeEmpty();
        expect($password)->not()->toBeEmpty();

        $page = ($login_page ?? visit($this->browserUrl('/login.php')))
            ->withTimezone('Europe/London')
            ->withLocale('en');

        // Issue the click once and wait for the login outcome separately from page assets.
        Playwright::usingTimeout(15_000, function () use ($page, $username, $password): void {
            $browser = $page->page();
            $browser->locator('#loginform input[name="username"]')->fill($username);
            $browser->locator('#loginform input[name="password"]')->fill($password);
            $browser->locator('#loginform input[type="submit"]')->click(['noWaitAfter' => true]);
            $browser->locator('#HomePanelContainer, #login-errors')->waitFor(['state' => 'visible']);
        });

        return $page->assertPathIs('/pages/home.php')
            ->assertNotPresent('.error-panel-details')
            ->assertSee('ResourceSpace');
    }

    /**
     * Log in as the provisioned test administrator.
     *
     * @param PendingAwaitablePage|null $login_page Login page returned by visit() in the test
     * @return AwaitableWebpage Authenticated browser page
     */
    public function loginAsAdmin(?PendingAwaitablePage $login_page = null): AwaitableWebpage
    {
        return $this->loginAs('admin', 'admin123', $login_page);
    }

    public function expectResourceIdByNodeName(string $name): int
    {
        $id = (int) ps_value(
            'SELECT r.ref AS `value`
               FROM `resource` r
               JOIN resource_node rn ON rn.resource = r.ref
               JOIN node n ON n.ref = rn.node
              WHERE n.name = ?
                AND r.ref > 0',
            ['s', $name],
            0,
        );

        expect($id)->toBeGreaterThan(0);

        return $id;
    }

    public function expectResourceIdsByNodeName(string $name): array
    {
        $ids = ps_array(
            'SELECT r.ref AS `value`
               FROM `resource` r
               JOIN resource_node rn ON rn.resource = r.ref
               JOIN node n ON n.ref = rn.node
              WHERE n.name = ?
                AND r.ref > 0',
            ['s', $name],
        );

        expect($ids)->not()->toBeEmpty();

        return $ids;
    }
}

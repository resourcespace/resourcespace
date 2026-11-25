<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\Browser\BrowserPage;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

test('a password reset link replaces the old sign-in password', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $username = 'reset' . generateRandomTestID(16);
    $old_password = 'Before!' . generateRandomTestID(12);
    $new_password = 'After!' . generateRandomTestID(12);
    $user = $fixtures->user($username);
    expect(save_user($user, [
        'fullname' => 'Password reset test user',
        'password' => $old_password,
        'approved' => 1,
    ]))->toBeTrue();

    $this->loginAs($username, $old_password);
    $reset_url = generateURL($this->browserUrl('/pages/user/user_change_password.php'), [
        'rp' => $user . create_password_reset_key($username),
    ]);
    $reset = visit($reset_url);
    $reset->fill('#password', $new_password)->fill('#password2', $new_password);
    $reset->page()->locator('form:has(#resetkey) input[name=save]')->click(['noWaitAfter' => true]);
    $reset->page()->locator('#password2')->waitFor(['state' => 'detached']);

    $failed_login = visit($this->browserUrl('/login.php'));
    $failed_login->fill('#loginform [name=username]', $username)->fill('#loginform [name=password]', $old_password);
    $failed_login->page()->locator('#loginform input[type=submit]')->click(['noWaitAfter' => true]);
    $failed_login->page()->locator('#login-errors')->waitFor(['state' => 'visible', 'timeout' => 15_000]);

    $this->loginAs($username, $new_password);
});

test('changing group permissions hides the denied resource type and metadata field', function () use (&$fixtures): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    $suffix = generateRandomTestID(16);
    $group = $fixtures->group('Visibility ' . $suffix, 's,g,f*');
    $username = 'reader' . $suffix;
    $password = 'Reader!' . $suffix;
    $user = $fixtures->user($username, $group);
    expect(save_user($user, ['fullname' => 'Visibility test user', 'password' => $password, 'approved' => 1]))
        ->toBeTrue();

    $visible = $fixtures->resource(1, 0);
    $hidden = $fixtures->resource(2, 0);
    $field = $fixtures->field('Group visibility test field', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $secret = 'Private value ' . $suffix;
    $errors = [];
    expect(update_field($visible, $field, $secret, $errors))->toBeTrue();

    $visible_url = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $visible]);
    $hidden_url = generateURL($this->browserUrl('/pages/view.php'), ['ref' => $hidden]);
    $reader = $this->loginAs($username, $password);
    $browser = new BrowserPage($reader);
    $browser->openAndWaitFor($visible_url, '#CentralSpace');
    $reader->assertSee($secret);
    $browser->openAndWaitFor($hidden_url, '#CentralSpace');
    expect($reader->page()->url())->toContain('ref=' . $hidden);
    $reader->assertVisible('#ResourceDownloadOptions');

    $admin = $this->loginAsAdmin();
    $admin_browser = new BrowserPage($admin);
    $group_url = generateURL($this->browserUrl('/pages/admin/admin_group_management_edit.php'), ['ref' => $group]);
    $permissions_input = '#mainform textarea[name=permissions]';
    $permissions = "s,g,f*,T2,f-{$field}";
    $admin_browser->openAndWaitFor($group_url, $permissions_input);
    $admin->fill($permissions_input, $permissions);
    $admin->page()->locator('#mainform input[name=buttonsave]')->click(['noWaitAfter' => true]);
    $admin->page()->locator($permissions_input)->waitFor(['state' => 'detached']);
    clear_query_cache('usergroup');
    expect(get_usergroup($group)['permissions'])->toBe($permissions);

    $reader = $this->loginAs($username, $password);
    $browser = new BrowserPage($reader);
    $browser->openAndWaitFor($visible_url, '#ResourceDownloadOptions');
    $reader->assertDontSee($secret);
    $reader->page()->goto($hidden_url, ['waitUntil' => 'domcontentloaded']);
    $reader->assertNotPresent('#ResourceDownloadOptions');
});

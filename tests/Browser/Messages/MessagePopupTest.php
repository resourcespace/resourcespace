<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

$message = null;
afterEach(function () use (&$message): void {
    if ($message !== null) {
        message_remove($message);
        $message = null;
    }
});

test('the header message popup opens the original message and marks it read', function () use (&$message): void {
    /** @var ResourceSpaceBrowserTestCase $this */
    ResourceSpaceTestEnvironment::setUpDefaultTestUser();
    $admin = (int) $GLOBALS['userref'];
    $text = 'Popup message ' . generateRandomTestID(16);
    message_add($admin, $text, $this->browserUrl('/pages/home.php'), $admin, MESSAGE_ENUM_NOTIFICATION_TYPE_USER_MESSAGE);
    $message = (int) ps_value('SELECT ref AS value FROM message WHERE message = ?', ['s', $text], 0);
    expect($message)->toBeGreaterThan(0);

    $page = $this->loginAsAdmin();
    $browser = $page->page();
    $browser->locator('#header-container a[href*="/pages/user/user_messages_quick.php"]')
        ->click(['noWaitAfter' => true]);
    $item = "#message-list #message{$message}";
    $browser->locator($item)->waitFor(['state' => 'visible', 'timeout' => 15_000]);
    $page->assertSeeIn($item, $text)
        ->assertPresent('#messages-list h1 a[href$="/pages/user/user_messages.php"]')
        ->assertVisible('#message-list #viewall-link');

    $browser->locator($item)->click(['noWaitAfter' => true]);
    $browser->locator('#message-detail .message-full-text')->waitFor(['state' => 'visible']);
    $page->assertSeeIn('#message-detail .message-full-text', $text)
        ->assertVisible('#message-detail .message_link')
        ->assertVisible("#reply-link[href$='/pages/user/user_message.php?msgto={$admin}']");

    $browser->locator('#message-detail h2 a')->click(['noWaitAfter' => true]);
    $browser->locator($item)->waitFor(['state' => 'visible']);
    expect((int) ps_value(
        'SELECT seen AS value FROM user_message WHERE message = ? AND user = ?',
        ['i', $message, 'i', $admin],
        0
    ))->toBe(1);
});

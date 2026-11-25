<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('shows the cookie notice at sign-in and requires consent before opening the application when configured', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $plugin = 'cookies_notification';
        $fixtures->plugin($plugin);
        $user = $fixtures->user('cookienotice' . generateRandomTestID(12), 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
        $settings = 'cookies_notification_allow_using_site_on_no_feedback';
        set_plugin_config($plugin, [$settings => true]);
        $login = $server->request('/login.php', headers: $server->sessionHeaders(''));
        expect($login['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($login['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $notice = '//*[@id="UICenter"]/div[@id="CookiesUseWrapper"]';
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(' . $notice . '/following-sibling::*//*[@id="loginform"])'))->toBe(1.0);
        expect($xpath->evaluate('count(' . $notice . '//input[@type="button"])'))->toBe(2.0);

        $headers = $server->sessionHeaders($session);
        $home_path = '/pages/home.php';
        $optional = $server->request($home_path, headers: $headers);
        expect($optional['status'])->toBe(200);
        $document->loadHTML($optional['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(' . $notice . '/following-sibling::div[@id="CentralSpaceContainer"])'))->toBe(1.0);

        set_plugin_config($plugin, [$settings => false]);
        $required = $server->request($home_path, headers: $headers);
        expect($required['status'])->toBe(302);
        $location = parse_url($required['headers']['location']);
        parse_str($location['query'], $query);
        expect($location['path'])->toBe('/login.php')
            ->and($query)->toMatchArray(['logout' => 'true', 'require_option' => 'true']);

        $consent_headers = array_map(
            static fn (string $header): string => str_starts_with($header, 'Cookie: ')
                ? $header . '; accepted_cookies_use=1' : $header,
            $headers
        );
        $accepted = $server->request($home_path, headers: $consent_headers);
        expect($accepted['status'])->toBe(200)->and($accepted['body'])->toContain('id="CentralSpaceContainer"');
        expect($accepted['body'])->not->toContain('id="CookiesUseWrapper"');
    } finally {
        $fixtures->cleanUp();
    }
});

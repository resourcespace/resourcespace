<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['language', 'lang', 'pagename', 'load_site_text_for_usergroup']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('saves linked welcome content and renders the full, shortened and hidden home variants', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $group = $fixtures->group('Welcome content ' . $suffix, 'a,o,s,g,f*');
    $user = $fixtures->user('welcome' . $suffix, $group);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $user]);
    $fixtures->onCleanup(static function () use ($group): void {
        ps_query('DELETE FROM site_text WHERE specific_to_group = ?', ['i', $group]);
        clear_query_cache('sitetext');
    });

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url,
        'session_autologout' => false,
        'no_welcometext' => false,
        'welcometext_trim' => 500,
    ]);
    $headers = $server->sessionHeaders($session);
    $heading = 'Welcome fixture ' . $suffix;
    $opening = 'Welcome to the collection catalogue.';
    $closing = 'Ask the team for further information.';
    $help = $server->url . '/pages/help.php';
    $welcome = $opening . ' <a href="' . $help . '">Browse information</a> ' . $closing;
    foreach (['welcometitle' => $heading, 'welcometext' => $welcome] as $key => $content) {
        $edit = '/pages/admin/admin_content_edit.php?' . http_build_query([
            'page' => 'home', 'name' => $key, 'editlanguage' => 'en', 'editgroup' => $group,
        ]);
        $form = $server->request($edit, headers: $headers);
        expect($form['status'])->toBe(200);
        $fields = LocalTestServer::hiddenFields($form['body'], '//form[@id="mainform"]');
        expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $saved = $server->request($edit, $fields + ['save' => 'yes', 'text' => $content], $headers);
        expect($saved['status'])->toBe(200);
    }

    $home = $server->request('/pages/home.php', headers: $headers);
    expect($home['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($home['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $panel = '//*[@id="HomeSiteTextInner"]';
    expect($xpath->evaluate('normalize-space(' . $panel . '/h1)'))->toBe($heading)
        ->and($xpath->evaluate('normalize-space(' . $panel . '/p)'))->toBe($opening . ' Browse information ' . $closing)
        ->and($xpath->evaluate('string(' . $panel . '/p/a/@href)'))->toBe($help);

    $server->configure(['welcometext_trim' => 45]);
    $short_home = $server->request('/pages/home.php', headers: $headers);
    expect($short_home['status'])->toBe(200);
    $document->loadHTML($short_home['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('normalize-space(' . $panel . '/p)'))->toContain($opening)->not->toContain($closing);
    $read_more = $xpath->evaluate('string(' . $panel . '/p/a/@href)');
    expect(parse_url($read_more, PHP_URL_PATH))->toBe('/pages/hometext.php');
    $complete = $server->request('/pages/hometext.php', headers: $headers);
    expect($complete['status'])->toBe(200)->and($complete['body'])->toContain($opening, $closing);

    $server->configure(['no_welcometext' => true]);
    $hidden = $server->request('/pages/home.php', headers: $headers);
    expect($hidden['status'])->toBe(200)->and($hidden['body'])->not->toContain('id="HomeSiteTextInner"', $heading);
});

it('finds site content by each admin search field', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $admin = $fixtures->user('contentlookup' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $admin]);
    $rows = [
        ['pagealpha' . $suffix, 'namealpha' . $suffix, 'textalpha' . $suffix],
        ['pagebeta' . $suffix, 'namebeta' . $suffix, 'textbeta' . $suffix],
    ];
    foreach ($rows as [$page, $name, $body]) {
        ps_query('INSERT INTO site_text (page, name, text, language) VALUES (?, ?, ?, ?)', [
            's', $page, 's', $name, 's', $body, 's', 'en',
        ]);
        $fixtures->onCleanup(static fn () => ps_query('DELETE FROM site_text WHERE name = ?', ['s', $name]));
    }
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    foreach (array_combine(['findpage', 'findname', 'findtext'], $rows[0]) as $filter => $value) {
        $results = $server->request('/pages/admin/admin_content.php?' . http_build_query([$filter => $value]), headers: $headers);
        expect($results['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($results['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $row_links = '//table[@class="ListviewStyle"]//td[2]//a';
        $names = [];
        foreach ($xpath->query($row_links) as $link) {
            $names[] = trim($link->textContent);
        }
        expect($names)->toBe([$rows[0][1]]);
    }
});
it('loads page and language specific content for a group and restores default content between groups', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $general = $fixtures->group('Site content general ' . $suffix, 's');
    $staff = $fixtures->group('Site content staff ' . $suffix, 's');
    $custom = 'pest_site_content_' . $suffix;
    $fixtures->onCleanup(static function () use ($general, $staff): void {
        ps_query('DELETE FROM site_text WHERE specific_to_group IN (?, ?)', ['i', $general, 'i', $staff]);
        clear_query_cache('sitetext');
    });

    $GLOBALS['language'] = 'en';
    $GLOBALS['pagename'] = 'home';
    unset($GLOBALS['load_site_text_for_usergroup']);
    $default = $GLOBALS['lang']['newlogindetails'];
    foreach ([
        ['', 'newlogindetails', 'General account text', 'en', $general],
        ['', 'newlogindetails', 'Staff account text', 'en', $staff],
        ['', 'newlogindetails', 'French staff account text', 'fr', $staff],
        ['', $custom, 'Shared staff text', 'en', $staff],
        ['home', $custom, 'Staff dashboard text', 'en', $staff],
        ['search', $custom, 'Staff search text', 'en', $staff],
    ] as [$page, $name, $value, $language, $group]) {
        ps_query(
            'INSERT INTO site_text (page, name, text, language, specific_to_group) VALUES (?, ?, ?, ?, ?)',
            ['s', $page, 's', $name, 's', $value, 's', $language, 'i', $group]
        );
    }
    clear_query_cache('sitetext');

    load_site_text_for_usergroup($general);
    expect($GLOBALS['lang']['newlogindetails'])->toBe('General account text')
        ->and($GLOBALS['lang'])->not->toHaveKey($custom);

    load_site_text_for_usergroup($staff);
    expect($GLOBALS['lang']['newlogindetails'])->toBe('Staff account text')
        ->and($GLOBALS['lang'][$custom])->toBe('Shared staff text')
        ->and($GLOBALS['lang']['home__' . $custom])->toBe('Staff dashboard text')
        ->and($GLOBALS['lang'])->not->toHaveKey('search__' . $custom);

    $GLOBALS['language'] = 'fr';
    load_site_text_for_usergroup($staff);
    expect($GLOBALS['lang']['newlogindetails'])->toBe('French staff account text')
        ->and($GLOBALS['lang'])->not->toHaveKey($custom);

    load_site_text_for_usergroup(null);
    expect($GLOBALS['lang']['newlogindetails'])->toBe($default)
        ->and($GLOBALS['lang'])->not->toHaveKey('home__' . $custom);
});

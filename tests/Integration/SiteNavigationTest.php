<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
$server = null;
beforeEach(function () use (&$fixtures, &$server): void {
    $fixtures = new IntegrationFixtures(['data_joins']);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('reports the resource count and disk usage for the current search', function () use (
    &$fixtures,
    &$server,
): void {
    $id = generateRandomTestID(12);
    $user = $fixtures->user('searchusage' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $refs = [];
    foreach ([1024, 2048, 8192] as $size) {
        $ref = $fixtures->resource(2, 0, file_extension: 'txt');
        $file = get_resource_path($ref, true, '', true, 'txt');
        expect(file_put_contents($file, str_repeat('a', $size)))->toBe($size);
        expect(update_disk_usage($ref))->toBeTrue();
        $refs[] = $ref;
    }
    $server->configure(['show_searchitemsdiskusage' => true]);
    $headers = $server->sessionHeaders($session);
    $search = '!list' . $refs[0] . ':' . $refs[1];
    $results = $server->request('/pages/search.php?' . http_build_query(['search' => $search]), headers: $headers);
    expect($results['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($results['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $route = parse_url($xpath->evaluate('string(//option[@value="search_items_disk_usage"]/@data-url)'));
    expect($route['path'])->toBe('/pages/search_disk_usage.php');
    parse_str($route['query'], $query);
    expect($query['search'])->toBe($search);

    $usage = $server->request($route['path'] . '?' . $route['query'], headers: $headers);
    expect($usage['status'])->toBe(200);
    $document->loadHTML($usage['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $values = [];
    foreach ($xpath->query('//*[@id="CentralSpace"]//div[contains(@class, "Question")]/div[@class="Fixed"]') as $value) {
        $values[] = trim(str_replace("\u{00a0}", ' ', $value->textContent));
    }
    expect($values)->toBe(['2', formatfilesize(3072, html: false)]);
});

it('starts archive search with archive alone and clears the advanced search filters', function () use (
    &$fixtures,
    &$server,
): void {
    $suffix = generateRandomTestID(12);
    $user = $fixtures->user('archivesearch' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $field = $fixtures->field('Archive search ' . $suffix, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
    $columns = array_intersect_key(get_resource_type_field_columns(), [
        'advanced_search' => true, 'keywords_index' => true,
    ]);
    expect(save_resource_type_field($field, $columns, ['advanced_search' => 1, 'keywords_index' => 1]))->toBeTrue();
    $shortname = get_resource_type_field($field)['name'];
    $server->configure([
        'advanced_search_archive_select' => true,
        'search_includes_resources' => true,
        'default_res_types' => '',
        'default_advanced_search_mode' => 'Global',
    ]);
    $headers = $server->sessionHeaders($session);
    $archive = $server->request('/pages/team/team_archive.php', headers: $headers);
    expect($archive['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($archive['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $archive_link = $xpath->evaluate('string(//div[@class="VerticalNav"]//a[contains(@href, "search_advanced.php")]/@href)');
    expect($archive_link)->toContain('search_advanced.php?');
    $link = parse_url($archive_link);
    $archive_form = $server->request($link['path'] . '?' . $link['query'], headers: $headers);
    expect($archive_form['status'])->toBe(200);
    $document->loadHTML($archive_form['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $states = '//form[@id="advancedform"]//input[@name="archive[]" and @checked]';
    expect($xpath->evaluate('count(' . $states . ')'))->toBe(1.0)
        ->and($xpath->evaluate('string(' . $states . '/@value)'))->toBe('2');

    $headers[1] .= '; search=' . rawurlencode($shortname . ':catalogue') . '; access=1';
    $filtered = $server->request($link['path'] . '?' . $link['query'] . '&restypes=1', headers: $headers);
    expect($filtered['status'])->toBe(200);
    $document->loadHTML($filtered['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $types = '//form[@id="advancedform"]//input[contains(@class, "SearchTypeItemCheckbox")]';
    $metadata = "//form[@id='advancedform']//div[@data-question_field_ref='{$field}']//input[@type='text']/@value";
    $access = '//select[@id="search_advanced_access"]/option';
    $clear = '//form[@id="advancedform"]//input[@name="resetform"]';
    expect($xpath->evaluate('count(' . $types . '[@checked])'))->toBe(1.0)
        ->and($xpath->evaluate('string(' . $metadata . ')'))->toBe('catalogue')
        ->and($xpath->evaluate('string(' . $access . '[@selected]/@value)'))->toBe('1')
        ->and($xpath->evaluate('count(' . $clear . ')'))->toBeGreaterThan(0);
    $form = LocalTestServer::hiddenFields($filtered['body'], '//form[@id="advancedform"]');
    $reset = $server->request($link['path'], $form + [
        'resetform' => $xpath->evaluate('string(' . $clear . '/@value)'),
        'archive' => [2], 'access' => 1, 'resourcetype1' => 'yes',
    ], $headers);
    expect($reset['status'])->toBe(200);
    $document->loadHTML($reset['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('count(' . $states . ')'))->toBe(1.0)
        ->and($xpath->evaluate('string(' . $states . '/@value)'))->toBe('0')
        ->and($xpath->evaluate('count(' . $metadata . ')'))->toBe(1.0)
        ->and($xpath->evaluate('string(' . $metadata . ')'))->toBe('')
        ->and($xpath->evaluate('count(' . $types . ')'))->toBeGreaterThan(1)
        ->and($xpath->evaluate('count(' . $types . '[@checked])'))->toBe($xpath->evaluate('count(' . $types . ')'))
        ->and($xpath->evaluate('count(' . $access . '[@selected])'))->toBe(0.0)
        ->and($xpath->evaluate('normalize-space(' . $access . '[1])'))->toBe($GLOBALS['lang']['all']);
});

it('renders configured text and fixed-list fields on normal and XL thumbnail cards', function () use (
    &$fixtures,
    &$server,
): void {
    $id = generateRandomTestID(10);
    $user = $fixtures->user('thumbfields' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $title_field = (int) $GLOBALS['view_title_field'];
    $caption = $fixtures->field('Card caption ' . $id, 1, FIELD_TYPE_TEXT_BOX_MULTI_LINE);
    $palette = $fixtures->field('Card palette ' . $id, 1, FIELD_TYPE_CHECK_BOX_LIST);
    $GLOBALS['data_joins'] = array_values(array_unique([...$GLOBALS['data_joins'], $caption, $palette]));
    check_db_structs();
    $resource = $fixtures->resource(1, 0);
    $title = 'Catalogue illustration ' . $id;
    $description = 'Long caption for a recently digitised illustrated catalogue';
    $errors = [];
    expect(update_field($resource, $title_field, $title, $errors))->toBeTrue()
        ->and(update_field($resource, $caption, $description, $errors))->toBeTrue();
    set_node(null, $palette, 'Ochre', null, 10);
    set_node(null, $palette, 'Indigo', null, 20);
    expect(update_field($resource, $palette, 'Ochre' . NODE_NAME_STRING_SEPARATOR . 'Indigo', $errors))->toBeTrue();

    $server->configure([
        'data_joins' => $GLOBALS['data_joins'], 'xlthumbs' => true,
        'thumbs_display_fields' => [$title_field, $caption, $palette],
        'thumbs_display_extended_fields' => [$caption],
    ]);
    $headers = $server->sessionHeaders($session);
    foreach (['thumbs' => 'normal', 'xlthumbs' => 'xl'] as $display => $size_class) {
        $results = $server->request('/pages/search.php?' . http_build_query([
            'search' => '!list' . $resource, 'display' => $display,
        ]), headers: $headers);
        expect($results['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($results['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $card = '//*[@id="ResourceShell' . $resource . '"]';
        $caption_field = $card . '//div[contains(@class, "ResourceTypeField' . $caption . '")]';
        $palette_field = $card . '//div[contains(@class, "ResourceTypeField' . $palette . '")]';
        expect($xpath->evaluate('string(' . $card . '/@class)'))->toContain($size_class)
            ->and($xpath->evaluate('normalize-space(' . $card . '//div[contains(@class, "resource-card-title")])'))
            ->toBe($title)
            ->and($xpath->evaluate('normalize-space(' . $caption_field . ')'))->toBe($description)
            ->and($xpath->evaluate('string(' . $caption_field . '/@class)'))->toContain('multi-line')
            ->and($xpath->evaluate('normalize-space(' . $palette_field . ')'))->toContain('Ochre', 'Indigo')
            ->and($xpath->evaluate('string(' . $palette_field . '/@class)'))->not->toContain('multi-line');
    }
});
it('places only the first six primary links outside the desktop overflow menu', function () use (
    &$fixtures,
    &$server,
): void {
    $id = generateRandomTestID(10);
    $group = $fixtures->group('Navigation viewers ' . $id, 's,g');
    $viewer = $fixtures->user('navlinks_' . $id, $group);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $viewer,
    ]);
    $server->configure(array_fill_keys([
        'nav2contact_link', 'advanced_search_nav', 'search_results_link', 'themes_navlink',
        'public_collections_top_nav', 'mycollections_link', 'recent_link', 'myrequests_link',
        'mycontributions_link', 'research_request',
    ], false));
    $headers = $server->sessionHeaders($session);
    $desktop = '//nav[button[contains(@class, "icon-ellipsis-vertical")]]';
    $navigation = static function (array $links) use ($server, $headers): DOMXPath {
        $server->configure(['custom_top_nav' => $links]);
        $response = $server->request('/pages/home.php', headers: $headers);
        expect($response['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    };
    $hrefs = static fn (DOMXPath $xpath, string $selector): array => array_map(
        static fn (DOMNode $node): string => $node->nodeValue,
        iterator_to_array($xpath->query($selector . '/@href'))
    );
    $baseline = $hrefs($navigation([]), $desktop . '//li/a');
    expect(count($baseline))->toBeLessThan(6);
    $links = [];
    foreach (range(1, 7 - count($baseline)) as $number) {
        $links[] = [
            'title' => 'Catalogue navigation ' . $number,
            'link' => $server->url . '/pages/search.php?search=nav' . $number,
        ];
    }
    foreach ([6, 7] as $count) {
        $configured = array_slice($links, 0, $count - count($baseline));
        $expected = array_merge(array_column($configured, 'link'), $baseline);
        $xpath = $navigation($configured);
        $menu_items = $desktop . '/ul/li/ul[@data-menu-panel and @hidden]/li/a[@role="menuitem"]';
        expect($hrefs($xpath, $desktop . '/ul/li/a'))->toBe(array_slice($expected, 0, 6))
            ->and($hrefs($xpath, $menu_items))->toBe(array_slice($expected, 6));
    }
});

it('links the served PWA manifest and shows Help and Advice according to configuration', function () use (
    &$fixtures,
    &$server,
): void {
    $user = $fixtures->user('site_navigation_' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query(
        'UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?',
        ['s', $session, 'i', $user]
    );
    $server->configure(['help_link' => true]);
    $headers = $server->sessionHeaders($session);
    $page = $server->request('/pages/user/user_home.php', headers: $headers);
    expect($page['status'])->toBe(200)->and($page['body'])->toContain($server->url . '/pages/help.php');

    $document = new DOMDocument();
    $document->loadHTML($page['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $manifest_link = (new DOMXPath($document))->query('//link[@rel="manifest"]')->item(0);
    expect($manifest_link)->toBeInstanceOf(DOMElement::class);
    $manifest_url = parse_url($manifest_link->getAttribute('href'));
    expect($manifest_url['path'])->toBe('/manifest.json');
    $manifest = $server->request($manifest_url['path']);
    expect($manifest['status'])->toBe(200);
    expect(json_decode($manifest['body'], true, flags: JSON_THROW_ON_ERROR)['display'])->toBe('standalone');

    $article = $server->request('/pages/help.php?page=user/searching', headers: $headers);
    expect($article['status'])->toBe(200)->and($article['body'])
        ->toContain('https://www.resourcespace.com/knowledge-base/user/searching?from_rs=true');

    $server->configure(['help_link' => false]);
    $without = $server->request('/pages/user/user_home.php', headers: $headers);
    expect($without['status'])->toBe(200)->and($without['body'])->not->toContain($server->url . '/pages/help.php');
});

it('opens a public resource as an anonymous visitor and requires the Contact Admin anti-spam answer', function () use (
    &$fixtures,
    &$server,
): void {
    $id = generateRandomTestID(12);
    $name = 'anon_' . $id;
    $group = $fixtures->group('Anonymous visitors ' . $id, 's,g,f*');
    $user = $fixtures->user($name, $group);
    ps_query('UPDATE user SET approved = 1 WHERE ref = ?', ['i', $user]);
    $resource = $fixtures->resource(1, 0);
    $title = 'Public anonymous resource ' . $id;
    $errors = [];
    expect(update_field($resource, $GLOBALS['view_title_field'], $title, $errors))->toBeTrue();

    $server->configure(['anonymous_login' => $name, 'resource_contact_link' => true]);
    $headers = $server->sessionHeaders('');
    $view = $server->request('/pages/view.php?ref=' . $resource, headers: $headers);
    expect($view['status'])->toBe(200)->and($view['body'])->toContain($title, '/pages/ajax/contactadmin.php');

    $path = '/pages/ajax/contactadmin.php?insert=true&ref=' . $resource;
    $response = $server->request($path, headers: $headers);
    expect($response['status'])->toBe(200)->and($response['body'])->toContain('name="antispam"');
    $fields = LocalTestServer::hiddenFields($response['body'], '//form[@id="contactadminform"]');
    expect($fields)->toHaveKeys(['antispamcode', 'antispamtime', $GLOBALS['CSRF_token_identifier']]);
    foreach (['', 'wrong answer'] as $answer) {
        $response = $server->request($path . '&send=true', $fields + [
            'antispam' => $answer, 'messagetext' => 'Anonymous contact specimen ' . $id,
        ], $headers);
        expect($response['status'])->toBe(200)
            ->and($response['body'])->toContain(escape($GLOBALS['lang']['requiredantispam']));
    }
});

it('hides a personal collection from the bar and keeps it in the management list', function () use (
    &$fixtures,
    &$server,
): void {
    $fixture_id = generateRandomTestID(12);
    $user = $fixtures->user('collection_bar_' . $fixture_id, 3);
    $session = generateSecureKey(32);
    ps_query(
        'UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?',
        ['s', $session, 'i', $user]
    );
    $visible = $fixtures->collection($user, 'Visible collection ' . $fixture_id);
    $hidden = $fixtures->collection($user, 'Hidden collection ' . $fixture_id);
    setup_user(get_user($user));
    set_user_collection($user, $visible);
    $headers = $server->sessionHeaders($session);
    $selector = static function (array $response, string $query): DOMNodeList {
        expect($response['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $matches = (new DOMXPath($document))->query($query);
        expect($matches)->toBeInstanceOf(DOMNodeList::class);
        return $matches;
    };

    $bar_path = '/pages/collections.php?collection=' . $visible;
    $visible_option = "//select[@id='collection']/option[@value='{$visible}']";
    $hidden_option = "//select[@id='collection']/option[@value='{$hidden}']";
    expect($selector($server->request($bar_path, headers: $headers), $hidden_option)->length)->toBe(1);

    expect(show_hide_collection($hidden, false))->toBeTrue();
    $bar = $server->request($bar_path, headers: $headers);
    expect($selector($bar, $visible_option)->length)->toBe(1)
        ->and($selector($bar, $hidden_option)->length)->toBe(0);

    $manage = $server->request('/pages/collection_manage.php', headers: $headers);
    $control = "//tr[td[@class='ref' and normalize-space(.)='{$hidden}']]/td[@class='collectionin']/input";
    expect($selector($manage, $control . '[not(@checked)]')->length)->toBe(1);

    expect(show_hide_collection($hidden))->toBeTrue()
        ->and($selector($server->request($bar_path, headers: $headers), $hidden_option)->length)->toBe(1);
});

it('links Recent to the configured search and returns the requested number of newest resources', function () use (
    &$fixtures,
    &$server,
): void {
    $user = $fixtures->user('recent_' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $refs = [];
    for ($count = 0; $count < 7; $count++) {
        $refs[] = $fixtures->resource(1, 0);
    }
    $expected = array_reverse($refs);

    $server->configure(['recent_link' => true, 'recent_search_quantity' => 1000]);
    $headers = $server->sessionHeaders($session);
    $home = $server->request('/pages/home.php', headers: $headers);
    expect($home['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($home['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $link = (new DOMXPath($document))->query('//div[@id="header-container"]//a[normalize-space(.)="Recent"]')->item(0);
    expect($link)->toBeInstanceOf(DOMElement::class);
    $url = parse_url($link->getAttribute('href'));
    expect($url['path'])->toBe('/pages/search.php');
    parse_str($url['query'], $query);
    expect($query['search'])->toBe('!last1000');

    $recent = static fn (string $search): array => array_column(
        do_search($search, order_by: 'resourceid', sort: 'DESC', return_refs_only: true),
        'ref'
    );
    expect(array_slice($recent($query['search']), 0, count($expected)))->toBe($expected)
        ->and($recent('!last5'))->toBe(array_slice($expected, 0, 5));

    foreach ([
        '/pages/home.php' => [false, false],
        '/pages/collections_featured.php' => [true, false],
        '/pages/search.php' => [false, true],
    ] as $expected_path => [$featured, $recent_home]) {
        $server->configure(['use_theme_as_home' => $featured, 'use_recent_as_home' => $recent_home]);
        $landing = $server->request('/index.php', headers: $headers);
        expect($landing['status'])->toBe(302);
        $destination = parse_url($landing['headers']['location'], PHP_URL_PATH);
        expect($destination)->toBe($expected_path);
        if ($recent_home) {
            parse_str(parse_url($landing['headers']['location'], PHP_URL_QUERY), $recent_query);
            expect($recent_query['search'])->toBe($query['search']);
        }
    }
});

it('renders configured metadata fields and tooltip text in the appropriate search form', function () use (
    &$fixtures,
    &$server,
): void {
    $suffix = generateRandomTestID(12);
    $user = $fixtures->user('search_fields_' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $headers = $server->sessionHeaders($session);
    $fields = [];
    $tooltip = 'Search field ' . $suffix;
    $columns = array_intersect_key(get_resource_type_field_columns(), array_fill_keys([
        'simple_search', 'advanced_search', 'tooltip_text', 'keywords_index',
    ], true));
    foreach (['simple' => [1, 0], 'advanced' => [0, 1], 'both' => [1, 1], 'neither' => [0, 0]] as $name => $modes) {
        $field = $fixtures->field('Search ' . $name . ' ' . $suffix, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE);
        expect(save_resource_type_field($field, $columns, [
            'simple_search' => $modes[0], 'advanced_search' => $modes[1],
            'tooltip_text' => $tooltip, 'keywords_index' => 1,
        ]))->toBeTrue();
        $fields[$field] = $modes;
    }

    $server->configure(['basic_simple_search' => false, 'search_includes_themes' => false]);
    $response = $server->request('/pages/search_advanced.php', headers: $headers);
    expect($response['status'])->toBe(200)->and($response['body'])->not->toContain(
        'id="SearchFeaturedCollectionsCheckbox"', 'id="AdvancedSearchFeaturedCollectionsSection"'
    );
    $document = new DOMDocument();
    $document->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    foreach ($fields as $field => [$in_simple, $in_advanced]) {
        $simple = $xpath->query("//form[@id='simple_search_form']//div[@id='simplesearch_{$field}']");
        $advanced = $xpath->query("//div[@id='CentralSpace']//div[@data-question_field_ref='{$field}']");
        expect($simple->length)->toBe($in_simple)->and($advanced->length)->toBe($in_advanced);
        foreach ([$simple, $advanced] as $matches) {
            $node = $matches->item(0);
            if ($node instanceof DOMElement) {
                expect($node->getAttribute('title'))->toBe($tooltip);
            }
        }
    }
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('publishes a signed RSS feed from ordinary, related and recent search results', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $fixtures->plugin('rss2');
        $id = generateRandomTestID(12);
        $admin = $fixtures->user('rss_admin_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $admin,
        ]);
        $outside = $fixtures->resource(1, 0);
        $source = $fixtures->resource(1, 0);
        $first = $fixtures->resource(1, 0);
        $second = $fixtures->resource(1, 0);
        $term = 'RssExhibition' . $id;
        $titles = [
            $outside => 'Unrelated fixture ' . $id, $source => 'Source fixture ' . $id,
            $first => $term . ' portraits', $second => $term . ' drawings',
        ];
        $errors = [];
        foreach ($titles as $resource => $title) {
            expect(update_field($resource, $GLOBALS['view_title_field'], $title, $errors))->toBeTrue();
        }
        expect(update_related_resource($source, [$first, $second]))->toBeTrue();

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure(['baseurl' => $server->url, 'session_autologout' => false, 'enable_remote_apis' => true]);
        $admin_headers = $server->sessionHeaders($session);
        $guest_headers = $server->sessionHeaders('');
        foreach ([$term, '!related' . $source, '!last2'] as $query) {
            $search = $server->request('/pages/search.php?' . http_build_query([
                'search' => $query, 'archive' => 0,
            ]), headers: $admin_headers);
            expect($search['status'])->toBe(200);
            $document = new DOMDocument();
            $document->loadHTML($search['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $url = $xpath->evaluate('string(//option[@value="rss"]/@data-url)');
            expect($url)->toBeString()->toContain('/plugins/rss2/pages/rssfilter.php?');
            $parts = parse_url($url);
            $feed = $server->request($parts['path'] . '?' . $parts['query'], headers: $guest_headers);
            expect($feed['status'])->toBe(200)
                ->and($feed['headers']['content-type'])->toStartWith('application/xml');
            $xml = simplexml_load_string($feed['body'], SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR);
            expect($xml)->toBeInstanceOf(SimpleXMLElement::class);
            expect((string) $xml['version'])->toBe('2.0');
            $items = [];
            foreach ($xml->channel->item as $item) {
                parse_str(parse_url((string) $item->link, PHP_URL_QUERY) ?? '', $params);
                $items[(int) ($params['ref'] ?? 0)] = (string) $item->title;
            }
            ksort($items);
            expect($items)->toBe([$first => $titles[$first], $second => $titles[$second]]);
        }
    } finally {
        $fixtures->cleanUp();
    }
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('routes downloads through terms only when a resource has the configured metadata value', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $fixtures->plugin('conditional_terms');
        $id = generateRandomTestID(10);
        $user = $fixtures->user('conditional_terms_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, accepted_terms = 0, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);
        $field = $fixtures->field('Custom download terms ' . $id, 1, FIELD_TYPE_CHECK_BOX_LIST);
        $match = set_node(null, $field, 'Requires agreement', null, 10);
        $other = set_node(null, $field, 'No agreement needed', null, 20);
        expect($match)->toBeInt()->and($other)->toBeInt();
        $resources = [];
        foreach ([$match, $other] as $node) {
            $resource = $fixtures->resource(1, 0, file_extension: 'jpg');
            expect(copy(testFixture('Images/image_1.jpg'), get_resource_path($resource, true, '', true, 'jpg')))
                ->toBeTrue();
            add_resource_nodes($resource, [$node]);
            $resources[] = $resource;
        }
        [$required, $standard] = $resources;

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url, 'session_autologout' => false, 'terms_download' => false,
            'download_usage' => false,
        ]);
        $headers = $server->sessionHeaders($session);
        $setup = '/plugins/conditional_terms/pages/setup.php';
        $form = $server->request($setup, headers: $headers);
        $hidden = LocalTestServer::hiddenFields($form['body'], '//form[@id="form1"]');
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        expect($server->request($setup, $hidden + [
            'save' => 'yes', 'conditional_terms_field' => $field, 'conditional_terms_value' => 'Requires agreement',
        ], $headers)['status'])->toBe(200);

        $download_link = static function (int $ref) use ($server, $headers): DOMElement {
            $response = $server->request('/pages/view.php?ref=' . $ref, headers: $headers);
            expect($response['status'])->toBe(200);
            $document = new DOMDocument();
            $document->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
            $links = (new DOMXPath($document))->query('//td[contains(@class, "DownloadButton")]/a[@id="downloadlink"]');
            $link = $links->item(0);
            expect($link)->toBeInstanceOf(DOMElement::class);

            return $link;
        };
        $plain = $download_link($standard);
        expect($plain->getAttribute('onclick'))->toContain('/pages/download_progress.php');
        $link = parse_url($download_link($required)->getAttribute('href'));
        expect($link['path'])->toBe('/pages/terms.php');
        $terms = $server->request($link['path'] . '?' . $link['query'], headers: $headers);
        expect($terms['status'])->toBe(200)->and($terms['body'])->toContain('id="iaccept"');
        $accept = LocalTestServer::hiddenFields($terms['body'], '//form[contains(@action, "/pages/terms.php")]');
        expect($accept)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $accepted = $server->request('/pages/terms.php', $accept + ['iaccept' => 'on', 'save' => 'yes'], $headers);
        expect($accepted['status'])->toBe(302);
        $redirect = parse_url($accepted['headers']['location']);
        parse_str($redirect['query'], $query);
        expect($redirect['path'])->toBe('/pages/download_progress.php')
            ->and($query)->toMatchArray(['ref' => (string) $required, 'iaccept' => 'on']);
        expect(ps_value('SELECT accepted_terms AS value FROM user WHERE ref = ?', ['i', $user], 0))->toBe(1);
    } finally {
        $fixtures->cleanUp();
    }
});

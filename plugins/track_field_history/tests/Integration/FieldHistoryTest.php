<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('shows changes only for the selected history fields and the viewed resource', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $fixtures->plugin('track_field_history');
        $id = generateRandomTestID(10);
        $user = $fixtures->user('fieldhistory_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);
        $first = $fixtures->field('Tracked description ' . $id, 1);
        $second = $fixtures->field('Tracked heading ' . $id, 1);
        $ignored = $fixtures->field('Untracked notes ' . $id, 1);
        $resource = $fixtures->resource(1, 0);
        $another = $fixtures->resource(1, 0);

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
        $headers = $server->sessionHeaders($session);
        $setup = '/plugins/track_field_history/pages/setup.php';
        $form = $server->request($setup, headers: $headers);
        expect($form['status'])->toBe(200);
        $form_data = LocalTestServer::hiddenFields($form['body'], '//form[@id="form1"]');
        expect($form_data)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $saved = $server->request($setup, $form_data + ['save' => 'yes', 'track_fields' => [$first, $second]], $headers);
        expect($saved['status'])->toBe(200);
        expect(array_map('intval', get_plugin_config('track_field_history')['track_fields']))->toBe([$first, $second]);

        $errors = [];
        expect(update_field($resource, $first, 'Early catalogue description', $errors))->toBeTrue();
        ps_query('UPDATE resource_log SET date = ? WHERE resource = ? AND resource_type_field = ?', [
            's', '2024-01-01 00:00:00', 'i', $resource, 'i', $first,
        ]);
        expect(update_field($resource, $first, 'Revised catalogue description', $errors))->toBeTrue()
            ->and(update_field($resource, $second, 'Heading change', $errors))->toBeTrue()
            ->and(update_field($resource, $ignored, 'Untracked note change', $errors))->toBeTrue()
            ->and(update_field($another, $first, 'Other resource change', $errors))->toBeTrue();

        $view = $server->request('/pages/view.php?ref=' . $resource, headers: $headers);
        expect($view['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($view['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new DOMXPath($document))->query('//a[contains(@href, "/field_history_log.php?")]');
        expect($links->length)->toBe(2);
        $history = [];
        foreach ($links as $link) {
            $url = parse_url($link->getAttribute('href'));
            parse_str($url['query'], $parameters);
            expect($parameters['ref'])->toBe((string) $resource);
            $history[(int) $parameters['field']] = $url['path'] . '?' . $url['query'];
        }
        expect(array_keys($history))->toEqualCanonicalizing([$first, $second]);
        $log = $server->request($history[$first], headers: $headers);
        expect($log['status'])->toBe(200);
        $document->loadHTML($log['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $changes = $xpath->query('//table[@id="track_field_history_table"]//tr/td[3]');
        expect($changes->length)->toBe(2);
        expect($changes->item(0)->textContent)->toContain('Revised catalogue description', 'Early catalogue description');
        expect($changes->item(1)->textContent)->toBe('+ Early catalogue description');
    } finally {
        $fixtures->cleanUp();
    }
});

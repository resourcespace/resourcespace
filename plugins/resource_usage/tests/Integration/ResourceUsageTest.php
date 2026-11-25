<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('records and removes a resource usage using a configured medium', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $plugin = 'resource_usage';
        $fixtures->plugin($plugin);
        $id = generateRandomTestID(10);
        $resource = $fixtures->resource(1, 0);
        $admin = $fixtures->user('usage_admin_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $admin,
        ]);
        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
        $headers = $server->sessionHeaders($session);
        $medium = 'Exhibition projection ' . $id;
        $description = 'Artwork used in a temporary exhibition ' . $id;
        $location = 'Museum entrance';
        $config = get_plugin_config($plugin) ?? [];
        $config['resource_usage_mediums'] = ['Press', $medium];
        expect(set_plugin_config($plugin, $config))->toBeTrue();

        $view = '/pages/view.php?ref=' . $resource;
        $edit = '/plugins/resource_usage/pages/edit.php';
        $page = $server->request($view, headers: $headers);
        expect($page['status'])->toBe(200)->and($page['body'])->toContain($edit . '?resource=' . $resource);
        $form = $server->request($edit . '?resource=' . $resource, headers: $headers);
        expect($form['status'])->toBe(200)
            ->and($form['body'])->toContain('name="usage_medium[]" value="' . $medium . '"');
        $hidden = LocalTestServer::hiddenFields($form['body'], '//form[contains(@action, "resource_usage/pages/edit.php")]');
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $fixtures->onCleanup(static fn () => ps_query('DELETE FROM resource_usage WHERE resource = ?', ['i', $resource]));

        $created = $server->request($edit, $hidden + [
            'usage_medium' => [$medium], 'description' => $description, 'usage_location' => $location,
            'usage_date_year' => '2024', 'usage_date_month' => '04', 'usage_date_day' => '19',
        ], $headers);
        expect($created['status'])->toBe(302);
        $stored = ps_query('SELECT * FROM resource_usage WHERE resource = ?', ['i', $resource]);
        expect($stored)->toHaveCount(1)->and($stored[0])->toMatchArray([
            'usage_medium' => $medium, 'description' => $description, 'usage_location' => $location,
            'usage_date' => '2024-04-19',
        ]);

        $delete = '/plugins/resource_usage/pages/delete.php';
        $delete_link = $delete . '?ref=' . $stored[0]['ref'] . '&resource=' . $resource;
        $page = $server->request($view, headers: $headers);
        expect($page['body'])->toContain($description, $medium, $location, $delete_link);
        $form = $server->request($delete_link, headers: $headers);
        $hidden = LocalTestServer::hiddenFields($form['body'], '//form[contains(@action, "resource_usage/pages/delete.php")]');
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        expect($server->request($delete, $hidden, $headers)['status'])->toBe(302);
        expect(ps_query('SELECT ref FROM resource_usage WHERE resource = ?', ['i', $resource]))->toBeEmpty();
        expect($server->request($view, headers: $headers)['body'])->not->toContain($description);
    } finally {
        $fixtures->cleanUp();
    }
});

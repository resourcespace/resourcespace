<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('assigns a restricted resource request using the configured metadata value', function (): void {
    $fixtures = new IntegrationFixtures();
    try {
        $fixtures->plugin('autoassign_mrequests');
        $id = generateRandomTestID(12);
        $admin = $fixtures->user('requestsetup' . $id, 3);
        $managers = $fixtures->group('Request managers ' . $id, 's,g,t,R,Ra,f*');
        $manager = $fixtures->user('requestmanager' . $id, $managers);
        $group = $fixtures->group('Request submitters ' . $id, 's,g,q,f*');
        expect(save_usergroup($group, ['request_mode' => 1]))->toBeTrue();
        $requester = $fixtures->user('requestsubmitter' . $id, $group);
        $sessions = [];
        foreach ([$admin, $manager, $requester] as $user) {
            $sessions[$user] = generateSecureKey(32);
            ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
                's', $sessions[$user], 'i', $user,
            ]);
        }
        $field = $fixtures->field('Request routing ' . $id, 1, FIELD_TYPE_DROP_DOWN_LIST);
        $value = 'Collections team';
        $node = set_node(null, $field, $value, null, 10);
        $resource = $fixtures->resource(1, 0);
        expect(put_resource_data($resource, ['access' => RESOURCE_ACCESS_RESTRICTED]))->toBeTrue();
        add_resource_nodes($resource, [$node]);
        $fixtures->onCleanup(static function () use ($field, $requester): void {
            ps_query('DELETE FROM assign_request_map WHERE field_id = ?', ['i', $field]);
            $collections = ps_array('SELECT collection AS value FROM request WHERE user = ?', ['i', $requester]);
            ps_query('DELETE FROM request WHERE user = ?', ['i', $requester]);
            foreach ($collections as $collection) {
                delete_collection($collection);
            }
        });

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url, 'session_autologout' => false, 'owner_field' => 0,
            'manage_request_admin' => [],
        ]);
        $setup = '/plugins/autoassign_mrequests/pages/setup.php';
        $admin_headers = $server->sessionHeaders($sessions[$admin]);
        $form = $server->request($setup, headers: $admin_headers);
        expect($form['status'])->toBe(200)->and($form['body'])->toContain('name="field_value_new"');
        $submitted = $server->request($setup, [
            $GLOBALS['CSRF_token_identifier'] => generateCSRFToken($sessions[$admin], 'form1'),
            'add_new' => 'yes', 'user_group_new' => $managers, 'user_new' => $manager,
            'field_new' => $field, 'field_value_new' => $value,
        ], $admin_headers);
        expect($submitted['status'])->toBe(200);
        expect(ps_value('SELECT user_id AS value FROM assign_request_map WHERE field_id = ?', ['i', $field], 0))
            ->toBe($manager);

        $request = '/pages/resource_request.php?ref=' . $resource;
        $requester_headers = $server->sessionHeaders($sessions[$requester]);
        $request_page = $server->request($request, headers: $requester_headers);
        expect($request_page['status'])->toBe(200);
        $fields = LocalTestServer::hiddenFields($request_page['body'], '//form[contains(@action, "resource_request.php")]');
        expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $reason = 'Access for the publication review';
        $done = $server->request($request, $fields + ['save' => 'yes', 'request' => $reason], $requester_headers);
        expect($done['status'])->toBe(200)->and($done['body'])->toContain('/pages/done.php');
        $requests = ps_query('SELECT assigned_to, request_mode, status, collection, comments FROM request WHERE user = ?', [
            'i', $requester,
        ]);
        expect($requests)->toHaveCount(1);
        expect($requests[0])->toMatchArray(['assigned_to' => $manager, 'request_mode' => 1, 'status' => 0]);
        expect($requests[0]['comments'])->toContain($reason);
        expect(get_collection_resources($requests[0]['collection']))->toBe([$resource]);
    } finally {
        $fixtures->cleanUp();
    }
});

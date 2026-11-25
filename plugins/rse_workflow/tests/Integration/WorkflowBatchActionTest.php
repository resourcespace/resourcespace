<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

it('offers a configured workflow action and moves only eligible search or collection results', function (string $scope): void {
    $fixtures = new IntegrationFixtures(['lang']);
    try {
        $fixtures->plugin('rse_workflow');
        register_plugin_language('rse_workflow');
        $id = generateRandomTestID(10);
        $admin = $fixtures->user('workflow_batch_' . $id, 3);
        $session = generateSecureKey(32);
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $admin,
        ]);
        $fixtures->onCleanup(static function () use ($admin): void {
            foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $admin]) as $message) {
                message_remove($message);
            }
        });
        $term = 'WorkflowCatalogue' . $id;
        $open = $fixtures->resource(1, 0, $admin);
        $restricted = $fixtures->resource(1, 0, $admin);
        $different_state = $fixtures->resource(1, 1, $admin);
        $elsewhere = $fixtures->resource(1, 0, $admin);
        ps_query('UPDATE resource SET access = ? WHERE ref IN (?, ?)', [
            'i', RESOURCE_ACCESS_RESTRICTED, 'i', $restricted, 'i', $different_state,
        ]);
        $errors = [];
        foreach ([$open, $restricted, $different_state] as $ref) {
            expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        }

        $server = new LocalTestServer();
        $fixtures->onCleanup($server->close(...));
        $server->configure([
            'baseurl' => $server->url, 'session_autologout' => false, 'search_all_workflow_states' => true,
        ]);
        $headers = $server->sessionHeaders($session);
        $root = '/plugins/rse_workflow/pages/';
        $edit = $root . 'edit_action.php?ref=new';
        $form = $server->request($edit, headers: $headers);
        expect($form['status'])->toBe(200);
        $hidden = LocalTestServer::hiddenFields($form['body'], '//form[@id="form_workflow_action"]');
        expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        $name = 'Review catalogue ' . $id;
        $saved = $server->request($edit, $hidden + [
            'actionname' => $name, 'actiontext' => 'Send the selected resources for review',
            'buttontext' => $name, 'from0' => 'yes', 'actionto' => '-1',
        ], $headers);
        expect($saved['status'])->toBe(200);
        $action = (int) ps_value('SELECT ref AS value FROM workflow_actions WHERE name = ?', ['s', $name], 0);
        expect($action)->toBeGreaterThan(0);
        $fixtures->onCleanup(static function () use ($action): void {
            ps_query('DELETE FROM workflow_actions WHERE ref = ?', ['i', $action]);
            clear_query_cache('workflow');
        });

        $params = ['search' => $term, 'archive' => '0,1'];
        $affected = [$open, $restricted];
        if ($scope === 'restricted search') {
            $params['access'] = RESOURCE_ACCESS_RESTRICTED;
            $affected = [$restricted];
        }
        if ($scope === 'collection') {
            $collection = $fixtures->collection($admin, 'Workflow selection ' . $id);
            expect(add_resource_to_collection($open, $collection))->toBeTrue()
                ->and(add_resource_to_collection($different_state, $collection))->toBeTrue();
            $action_path = $root . 'batch_action.php?' . http_build_query([
                'action' => $action, 'collection' => $collection, 'archive' => '0,1',
            ]);
            $affected = [$open];
        } else {
            $results = $server->request('/pages/search.php?' . http_build_query($params), headers: $headers);
            expect($results['status'])->toBe(200);
            $document = new DOMDocument();
            $document->loadHTML($results['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
            $option = '//option[@value="rse_workflow_move_to_workflow~' . $action . '"]';
            $xpath = new DOMXPath($document);
            expect($xpath->evaluate('normalize-space(' . $option . ')'))->toBe($name);
            $route = parse_url($xpath->evaluate('string(' . $option . '/@data-url)'));
            expect($route['path'])->toBe($root . 'batch_action.php');
            $action_path = $route['path'] . '?' . $route['query'];
        }

        $confirmation = $server->request($action_path, headers: $headers);
        expect($confirmation['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($confirmation['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $count = str_replace('%count', (string) count($affected), text('rse_workflow_affected_resources'));
        $button = '//button[contains(@onclick, "process_wf_action")]';
        expect($xpath->evaluate('normalize-space(' . $button . '/../preceding-sibling::p[1])'))->toBe($count);
        $token = $xpath->evaluate('string(' . $button . '/@data-csrf-token)');
        expect($token)->not->toBeEmpty();
        $applied = $server->request($action_path, [
            'ajax' => 'true', 'process_action' => 'true', $GLOBALS['CSRF_token_identifier'] => $token,
        ], $headers);
        expect($applied['status'])->toBe(200);
        $result = json_decode($applied['body'], true, flags: JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe('success')->and($result['data'])->toEqualCanonicalizing($affected);
        foreach ([$open => 0, $restricted => 0, $different_state => 1, $elsewhere => 0] as $ref => $state) {
            expect((int) get_resource_data($ref, false)['archive'])->toBe(in_array($ref, $affected, true) ? -1 : $state);
        }
    } finally {
        $fixtures->cleanUp();
    }
})->with(['search', 'restricted search', 'collection']);

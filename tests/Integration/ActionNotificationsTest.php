<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/action_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/request_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'actions_resource_requests', 'actions_account_requests', 'actions_notify_states',
        'actions_approve_hide_groups', 'usergroup_approval_mappings',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('returns recent review actions by workflow state, resource type and reviewer', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $admin = (int) $GLOBALS['userref'];
    $supervisor = $fixtures->user('review_supervisor_' . $id, 3);
    $contributor = $fixtures->user('review_contributor_' . $id, 2);
    $fixtures->onCleanup(static function () use ($supervisor, $contributor): void {
        ps_query('DELETE FROM user_preferences WHERE user IN (?, ?)', ['i', $supervisor, 'i', $contributor]);
        clear_query_cache('preferences');
    });
    ps_query('UPDATE user SET approved = 1 WHERE ref IN (?, ?)', ['i', $supervisor, 'i', $contributor]);
    foreach ([$supervisor => '-1', $contributor => '-2'] as $user => $state) {
        foreach ([
            'user_pref_new_action_emails' => '1', 'actions_resource_review' => '1',
            'actions_notify_states' => $state, 'actions_resource_types_hide' => '2',
        ] as $key => $value) {
            expect(set_config_option($user, $key, $value))->toBeTrue();
        }
    }

    $active = $fixtures->resource(1, 0, $contributor);
    $photo = $fixtures->resource(1, -1, $contributor);
    $document = $fixtures->resource(2, -1, $contributor);
    $video = $fixtures->resource(3, -1, $contributor);
    $scope = [$active, $photo, $document, $video];
    $review_refs = static fn (array $actions, int $user): array => array_values(
        array_intersect($scope, array_column($actions[$user]['resourcereview'] ?? [], 'ref'))
    );
    $actions = get_user_actions_recent(5, true);
    expect($review_refs($actions, $supervisor))->toEqualCanonicalizing([$photo, $video])
        ->and($review_refs($actions, $contributor))->toBeEmpty();

    setup_user(get_user($admin));
    foreach ([$photo, $document, $video] as $ref) {
        update_archive_status($ref, -2);
        expect((int) get_resource_data($ref, false)['archive'])->toBe(-2);
    }
    $actions = get_user_actions_recent(5, true);
    expect($review_refs($actions, $contributor))->toEqualCanonicalizing([$photo, $video])
        ->and($review_refs($actions, $supervisor))->toBeEmpty();
});

it('includes pending managed requests and account approvals for the current administrator', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $manager_group = $fixtures->group('Action managers ' . $id, 's,g,t,u,R,Ra,e0,f*');
    $manager = $fixtures->user('action_manager_' . $id, $manager_group);
    $requester = $fixtures->user('requester_' . $id);
    $pending = $fixtures->user('account_pending_' . $id);
    ps_query('UPDATE user SET approved = 0 WHERE ref = ?', ['i', $pending]);
    $collection = $fixtures->collection($requester, 'Pending request ' . $id, extraparams: ["type" => COLLECTION_TYPE_REQUEST]);
    ps_query(
        'INSERT INTO request (user, collection, created, status, request_mode) VALUES (?, ?, NOW(), 0, 1)',
        ['i', $requester, 'i', $collection]
    );
    $request = sql_insert_id();
    $fixtures->onCleanup(static function () use ($request): void {
        ps_query('DELETE FROM request WHERE ref = ?', ['i', $request]);
    });
    setup_user(get_user($manager));
    $GLOBALS['usergroup_approval_mappings'] = [];
    $GLOBALS['actions_resource_requests'] = true;
    $GLOBALS['actions_account_requests'] = true;
    $GLOBALS['actions_approve_hide_groups'] = '';

    $actions = get_user_actions_recent(5, false);
    expect(array_keys($actions))->toBe([$manager])
        ->and(array_column($actions[$manager]['resourcerequest'], 'ref'))->toContain($request)
        ->and(array_column($actions[$manager]['userrequest'], 'ref'))->toContain($pending);
    $GLOBALS['actions_resource_requests'] = false;
    $GLOBALS['actions_account_requests'] = false;
    $disabled = get_user_actions_recent(5, false)[$manager];
    expect($disabled)->not->toHaveKey('userrequest')->not->toHaveKey('resourcerequest');
});

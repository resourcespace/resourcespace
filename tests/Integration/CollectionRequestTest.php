<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/request_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'lang', 'resource_request_reason_required', 'resource_type_request_emails',
        'admin_resource_access_notifications', 'custom_request_fields', 'custom_request_required',
        'themes_in_my_collections', 'userpermissions', 'user_is_anon',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('keeps an email request collection searchable to its limited requester and snapshots its members', function () use (
    &$fixtures,
): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Request readers ' . $id, 's,g,t,h,r,i,v,q,R,Ra,x,f*');
    $user = $fixtures->user('requestreader' . $id, $group);
    $original = $fixtures->collection($user, 'Request originals ' . $id);
    $first = $fixtures->resource(1, 0, $user);
    $later = $fixtures->resource(1, 0, $user);
    expect(add_resource_to_collection($first, $original))->toBeTrue();

    setup_user(get_user($user));
    $GLOBALS['resource_request_reason_required'] = false;
    $GLOBALS['resource_type_request_emails'] = [];
    $GLOBALS['admin_resource_access_notifications'] = false;
    $GLOBALS['custom_request_fields'] = 'Purpose';
    $GLOBALS['themes_in_my_collections'] = true;
    unset($GLOBALS['custom_request_required']);
    $request_name = 'Request snapshot ' . $id;
    $GLOBALS['lang']['requestcollection'] = $request_name;
    $fixtures->onCleanup(static function () use ($user, $request_name): void {
        foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $user]) as $id) {
            message_remove($id);
        }
        $ref = (int) ps_value('SELECT ref AS value FROM collection WHERE name = ?', ['s', $request_name], 0);
        if ($ref > 0) {
            delete_collection($ref);
        }
    });

    expect(email_collection_request($original, '', ''))->toBeTrue();
    $copy = (int) ps_value('SELECT ref AS value FROM collection WHERE name = ?', ['s', $request_name], 0);
    expect($copy)->toBeGreaterThan(0)->not->toBe($original);
    expect(add_resource_to_collection($later, $original))->toBeTrue();
    expect(get_collection_resources($original))->toEqualCanonicalizing([$first, $later])
        ->and(array_column(do_search('!collection' . $copy), 'ref'))->toBe([$first]);
});

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'collection_block_restypes', 'anonymous_login', 'collection_share_warning', 'CACHE_FC_ACCESS_CONTROL',
        'userpermissions', 'use_error_exception', 'CACHE_FC_RESOURCES', 'collection_allow_not_approved_share',
        'userderestrictfilter', 'resource_access_cache', 'get_resource_data_cache', 'resource_deletion_state',
    ]);
    $GLOBALS['collection_block_restypes'] = [];
    $GLOBALS['anonymous_login'] = '';
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('renders the collection log and offers sharing for an empty collection only when configured', function () use (
    &$fixtures,
): void {
    $id = generateRandomTestID(12);
    $owner = $fixtures->user('collectionactions' . $id, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $owner,
    ]);
    $name = 'Catalogue prints ' . $id;
    $collection = $fixtures->collection($owner, $name);
    $other = $fixtures->collection($owner, 'Unrelated collection ' . $id);
    $note = 'Catalogue prints reviewed ' . $id;
    $other_note = 'Unrelated review ' . $id;
    collection_log($collection, LOG_CODE_EDITED, null, $note);
    collection_log($other, LOG_CODE_EDITED, null, $other_note);

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'session_autologout' => false, 'allow_share' => true,
        'manage_collections_share_link' => true, 'collection_allow_empty_share' => false,
    ]);
    $headers = $server->sessionHeaders($session);
    $bar = '/pages/collections.php?collection=' . $collection;
    $actions = static function () use ($server, $bar, $headers, $collection): DOMXPath {
        $response = $server->request($bar, headers: $headers);
        expect($response['status'])->toBe(200);
        $document = new DOMDocument();
        $document->loadHTML($response['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('count(//select[@id="collections_action_selection_bottom_' . $collection . '"])'))
            ->toBe(1.0);

        return $xpath;
    };
    $share = '//select[@id="collections_action_selection_bottom_' . $collection . '"]/optgroup/option[@value="share_collection"]';
    expect($actions()->evaluate('count(' . $share . ')'))->toBe(0.0);
    $server->configure(['collection_allow_empty_share' => true]);
    $with_empty_sharing = $actions();
    expect($with_empty_sharing->evaluate('count(' . $share . ')'))->toBe(1.0);
    $log = '//select[@id="collections_action_selection_bottom_' . $collection . '"]/optgroup/option[@value="collection_log"]';
    $url = parse_url($with_empty_sharing->evaluate('string(' . $log . '/@data-url)'));
    expect($url['path'])->toBe('/pages/collection_log.php');
    parse_str($url['query'], $query);
    expect($query['ref'])->toBe((string) $collection);
    $response = $server->request($url['path'] . '?' . $url['query'], headers: $headers);
    expect($response['status'])->toBe(200)->and($response['body'])->toContain($name, $note);
    expect($response['body'])->not->toContain($other_note);

    $server->configure(['collection_allow_empty_share' => false]);
    $resource = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($resource, $collection))->toBeTrue();
    expect($actions()->evaluate('count(' . $share . ')'))->toBe(1.0);
});

it('keeps membership unique and checks access for adding and removing resources', function () use (&$fixtures): void {
    $owner = get_user($GLOBALS['userref']);
    $first = $fixtures->collection($owner['ref'], 'Membership ' . generateRandomTestID(16));
    $second = $fixtures->collection($owner['ref'], 'Membership ' . generateRandomTestID(16));
    $resource = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($resource, $first))->toBeTrue()
        ->and(add_resource_to_collection($resource, $first))->toBeTrue()
        ->and(add_resource_to_collection($control, $first))->toBeTrue()
        ->and(add_resource_to_collection($resource, $second))->toBeTrue()
        ->and(get_collection_resources($first))->toEqualCanonicalizing([$resource, $control]);

    $other = $fixtures->user('collectionreader' . generateRandomTestID(16));
    setup_user(get_user($other));
    expect(add_resource_to_collection($control, $second))->not->toBeTrue()
        ->and(remove_resource_from_collection($resource, $first))->not->toBeTrue()
        ->and(get_collection_resources($first))->toEqualCanonicalizing([$resource, $control])
        ->and(get_collection_resources($second))->toBe([$resource]);

    setup_user($owner);
    $GLOBALS['collection_block_restypes'] = [1];
    expect(add_resource_to_collection($control, $second))->not->toBeTrue()
        ->and(get_collection_resources($second))->toBe([$resource]);
    expect(remove_resource_from_collection($resource, $first))->toBeTrue()
        ->and(get_collection_resources($first))->toBe([$control])
        ->and(get_collection_resources($second))->toBe([$resource])
        ->and(get_resource_data($resource, false))->not->toBeFalse();
});

it('propagates a collection share to additions and revokes only that share on removal', function () use (&$fixtures): void {
    $user = (int) $GLOBALS['userref'];
    $collection = $fixtures->collection($user, 'Shared membership ' . generateRandomTestID(16));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($first, $collection))->toBeTrue();
    $key = generate_collection_access_key($collection, 0, '', RESOURCE_ACCESS_FULL);
    expect($key)->toBeString()->not->toBeEmpty();
    $shares = static fn (int $ref): int => (int) ps_value(
        'SELECT COUNT(*) AS value FROM external_access_keys WHERE collection = ? AND resource = ? AND access_key = ?',
        ['i', $collection, 'i', $ref, 's', $key],
        0
    );
    expect($shares($first))->toBe(1)
        ->and(add_resource_to_collection($second, $collection))->toBeTrue()
        ->and($shares($second))->toBe(1);

    $direct = generate_resource_access_key($second, $user, RESOURCE_ACCESS_FULL, '', '');
    expect($direct)->toBeString()->not->toBeEmpty();
    $fixtures->onCleanup(static function () use ($direct): void {
        ps_query('DELETE FROM external_access_keys WHERE access_key = ?', ['s', $direct]);
    });
    expect(remove_resource_from_collection($second, $collection))->toBeTrue()
        ->and($shares($first))->toBe(1)
        ->and($shares($second))->toBe(0)
        ->and(check_access_key($second, $direct))->not->toBeFalse();
    delete_collection_access_key($collection, $key);
    expect($shares($first))->toBe(0)
        ->and(get_resource_data($first, false))->not->toBeFalse()
        ->and(get_collection_resources($collection))->toBe([$first]);
});

it('merges and replaces collection members and removes only collection data at deletion', function () use (&$fixtures): void {
    $owner = get_user($GLOBALS['userref']);
    $source = $fixtures->collection($owner['ref'], 'Copy source ' . generateRandomTestID(16));
    $target = $fixtures->collection($owner['ref'], 'Copy target ' . generateRandomTestID(16));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $extra = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($first, $source, false, '', '', null, null, '', 10))->toBeTrue()
        ->and(add_resource_to_collection($second, $source, false, '', '', null, null, '', 20))->toBeTrue()
        ->and(add_resource_to_collection($extra, $target))->toBeTrue();

    copy_collection($source, $target);
    expect(get_collection_resources($target))->toEqualCanonicalizing([$first, $second, $extra]);
    copy_collection($source, $target, true);
    expect(get_collection_resources($target))->toBe([$first, $second])
        ->and(get_collection_resources($source))->toBe([$first, $second]);
    expect(generate_collection_access_key($target, 0, '', RESOURCE_ACCESS_FULL))->toBeString()->not->toBeEmpty();

    $other = $fixtures->user('collectionowner' . generateRandomTestID(16));
    setup_user(get_user($other));
    expect(delete_collection($target))->toBeFalse();
    expect(get_collection($target))->not->toBeFalse();

    setup_user($owner);
    delete_collection($target);
    expect(get_collection($target))->toBeFalse()
        ->and(get_collection_resources($target))->toBeEmpty()
        ->and((int) ps_value(
            'SELECT COUNT(*) AS value FROM external_access_keys WHERE collection = ?',
            ['i', $target],
            -1
        ))->toBe(0)
        ->and(get_collection_resources($source))->toBe([$first, $second])
        ->and(get_resource_data($extra, false))->not->toBeFalse()
        ->and(get_resource_data($first, false))->not->toBeFalse();
});

it('returns the same collection member records to the admin and collection owner', function () use (&$fixtures): void {
    $owner = $fixtures->user('collectiondata' . generateRandomTestID(12));
    $collection = $fixtures->collection($owner, 'Collection records ' . generateRandomTestID(12), 1);
    $active = $fixtures->resource(1, 0);
    $deleted = $fixtures->resource(1, 3);
    expect(add_resource_to_collection($active, $collection))->toBeTrue()
        ->and(add_resource_to_collection($deleted, $collection))->toBeTrue();
    $admin_result = get_collection_resources_with_data($collection);

    setup_user(get_user($owner));
    expect(get_collection_resources_with_data($collection))->toBe($admin_result)
        ->and(array_column($admin_result, 'archive', 'ref'))->toHaveKey($deleted, 3);
});

it('requires edit permission and unrestricted category visibility to reorder featured collections', function (
    array $permissions,
    bool $allowed,
): void {
    $GLOBALS['userpermissions'] = $permissions;
    unset($GLOBALS['CACHE_FC_ACCESS_CONTROL']);
    expect(can_reorder_featured_collections())->toBe($allowed);
})->with([
    'no permissions' => [[], false],
    'visibility only' => [['j*'], false],
    'edit only' => [['h'], false],
    'edit and full visibility' => [['h', 'j*'], true],
    'edit with an explicit exclusion' => [['h', 'j*', '-j1405'], false],
]);

it('persists collection order while ignoring invalid references', function (
    array $order,
    array $expected,
) use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $refs = [];
    foreach (['a', 'b', 'c'] as $name) {
        $refs[$name] = $fixtures->collection($owner, $name . generateRandomTestID(12));
    }

    sql_reorder_records('collection', array_values($refs));
    $new_order = array_map(
        static fn (string $value): int|string => str_starts_with($value, '#')
            ? (string) $refs[substr($value, 1)]
            : ($refs[$value] ?? $value),
        $order
    );
    sql_reorder_records('collection', $new_order);

    $stored = ps_array(
        'SELECT ref AS value FROM collection WHERE ref IN (?, ?, ?) ORDER BY order_by',
        ps_param_fill(array_values($refs), 'i')
    );
    expect(array_map('intval', $stored))->toBe(array_map(static fn (string $key): int => $refs[$key], $expected));
})->with([
    'explicit order' => [['b', 'a', 'c'], ['b', 'a', 'c']],
    'empty order' => [[], ['a', 'b', 'c']],
    'invalid references skipped' => [['c', 'invalid', 'a', 'b', 'also invalid'], ['c', 'a', 'b']],
    'numeric strings' => [['c', 'a', '#b'], ['c', 'a', 'b']],
]);

it('filters personal collections by owner and name', function () use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $other = $fixtures->user('collectionsearch' . generateRandomTestID(12), 2);
    $term = 'User collection ' . generateRandomTestID(12);
    $first = $fixtures->collection($owner, $term . ' A');
    $second = $fixtures->collection($owner, $term . ' B');
    $third = $fixtures->collection($other, $term . ' C');
    $fixtures->collection($owner, 'Unrelated collection');

    expect(array_column(get_user_collections($owner, $term, 'created', 'ASC'), 'ref'))
        ->toEqualCanonicalizing([$first, $second]);
    setup_user(get_user($other));
    expect(array_column(get_user_collections($other, $term, 'created', 'ASC'), 'ref'))->toBe([$third]);
    add_collection($other, $second);
    expect(array_column(get_user_collections($other, $term, 'created', 'ASC'), 'ref'))
        ->toEqualCanonicalizing([$third, $second]);
});

it('updates collection counts as membership changes and respects access and workflow', function () use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $user = $fixtures->user('collectioncount' . generateRandomTestID(12), 2);
    $mixed = $fixtures->collection($owner, 'Mixed workflow ' . generateRandomTestID(12));
    $hidden = $fixtures->collection($owner, 'Confidential ' . generateRandomTestID(12));
    $active = $fixtures->resource(1, 0);
    $deleted = $fixtures->resource(1, 3);
    $confidential = $fixtures->resource(1, 0);
    $extra = $fixtures->resource(1, 0);
    expect(put_resource_data($confidential, ['access' => RESOURCE_ACCESS_CONFIDENTIAL]))->toBeTrue()
        ->and(add_resource_to_collection($active, $mixed))->toBeTrue()
        ->and(add_resource_to_collection($deleted, $mixed))->toBeTrue()
        ->and(add_resource_to_collection($confidential, $hidden))->toBeTrue();

    expect(get_collections_resource_count([$mixed, $hidden]))->toBe([$mixed => 2, $hidden => 1])
        ->and(add_resource_to_collection($extra, $mixed))->toBeTrue()
        ->and(get_collections_resource_count([$mixed]))->toBe([$mixed => 3])
        ->and(remove_resource_from_collection($extra, $mixed))->toBeTrue();

    setup_user(get_user($user));
    expect(get_collections_resource_count([$mixed, $hidden]))->toBeEmpty();
    add_collection($user, $mixed);
    add_collection($user, $hidden);
    expect(get_collections_resource_count([$mixed, $hidden]))->toBe([$mixed => 1, $hidden => 0]);
});

it('filters resource members from featured collections and their parent category by workflow', function () use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $parent = $fixtures->collection($owner, 'Featured parent ' . generateRandomTestID(10));
    $collections = [$parent];
    foreach (['mixed', 'active', 'deleted'] as $name) {
        $collections[] = $fixtures->collection($owner, $name . generateRandomTestID(10));
    }
    ps_query(
        'UPDATE collection SET type = ?, thumbnail_selection_method = 1 WHERE ref IN (?, ?, ?, ?)',
        array_merge(['i', COLLECTION_TYPE_FEATURED], ps_param_fill($collections, 'i'))
    );
    [$parent, $mixed, $approved, $removed] = $collections;
    ps_query('UPDATE collection SET parent = ? WHERE ref IN (?, ?, ?)', ps_param_fill($collections, 'i'));

    $active = $fixtures->resource(1, 0);
    $deleted = $fixtures->resource(1, 3);
    expect(add_resource_to_collection($active, $mixed))->toBeTrue()
        ->and(add_resource_to_collection($deleted, $mixed))->toBeTrue()
        ->and(add_resource_to_collection($active, $approved))->toBeTrue()
        ->and(add_resource_to_collection($deleted, $removed))->toBeTrue();

    $GLOBALS['userpermissions'] = ['s', 'j*', 'f*', 'e0', 'e3', 'c', 'D', 'h'];
    unset($GLOBALS['CACHE_FC_ACCESS_CONTROL']);
    $children = array_column(get_featured_collections($parent, []), null, 'ref');
    $roots = array_column(get_featured_collections(0, []), null, 'ref');

    foreach ([false, true] as $include_unapproved) {
        $GLOBALS['collection_allow_not_approved_share'] = $include_unapproved;
        $GLOBALS['CACHE_FC_RESOURCES'] = [];
        $visible = $include_unapproved ? [$active, $deleted] : [$active];
        expect(get_featured_collection_resources($children[$mixed], []))->toEqualCanonicalizing($visible)
            ->and(get_featured_collection_resources($children[$approved], []))->toBe([$active])
            ->and(get_featured_collection_resources($children[$removed], []))->toBe($include_unapproved ? [$deleted] : [])
            ->and(get_featured_collection_resources($roots[$parent], []))->toEqualCanonicalizing($visible);
    }
});

it('reorders collection members and calculates access across the members visible to the owner', function () use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $user = $fixtures->user('collection_access_' . generateRandomTestID(10));
    $collection = $fixtures->collection($user, 'Collection access bounds');
    update_collection_order([], $collection);
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_CONFIDENTIAL)
        ->and(collection_max_access($collection))->toBe(RESOURCE_ACCESS_CONFIDENTIAL);

    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $third = $fixtures->resource(1, 0);
    foreach ([$first, $second, $third] as $ref) {
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
    }
    update_collection_order([$third, $first, $second], $collection);
    expect(get_collection_resources($collection))->toBe([$third, $first, $second]);

    setup_user(get_user($user));
    $GLOBALS['userpermissions'] = ['s', 'g', 'f*'];
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_FULL)
        ->and(collection_max_access($collection))->toBe(RESOURCE_ACCESS_FULL);

    $GLOBALS['userpermissions'] = ['s', 'f*'];
    $GLOBALS['resource_access_cache'] = [];
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_RESTRICTED);

    $GLOBALS['userpermissions'] = ['s', 'g', 'f*', 'X1'];
    $GLOBALS['resource_access_cache'] = [];
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_RESTRICTED);

    setup_user(get_user($owner));
    expect(put_resource_data($first, ['access' => RESOURCE_ACCESS_RESTRICTED]))->toBeTrue();
    $field = $fixtures->field('Access filter options', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $node = set_node(null, $field, 'Grant collection access', null, 10);
    $filter = save_filter(0, 'Collection access filter ' . generateRandomTestID(10), (string) RS_FILTER_ALL);
    expect($filter)->toBeInt()->toBeGreaterThan(0);
    $fixtures->onCleanup(static fn () => delete_filter($filter));
    expect(save_filter_rule(0, $filter, [[RS_FILTER_NODE_IN, [$node]]]))->toBeInt();
    add_resource_nodes($first, [$node]);

    setup_user(get_user($user));
    $GLOBALS['userpermissions'] = ['s', 'g', 'f*'];
    $GLOBALS['resource_access_cache'] = [];
    unset($GLOBALS['get_resource_data_cache'][$first]);
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_RESTRICTED)
        ->and(collection_max_access($collection))->toBe(RESOURCE_ACCESS_FULL);

    setup_user(get_user($owner));
    expect(put_resource_data($first, ['access' => RESOURCE_ACCESS_FULL]))->toBeTrue();
    setup_user(get_user($user));
    unset($GLOBALS['get_resource_data_cache'][$first]);
    $GLOBALS['userpermissions'] = ['s', 'f*'];
    $GLOBALS['userderestrictfilter'] = $filter;
    $GLOBALS['resource_access_cache'] = [];
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_RESTRICTED);
    add_resource_nodes($second, [$node], false, false);
    add_resource_nodes($third, [$node], false, false);
    $GLOBALS['resource_access_cache'] = [];
    expect(collection_min_access($collection))->toBe(RESOURCE_ACCESS_FULL);
});

it('updates collection details, publishes collections and applies membership actions', function () use (&$fixtures): void {
    $owner = (int) $GLOBALS['userref'];
    $suffix = generateRandomTestID(10);
    $collection = $fixtures->collection($owner, 'Private collection ' . $suffix);
    $featured = $fixtures->collection($owner, 'Featured child ' . $suffix);
    $parent = $fixtures->collection($owner, 'Featured parent ' . $suffix);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $control = $fixtures->resource(1, 0);
    expect(save_collection('not a reference', ['name' => 'Invalid']))->toBeFalse();

    foreach ([$first, $second] as $ref) {
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
    }
    save_collection($collection, ['relateall' => 1]);
    expect(get_related_resources($first))->toBe([$second]);
    save_collection($collection, ['removeall' => 1]);
    expect(get_collection_resources($collection))->toBeEmpty();

    $details = [
        'name' => 'Published collection ' . $suffix,
        'created' => '2021-03-17 19:30:00',
        'allow_changes' => 1,
        'cant_delete' => 1,
        'keywords' => 'searchable keyword',
        'home_page_publish' => 1,
        'home_page_text' => 'Introduction',
        'home_page_image' => 0,
        'session_id' => 123,
        'description' => 'Updated description',
    ];
    $fixtures->onCleanup(static fn () => save_collection($collection, ['cant_delete' => 0]));
    save_collection($collection, $details + ['public' => 1]);
    expect(array_intersect_key(get_collection($collection), $details))->toEqual($details)
        ->and(array_column(search_public_collections(), 'ref'))->toContain($collection);

    save_collection($featured, ['type' => COLLECTION_TYPE_PUBLIC]);
    expect((int) get_collection($featured)['type'])->toBe(COLLECTION_TYPE_PUBLIC);
    expect(add_resource_to_collection($control, $featured))->toBeTrue();
    save_collection($parent, ['type' => COLLECTION_TYPE_FEATURED]);
    $method = $GLOBALS['FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS']['manual'];
    save_collection($featured, [
        'featured_collections_changes' => ['update_parent' => $parent, 'thumbnail_selection_method' => $method],
        'bg_img_resource_ref' => $control,
    ]);
    $children = array_column(get_featured_collections($parent, []), null, 'ref');
    expect($children[$featured])->toMatchArray([
        'type' => COLLECTION_TYPE_FEATURED, 'parent' => $parent,
        'thumbnail_selection_method' => $method, 'bg_img_resource_ref' => $control,
    ]);
    expect(get_featured_collection_resources($children[$featured], ['use_thumbnail_selection_method' => true]))
        ->toBe([$control]);

    $GLOBALS['userpermissions'] = array_values(array_diff($GLOBALS['userpermissions'], ['D']));
    $GLOBALS['resource_deletion_state'] = 3;
    foreach ([$first, $second] as $ref) {
        expect(add_resource_to_collection($ref, $collection))->toBeTrue();
    }
    expect(delete_resources_in_collection($collection))->toBeTrue();
    foreach ([$first, $second] as $ref) {
        expect((int) get_resource_data($ref, false)['archive'])->toBe(3);
    }
    expect((int) get_resource_data($control, false)['archive'])->toBe(0);
});

it('lets another user edit members only when the collection owner allows changes', function (
    bool $public,
) use (&$fixtures): void {
    $owner = get_user($GLOBALS['userref']);
    $username = 'collectioneditor' . generateRandomTestID(12);
    $editor = $fixtures->user($username);
    $outsider = $fixtures->user('collectionoutsider' . generateRandomTestID(12));
    $name = 'Editable collection ' . generateRandomTestID(12);
    $collection = $fixtures->collection($owner['ref'], $name);
    $existing = $fixtures->resource(1, 0);
    $incoming = $fixtures->resource(1, 0);

    save_collection($collection, $public ? ['public' => 1] : ['users' => $username]);
    get_collection($collection);
    expect(add_resource_to_collection($existing, $collection))->toBeTrue();

    setup_user(get_user($editor));
    if ($public) {
        expect(array_column(search_public_collections($name), 'ref'))->toContain($collection);
    } else {
        expect(array_column(get_user_collections($editor, $name), 'ref'))->toBe([$collection]);
        expect(array_column(get_user_collections($outsider, $name), 'ref'))->toBe([]);
    }
    expect(collection_readable($collection))->toBeTrue()
        ->and(add_resource_to_collection($incoming, $collection))->not->toBeTrue()
        ->and(remove_resource_from_collection($existing, $collection))->not->toBeTrue()
        ->and(get_collection_resources($collection))->toBe([$existing]);

    setup_user($owner);
    save_collection($collection, ['allow_changes' => 1]);
    expect(get_collection($collection)['allow_changes'])->toBe(1);
    if (!$public) {
        setup_user(get_user($outsider));
        expect(collection_readable($collection))->toBeFalse()
            ->and(add_resource_to_collection($incoming, $collection))->not->toBeTrue();
    }

    setup_user(get_user($editor));
    expect(add_resource_to_collection($incoming, $collection))->toBeTrue()
        ->and(remove_resource_from_collection($existing, $collection))->toBeTrue()
        ->and(get_collection_resources($collection))->toBe([$incoming]);
})->with([
    'public collection' => [true],
    'private collection shared with a named user' => [false],
]);

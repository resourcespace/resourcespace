<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'annotate_fields', 'annotate_text_adds_comment', 'annotate_public_view',
        'annotate_exclude_restypes', 'anonymous_login', 'k',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('deletes comment threads and associated annotations according to permissions', function () use (&$fixtures): void {
    include_once dirname(__DIR__, 2) . '/include/comment_functions.php';
    $saved = $GLOBALS['userpermissions'];
    $resource = $fixtures->resource(1, 0);
    $_GET = [];
    $_POST = ['resource_ref' => $resource, 'body' => 'Parent café'];
    comments_submit();
    $parent = ps_value('SELECT ref value FROM comment WHERE resource_ref = ?', ['i', $resource], 0);
    expect($parent > 0)->toBeTrue('Comment creation failed');
    $_POST = ['ref_parent' => $parent, 'resource_ref' => $resource, 'body' => 'Child reply'];
    comments_submit();
    $child = ps_value('SELECT ref value FROM comment WHERE ref_parent = ?', ['i', $parent], 0);
    expect($child > 0)->toBeTrue('Threaded reply not linked');
    $GLOBALS['userpermissions'] = [];
    $before = ps_query('SELECT * FROM comment WHERE resource_ref = ? ORDER BY ref', ['i', $resource]);
    expect(!hide_delete_comment($parent))->toBeTrue('Comment moderation allowed without permission');
    expect(ps_query('SELECT * FROM comment WHERE resource_ref = ? ORDER BY ref', ['i', $resource]) === $before)->toBeTrue('Rejected moderation changed comments');
    $GLOBALS['userpermissions'] = array_unique(array_merge($saved, ['o']));
    expect(hide_delete_comment($parent))->toBeTrue('Moderator cannot hide parent');
    expect(ps_value('SELECT hide value FROM comment WHERE ref = ?', ['i', $parent], 0) == 1)->toBeTrue('Parent with reply not hidden');
    expect(hide_delete_comment($child))->toBeTrue('Moderator cannot remove reply');
    expect(ps_value('SELECT COUNT(*) value FROM comment WHERE resource_ref = ?', ['i', $resource], -1) == 0)->toBeTrue('Empty hidden thread not cleaned');
    $field = $fixtures->field('Deletion annotation', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, 'deletionannotation');
    $GLOBALS['annotate_fields'] = [$field];
    $node = set_node(null, $field, 'Annotation tag', '', 10);
    $input = ['resource' => $resource, 'resource_type_field' => $field, 'tags' => [['ref' => $node, 'name' => 'Annotation tag']],
        'shapes' => [['type' => 'rect', 'geometry' => ['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.4]]]];
    $id = createAnnotation($input, []);
    expect($id > 0)->toBeTrue('Annotation fixture creation failed');
    $annotation = getAnnotation($id);
    $GLOBALS['userpermissions'] = [];
    expect(!deleteAnnotation($annotation, []))->toBeTrue('Annotation deletion permitted without access');
    expect(getAnnotation($id) !== [])->toBeTrue('Rejected annotation deletion changed row');
    $GLOBALS['userpermissions'] = $saved;
    expect(deleteAnnotation($annotation, []))->toBeTrue('Authorized annotation deletion failed');
    expect(getAnnotation($id) === []
        && ps_value('SELECT COUNT(*) value FROM annotation_node WHERE annotation = ?', ['i', $id], -1) == 0
        && ps_value('SELECT COUNT(*) value FROM resource_node WHERE resource = ? AND node = ?', ['i', $resource, 'i', $node], -1) == 0)->toBeTrue('Annotation deletion left tags or mappings');
});

it('persists annotation geometry, tag changes and their matching resource metadata', function () use (&$fixtures): void {
    $field = $fixtures->field('Annotation tag lifecycle', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $GLOBALS['annotate_fields'] = [$field];
    $resource = $fixtures->resource(1, 0);
    $tag_a = [];
    $tag_b = [];
    expect(get_node(set_node(null, $field, 'First tag', null, 10), $tag_a))->toBeTrue()
        ->and(get_node(set_node(null, $field, 'Second tag', null, 20), $tag_b))->toBeTrue();
    $input = [
        'resource' => $resource,
        'resource_type_field' => $field,
        'tags' => [],
        'text' => 'Metadata annotation cannot also be a comment',
        'shapes' => [['type' => 'rect', 'geometry' => ['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.4]]],
    ];

    $ref = createAnnotation($input, []);
    expect($ref)->toBeInt()->toBeGreaterThan(0);
    $geometry = getAnnotation($ref);
    expect((float) $geometry['x'])->toBe(0.1)
        ->and((float) $geometry['height'])->toBe(0.4)
        ->and(addAnnotationNodes($ref, []))->toBeFalse()
        ->and(getAnnotationTags($geometry))->toBeEmpty();

    expect(addAnnotationNodes($ref, [$tag_a, $tag_b]))->toBeTrue();
    $tags = getAnnotationTags($geometry);
    expect(array_column($tags, 'ref'))->toEqualCanonicalizing([$tag_a['ref'], $tag_b['ref']])
        ->and(array_column($tags, 'tag_searchable'))->toBe([null, null]);
    expect(get_resource_nodes($resource, $field))->toBeEmpty();

    $input['tags'] = [$tag_a];
    expect(updateAnnotation($input, []))->toBeFalse();
    $created_with_tag = createAnnotation($input, []);
    expect($created_with_tag)->toBeInt()->toBeGreaterThan(0)
        ->and(get_resource_nodes($resource, $field))->toBe([$tag_a['ref']])
        ->and(array_column(getAnnotationTags(getAnnotation($created_with_tag)), 'tag_searchable'))->toBe(['yes']);
    $input['ref'] = $created_with_tag;
    $input['user'] = (int) $GLOBALS['userref'];
    $input['tags'] = [$tag_b];
    $input['shapes'][0]['geometry']['width'] = 0.5;
    expect(updateAnnotation($input, []))->toBeTrue();
    $updated = getAnnotation($created_with_tag);
    expect((float) $updated['width'])->toBe(0.5)
        ->and(array_column(getAnnotationTags($updated), 'ref'))->toBe([$tag_b['ref']])
        ->and(get_resource_nodes($resource, $field))->toBe([$tag_b['ref']])
        ->and(ps_value('SELECT COUNT(*) AS value FROM comment WHERE annotation = ?', ['i', $created_with_tag], -1))
        ->toBe(0);
});

it('shows and removes the linked comment for a text annotation and preserves its original text', function () use (&$fixtures): void {
    require_once dirname(__DIR__, 2) . '/include/comment_functions.php';
    $GLOBALS['annotate_text_adds_comment'] = true;
    $GLOBALS['annotate_exclude_restypes'] = [];
    $GLOBALS['userpermissions'][] = 'o';
    $resource = $fixtures->resource(1, 0);
    $fixtures->onCleanup(static function () use ($resource): void {
        ps_query('DELETE FROM comment WHERE resource_ref = ?', ['i', $resource]);
    });
    $input = [
        'resource' => $resource,
        'resource_type_field' => 0,
        'text' => 'Text annotation original comment',
        'shapes' => [['type' => 'rect', 'geometry' => ['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.4]]],
    ];
    $ref = createAnnotation($input, []);
    expect($ref)->toBeInt()->toBeGreaterThan(0);
    $annotations = array_column(getAnnotoriousResourceAnnotations($resource), null, 'ref');
    expect($annotations[$ref]['text'])->toBe($input['text']);

    $input['ref'] = $ref;
    $input['user'] = (int) $GLOBALS['userref'];
    $input['text'] = 'Should not modify an existing comment';
    expect(updateAnnotation($input, []))->toBeFalse();
    expect(getResourceAnnotations($resource)[0]['text'])->toBe('Text annotation original comment');

    $comment = ps_value('SELECT ref AS value FROM comment WHERE annotation = ?', ['i', $ref], 0);
    expect($comment)->toBeInt()->toBeGreaterThan(0);
    expect(hide_delete_comment($comment))->toBeTrue()
        ->and(getResourceAnnotations($resource))->toBeEmpty()
        ->and(ps_value('SELECT COUNT(*) AS value FROM comment WHERE annotation = ?', ['i', $ref], -1))->toBe(0);
});

it('applies resource, field, author and share permissions to annotations', function (
    bool $as_text,
    string $author,
    array $remove_permissions,
    array $add_permissions,
    bool $text_enabled,
    string $context,
    bool $expected,
) use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $field = $fixtures->field('Annotation permission fixture', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST);
    $user = (int) $GLOBALS['userref'];
    $GLOBALS['annotate_text_adds_comment'] = $text_enabled;
    $GLOBALS['annotate_public_view'] = true;
    $GLOBALS['annotate_exclude_restypes'] = $context === 'excluded type' ? [1] : [];
    $GLOBALS['anonymous_login'] = '';
    $GLOBALS['userpermissions'] = array_values(array_diff(['a', 't', 'v', 's', 'e0', 'f*', 'o'], $remove_permissions));
    $GLOBALS['userpermissions'] = array_merge(
        $GLOBALS['userpermissions'],
        array_map(static fn (string $permission): string => $permission === 'deny field' ? 'F' . $field : $permission, $add_permissions)
    );
    $annotation = ['resource' => $resource, 'resource_type_field' => $as_text ? 0 : $field];
    if ($author !== 'new') {
        $annotation['user'] = $author === 'owner' ? $user : $user + 1;
    }
    $request = [];
    if ($context === 'external share') {
        $key = generate_resource_access_key($resource, $user, RESOURCE_ACCESS_FULL, '', '');
        expect($key)->toBeString()->not->toBeEmpty();
        $request['k'] = $key;
    }

    expect(annotationEditable($annotation, $request))->toBe($expected);
})->with([
    'admin can create metadata annotation' => [false, 'new', [], [], true, '', true],
    'editor can create metadata annotation' => [false, 'new', ['a'], [], true, '', true],
    'viewer cannot create metadata annotation' => [false, 'new', ['a', 'e0'], [], true, '', false],
    'viewer can create a comment annotation' => [true, 'new', ['a', 'e0', 'o'], [], true, '', true],
    'comment annotations can be disabled' => [true, 'new', [], [], false, '', false],
    'excluded types reject metadata annotations' => [false, 'new', [], [], true, 'excluded type', false],
    'excluded types reject existing comment edits' => [true, 'owner', [], [], true, 'excluded type', false],
    'editor can edit their own metadata annotation' => [false, 'owner', ['a'], [], true, '', true],
    'admin cannot edit a denied field' => [false, 'owner', [], ['deny field'], true, '', false],
    'editor cannot edit a denied field' => [false, 'owner', ['a'], ['deny field'], true, '', false],
    'editor must retain resource edit access' => [false, 'owner', ['a', 'e0'], [], true, '', false],
    'moderator can manage own comments' => [true, 'owner', [], [], true, '', true],
    'author without moderation cannot edit a comment annotation' => [true, 'owner', ['a', 'o'], [], true, '', false],
    'admin can manage another user metadata annotation' => [false, 'other', [], [], true, '', true],
    'editor cannot manage another user metadata annotation' => [false, 'other', ['a'], [], true, '', false],
    'admin without resource edit access cannot update metadata annotation' => [false, 'other', ['e0'], [], true, '', false],
    'admin with moderation can manage another user comment' => [true, 'other', [], [], true, '', true],
    'admin without moderation cannot manage another user comment' => [true, 'other', ['o'], [], true, '', false],
    'external shares cannot create metadata annotations' => [false, 'new', [], [], true, 'external share', false],
    'external shares cannot create comment annotations' => [true, 'new', [], [], true, 'external share', false],
    'external shares cannot manage metadata annotations' => [false, 'other', [], [], true, 'external share', false],
    'external shares cannot manage comment annotations' => [true, 'other', [], [], true, 'external share', false],
]);

it('renders the annotation control only for enabled and eligible resource views', function () use (
    &$fixtures,
): void {
    $user = $fixtures->user('annotation_view_' . generateRandomTestID(12), 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $resource = $fixtures->resource(1, 0, $user, file_extension: 'jpg');
    ps_query('UPDATE resource SET has_image = 1, preview_extension = ? WHERE ref = ?', [
        's', 'jpg', 'i', $resource,
    ]);
    foreach (['', 'pre'] as $size) {
        expect(copy(testFixture('Images/image_1.jpg'), get_resource_path($resource, true, $size, true, 'jpg')))
            ->toBeTrue();
    }
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url,
        'session_autologout' => false,
        'annotate_enabled' => true,
        'annotate_text_adds_comment' => true,
        'annotate_public_view' => false,
        'annotate_exclude_restypes' => [],
    ]);
    $path = '/pages/view.php?ref=' . $resource;
    $headers = $server->sessionHeaders($session);
    $has_control = static function (array $view, bool $visible): void {
        expect($view['status'])->toBe(200)
            ->and(str_contains($view['body'], 'id="previewimagelink"'))->toBeTrue()
            ->and(str_contains($view['body'], 'class="ToolsOptionLink AnnotationsOption"'))->toBe($visible);
    };
    $has_control($server->request($path, headers: $headers), true);

    $server->configure(['annotate_exclude_restypes' => [1]]);
    $has_control($server->request($path, headers: $headers), false);
    $server->configure(['annotate_exclude_restypes' => [], 'annotate_enabled' => false]);
    $has_control($server->request($path, headers: $headers), false);

    $server->configure(['annotate_enabled' => true]);
    $key = generate_resource_access_key($resource, $user, RESOURCE_ACCESS_FULL, '', '');
    expect($key)->toBeString()->not->toBeEmpty();
    $fixtures->onCleanup(static fn () => delete_resource_access_key($resource, $key));
    $has_control($server->request($path . '&k=' . urlencode($key), headers: $server->sessionHeaders('')), false);
});

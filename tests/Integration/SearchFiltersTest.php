<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'date_field', 'userpermissions', 'search_all_workflow_states', 'hidden_fields_cache', 'wildcard_always_applied',
        'keywords_remove_diacritics', 'keyword_relationships_one_way', 'related_keywords_cache', 'daterange_search',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('turns the selected date parts into a search and matches the correct dates', function (
    array $parts,
    string $query,
    array $expected,
) use (&$fixtures): void {
    $field = $fixtures->field('Basic search date', 0, FIELD_TYPE_DATE, 'basicdate' . generateRandomTestID(8));
    $GLOBALS['date_field'] = $field;
    $refs = [];
    $errors = [];
    foreach (['2017-07-15', '2017-07-02', '2017-04-08'] as $date) {
        $ref = $fixtures->resource(1, 0);
        expect(update_field($ref, $field, $date, $errors))->toBeTrue();
        $refs[] = $ref;
    }

    $_POST = $parts;
    $search = search_form_to_search_query([get_field($field)]);
    expect($search)->toBe($query)
        ->and(array_column(do_search($search), 'ref'))->toEqualCanonicalizing(
            array_map(static fn (int $index): int => $refs[$index], $expected)
        );
})->with([
    'a complete date' => [
        ['basicyear' => '2017', 'basicmonth' => '07', 'basicday' => '15'],
        'basicyear:2017, basicmonth:07, basicday:15', [0],
    ],
    'year and month' => [
        ['basicyear' => '2017', 'basicmonth' => '07'], 'basicyear:2017, basicmonth:07', [0, 1],
    ],
    'month and day' => [
        ['basicmonth' => '07', 'basicday' => '02'], 'basicmonth:07, basicday:02', [1],
    ],
    'year and day' => [
        ['basicyear' => '2017', 'basicday' => '08'], 'basicyear:2017, basicday:08', [2],
    ],
    'year' => [['basicyear' => '2017'], 'basicyear:2017', [0, 1, 2]],
    'month' => [['basicmonth' => '07'], 'basicmonth:07', [0, 1]],
    'day' => [['basicday' => '08'], 'basicday:08', [2]],
]);

it('distinguishes fixed-list field matches from the same word in free text', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(8);
    $name = 'searchcountry' . $suffix;
    $field = $fixtures->field('Test country', 0, FIELD_TYPE_CHECK_BOX_LIST, $name, true);
    $a = $fixtures->resource(1, 0);
    $b = $fixtures->resource(1, 0);
    $c = $fixtures->resource(1, 0);
    $summer = 'summerland' . $suffix;
    $spring = 'springland' . $suffix;
    $summer_node = set_node(null, $field, $summer, null, 10);
    $winter_node = set_node(null, $field, 'winterland' . $suffix, null, 10);
    $spring_node = set_node(null, $field, $spring, null, 10);
    add_resource_nodes($a, [$summer_node, $winter_node]);
    add_resource_nodes($b, [$summer_node]);
    add_resource_nodes($c, [$winter_node, $spring_node]);
    $errors = [];
    expect(update_field($c, $GLOBALS['view_title_field'], $summer, $errors))->toBeTrue();

    expect(array_column(do_search("{$name}:{$summer}"), 'ref'))->toEqualCanonicalizing([$a, $b])
        ->and(array_column(do_search("{$name}:{$spring}"), 'ref'))->toBe([$c])
        ->and(array_column(do_search($summer), 'ref'))->toEqualCanonicalizing([$a, $b, $c]);
});

it('filters a search by access and removes only visible non-matches from the selection', function () use (&$fixtures): void {
    $term = 'accesstest' . generateRandomTestID(12);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], $term);
    $a = $fixtures->resource(1, 0);
    $b = $fixtures->resource(1, 0);
    $c = $fixtures->resource(1, 0);
    foreach ([$a, $b, $c] as $ref) {
        $errors = [];
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue()
            ->and(add_resource_to_collection($ref, $collection))->toBeTrue();
    }
    expect(put_resource_data($b, ['access' => RESOURCE_ACCESS_RESTRICTED]))->toBeTrue()
        ->and(put_resource_data($c, ['access' => RESOURCE_ACCESS_RESTRICTED]))->toBeTrue();

    foreach ([null, 'bad input'] as $access) {
        expect(array_column(do_search($term, access: $access), 'ref'))->toEqualCanonicalizing([$a, $b, $c]);
    }
    $results = do_search($term, access: RESOURCE_ACCESS_RESTRICTED);
    expect(array_column($results, 'ref'))->toEqualCanonicalizing([$b, $c]);

    $matches = do_search($term, access: RESOURCE_ACCESS_RESTRICTED, return_refs_only: true, resource_collection: $collection);
    expect(array_column($matches, 'ref'))->toEqualCanonicalizing([$b, $c]);
    $state = reconcile_selection_collection($collection, $matches);
    expect($state['empty'])->toBeFalse()
        ->and(array_values($state['removed']))->toBe([$a])
        ->and(get_collection_resources($collection))->toEqualCanonicalizing([$b, $c]);

    expect(put_resource_data($c, ['access' => RESOURCE_ACCESS_CONFIDENTIAL]))->toBeTrue();
    $GLOBALS['userpermissions'] = ['s', 'g'];
    $GLOBALS['search_all_workflow_states'] = false;
    $state = reconcile_selection_collection($collection, []);
    expect($state['empty'])->toBeTrue()->and(get_collection_resources($collection))->toBe([$c]);
});

it('counts distinct custom access combinations without counting duplicate joins', function () use (&$fixtures): void {
    $term = 'accessjoins' . generateRandomTestID(12);
    $errors = [];
    for ($i = 0; $i < 3; $i++) {
        $ref = $fixtures->resource(1, 0);
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        $first ??= $ref;
    }
    $fixtures->onCleanup(static function () use ($first): void {
        ps_query('DELETE FROM resource_custom_access WHERE resource = ?', ['i', $first]);
    });
    foreach ([RESOURCE_ACCESS_FULL, RESOURCE_ACCESS_FULL, RESOURCE_ACCESS_RESTRICTED] as $level) {
        ps_query('INSERT INTO resource_custom_access (resource, usergroup, access) VALUES (?, ?, ?)', [
            'i', $first, 'i', $GLOBALS['usergroup'], 'i', $level,
        ]);
        ps_query('INSERT INTO resource_custom_access (resource, user, access) VALUES (?, ?, ?)', [
            'i', $first, 'i', $GLOBALS['userref'], 'i', $level,
        ]);
    }
    $GLOBALS['userpermissions'] = ['s', 'g', 'f*'];
    expect(do_search($term, fetchrows: [0, 1])['total'])->toBe(6);
});

it('omits joined metadata fields the user cannot view from search results', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $country = get_nodes(3)[0]['ref'];
    add_resource_nodes($resource, [$country]);
    $errors = [];
    expect(update_field($resource, 8, 'Joined title', $errors))->toBeTrue();
    expect(update_resource_field_column($resource, 3, 'Joined country'))->toBeTrue();

    $result = do_search("!resource{$resource}")[0];
    expect($result)->toHaveKeys(['field8', 'field3']);
    $GLOBALS['userpermissions'][] = 'f-3';
    $GLOBALS['hidden_fields_cache'] = null;
    $result = do_search("!resource{$resource}")[0];
    expect($result['ref'])->toBe($resource)
        ->and($result)->toHaveKey('field8')
        ->and(array_key_exists('field3', $result))->toBeFalse();
});

it('matches keyword prefixes automatically only when configured', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(8);
    $node_prefix = 'nodewild' . $suffix;
    $text_prefix = 'textwild' . $suffix;
    $node_resource = $fixtures->resource(1, 0);
    $text_resource = $fixtures->resource(1, 0);
    $field = $fixtures->field('Wildcard options', 0, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $node = set_node(null, $field, $node_prefix . 'long', null, 10);
    add_resource_nodes($node_resource, [$node]);
    $errors = [];
    expect(update_field($text_resource, $GLOBALS['view_title_field'], $text_prefix . 'long', $errors))->toBeTrue();

    foreach ([$node_prefix => $node_resource, $text_prefix => $text_resource] as $prefix => $ref) {
        $GLOBALS['wildcard_always_applied'] = false;
        expect(do_search($prefix))->not->toBeArray();
        $GLOBALS['wildcard_always_applied'] = true;
        expect(array_column(do_search($prefix), 'ref'))->toBe([$ref]);
    }
});

it('excludes downloaded resources and combines the no-download search with a node filter', function () use (&$fixtures): void {
    $downloaded = $fixtures->resource(1, 0);
    $without_node = $fixtures->resource(1, 0);
    $with_node = $fixtures->resource(1, 0);
    $other_with_node = $fixtures->resource(1, 0);
    $field = $fixtures->field('Download search', 0, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $node = set_node(null, $field, 'Downloaded sample', null, 10);
    foreach ([$downloaded, $with_node, $other_with_node] as $ref) {
        add_resource_nodes($ref, [$node]);
    }
    daily_stat('Resource download', $downloaded);
    $fixtures->onCleanup(static function () use ($downloaded): void {
        ps_query('DELETE FROM daily_stat WHERE object_ref = ? AND activity_type = ?', ['i', $downloaded, 's', 'Resource download']);
    });

    $result = array_column(do_search('!nodownloads'), 'ref');
    expect($result)->toContain($without_node, $with_node, $other_with_node)->not->toContain($downloaded);
    $result = array_column(do_search('!nodownloads ' . NODE_TOKEN_PREFIX . $node), 'ref');
    expect($result)->toEqualCanonicalizing([$with_node, $other_with_node]);
});

it('searches multiple workflow states while respecting visibility of other users’ submissions', function () use (&$fixtures): void {
    $contributor = $fixtures->user('workflowuser' . generateRandomTestID(12));
    $term = 'workflowscope' . generateRandomTestID(12);
    $refs = [];
    foreach ([0, 1, -1, -2] as $state) {
        $refs[$state] = $fixtures->resource(1, $state, $contributor);
        $errors = [];
        expect(update_field($refs[$state], $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
    }

    $GLOBALS['userpermissions'] = ['s', 'g', 'f*'];
    $GLOBALS['search_all_workflow_states'] = false;
    expect(array_column(do_search($term), 'ref'))->toBe([$refs[0]]);
    $GLOBALS['search_all_workflow_states'] = true;
    expect(array_column(do_search($term), 'ref'))->toEqualCanonicalizing([$refs[0], $refs[1]]);
});

it('applies type and workflow permissions to search and editable results', function (
    array $permissions,
    array $visible,
    array $editable,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $author = $fixtures->user('searchowner' . $id);
    $field = $fixtures->field('Search visibility token', 0, FIELD_TYPE_CHECK_BOX_LIST, 'searchvis' . $id, true);
    $node = set_node(null, $field, 'Search option ' . $id, null, 10);
    $refs = [];
    foreach ([[1, 0], [1, -1], [2, 0], [2, -1], [3, 0]] as [$type, $state]) {
        $ref = $fixtures->resource($type, $state, $author);
        add_resource_nodes($ref, [$node]);
        $refs[] = $ref;
    }

    $GLOBALS['userpermissions'] = $permissions;
    $GLOBALS['search_all_workflow_states'] = false;
    $query = NODE_TOKEN_PREFIX . $node;
    $active = [$refs[0], $refs[2], $refs[4]];
    expect(array_column(do_search($query), 'ref'))->toEqualCanonicalizing($active);
    expect(array_column(do_search($query, editable_only: true), 'ref'))->toEqualCanonicalizing($active);
    expect(array_column(do_search($query, archive: '-1,0'), 'ref'))
        ->toEqualCanonicalizing(array_map(static fn (int $index): int => $refs[$index], $visible));
    expect(array_column(do_search($query, archive: '-1,0', editable_only: true), 'ref'))
        ->toEqualCanonicalizing(array_map(static fn (int $index): int => $refs[$index], $editable));
})->with([
    'administrator' => [['v', 't', 's', 'e0', 'e-1', 'f*'], [0, 1, 2, 3, 4], [0, 1, 2, 3, 4]],
    'forced type edit access' => [['ert1', 's', 'e0', 'e-1', 'f*'], [0, 1, 2, 4], [0, 1, 2, 4]],
    'blocked workflow state' => [['ert1', 'e0', 'z-1', 'f*'], [0, 2, 4], [0, 2, 4]],
    'restricted workflow state' => [['ert1', 'e0', 'e-1', 'rws-1', 'f*'], [0, 1, 2, 4], [0, 1, 2, 4]],
    'general editor' => [['e0', 'e-1', 'f*'], [0, 2, 4], [0, 2, 4]],
    'general editor blocked workflow' => [['e0', 'e-1', 'z-1', 'f*'], [0, 2, 4], [0, 2, 4]],
    'general editor restricted workflow' => [['e0', 'e-1', 'rws-1', 'f*'], [0, 2, 4], [0, 2, 4]],
]);

it('finds indexed multilingual text with and without supported diacritics', function (
    string $text,
    array $queries,
) use (&$fixtures): void {
    $GLOBALS['keywords_remove_diacritics'] = true;
    $ref = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($ref, $GLOBALS['view_title_field'], $text, $errors))->toBeTrue();
    foreach ($queries as $query) {
        expect(array_column(do_search($query), 'ref'))->toContain($ref);
    }
})->with([
    'German umlaut' => ['Zwölf Boxkämpfer jagten Eva', ['Boxkämpfer', 'Boxkampfer']],
    'German sharp s' => ['Größeren Image', ['Größeren', 'groseren']],
    'long German compound' => ['Heizölrückstoßabdämpfung', ['Heizölrückstoßabdämpfung']],
    'Japanese' => ["いろはにほへとちりぬるを\nわかよたれそつねならむ", ['わかよたれそつねならむ']],
]);

it('indexes metadata content without indexing HTML element and attribute names', function (
    int $type,
    bool $indexes_url,
) use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $term = 'openasset' . $suffix;
    $url_term = 'assetlink' . $suffix;
    $field = $fixtures->field('Markup search field', 0, $type, 'markup' . $suffix, true);
    $resource = $fixtures->resource(1, 0);
    $markup = "<div><a href='https://{$url_term}.example'>{$term} management</a></div>";
    if ($type === FIELD_TYPE_CHECK_BOX_LIST) {
        add_resource_nodes($resource, [set_node(null, $field, $markup, null, 10)]);
    } else {
        $errors = [];
        expect(update_field($resource, $field, $markup, $errors))->toBeTrue();
    }

    expect(array_column(do_search("{$term} management"), 'ref'))->toBe([$resource]);
    if ($indexes_url) {
        expect(array_column(do_search($url_term), 'ref'))->toBe([$resource]);
    }
    foreach (['div', 'href'] as $syntax) {
        expect(array_column((array) do_search($syntax), 'ref'))->not->toContain($resource);
    }
})->with([
    'formatted text' => [FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE, true],
    'fixed option' => [FIELD_TYPE_CHECK_BOX_LIST, true],
    'multiline text' => [FIELD_TYPE_TEXT_BOX_MULTI_LINE, false],
]);

it('matches quoted phrases across punctuation only when their words are adjacent', function (
    int $type,
) use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $first = 'romeo' . $suffix;
    $field = $fixtures->field('Phrase search field', 0, $type, 'phrase' . $suffix, true);
    $resource = $fixtures->resource(1, 0);
    $value = "\"{$first} lima foxtrot.\" said the author";
    if ($type === FIELD_TYPE_CHECK_BOX_LIST) {
        add_resource_nodes($resource, [set_node(null, $field, $value, null, 10)]);
    } else {
        $errors = [];
        expect(update_field($resource, $field, $value, $errors))->toBeTrue();
    }

    foreach (["lima {$first}", "\"{$first} lima\"", "\"{$first} lima foxtrot said the author\""] as $query) {
        expect(array_column(do_search($query), 'ref'))->toBe([$resource]);
    }
    expect(array_column((array) do_search("\"{$first} foxtrot\""), 'ref'))->toBeEmpty();
})->with([
    'multiline text' => FIELD_TYPE_TEXT_BOX_MULTI_LINE,
    'fixed option' => FIELD_TYPE_CHECK_BOX_LIST,
]);

it('expands related keyword searches according to the configured relationship direction', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $original = 'sourceword' . $id;
    $synonym = 'synonymword' . $id;
    $field = $fixtures->field('Related keyword index', 0, FIELD_TYPE_CHECK_BOX_LIST, 'relatedwords' . $id, true);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $fixtures->onCleanup(static fn () => save_related_keywords($original, ''));

    add_resource_nodes($first, [set_node(null, $field, $original, null, 10)]);
    $GLOBALS['keyword_relationships_one_way'] = false;
    $GLOBALS['related_keywords_cache'] = [];
    expect(save_related_keywords($original, $synonym))->toBeTrue();
    expect(array_column(do_search($synonym), 'ref'))->toBe([$first]);

    add_resource_nodes($second, [set_node(null, $field, $synonym, null, 20)]);
    expect(array_column(do_search($original), 'ref'))->toEqualCanonicalizing([$first, $second]);
    $GLOBALS['keyword_relationships_one_way'] = true;
    $GLOBALS['related_keywords_cache'] = [];
    expect(array_column(do_search($synonym), 'ref'))->toBe([$second])
        ->and(array_column(do_search($original), 'ref'))->toEqualCanonicalizing([$first, $second]);
});

it('distinguishes the ordinary keyword index from full-text phrases and exclusions', function () use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $field = $fixtures->field('Full text search field', 0, FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE, 'longtext' . $id, true);
    $first = $fixtures->resource(1, 0);
    $phrase = $fixtures->resource(1, 0);
    $third = $fixtures->resource(1, 0);
    $indexed = 'start' . $id;
    $fulltext = 'end' . $id;
    $exclude = 'exclude' . $id;
    $words = 'chapter' . $id . ' closing' . $id;
    $padding = str_repeat(' ordinary long text', 50);
    $errors = [];
    foreach ([
        $first => "{$indexed}{$padding} {$fulltext}",
        $phrase => "{$padding} {$indexed} {$words}",
        $third => "{$indexed}{$padding} {$fulltext}",
    ] as $resource => $text) {
        expect(update_field($resource, $field, $text, $errors))->toBeTrue();
    }
    expect(update_field($first, $GLOBALS['view_title_field'], $exclude, $errors))->toBeTrue();

    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    $full = static fn (string $query): string => '"' . FULLTEXT_SEARCH_PREFIX . ':' . $query . '"';
    $quoted = FULLTEXT_SEARCH_QUOTES_PLACEHOLDER . $words . FULLTEXT_SEARCH_QUOTES_PLACEHOLDER;
    expect($find($indexed))->toEqualCanonicalizing([$first, $third])
        ->and($find($fulltext))->toBeEmpty()
        ->and($find($full($fulltext)))->toEqualCanonicalizing([$first, $third])
        ->and($find($full($quoted)))->toBe([$phrase])
        ->and($find($full($indexed)))->toEqualCanonicalizing([$first, $phrase, $third])
        ->and($find($full("+{$indexed} -{$quoted}")))->toEqualCanonicalizing([$first, $third])
        ->and($find($full($fulltext) . " -{$exclude}"))->toBe([$third]);
});

it('includes only searchable metadata and inserts the designated date once in its type group', function () use (&$fixtures): void {
    $GLOBALS['daterange_search'] = true;
    $id = generateRandomTestID(10);
    $eligible_photo = $fixtures->field('Searchable photo text', 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'photo' . $id, true);
    $disabled_photo = $fixtures->field('Non-searchable photo option', 1, FIELD_TYPE_RADIO_BUTTONS, 'disabled' . $id, false);
    $video_date = $fixtures->field('Non-searchable video date', 3, FIELD_TYPE_DATE, 'date' . $id, false);
    $video_option = $fixtures->field('Searchable video option', 3, FIELD_TYPE_CHECK_BOX_LIST, 'video' . $id, true);
    $global_date = $fixtures->field('Global fallback date', 0, FIELD_TYPE_DATE, 'global' . $id, false);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['advanced_search' => true, 'order_by' => true]);
    foreach ([
        [$eligible_photo, 1, 10], [$disabled_photo, 0, 20], [$video_date, 0, 30],
        [$video_option, 1, 40], [$global_date, 0, 50],
    ] as [$ref, $advanced, $order]) {
        expect(save_resource_type_field($ref, $columns, ['advanced_search' => $advanced, 'order_by' => $order]))->toBeTrue();
    }

    $fixture_refs = [$eligible_photo, $disabled_photo, $video_date, $video_option, $global_date];
    $advanced_refs = static fn (): array =>
        array_values(array_intersect(array_map('intval', array_column(get_advanced_search_fields(), 'ref')), $fixture_refs));
    $GLOBALS['date_field'] = $global_date;
    expect($advanced_refs())->toBe([$global_date, $eligible_photo, $video_option]);

    $GLOBALS['date_field'] = $video_date;
    $insertion = $advanced_refs();
    expect($insertion)->toEqualCanonicalizing([$eligible_photo, $video_date, $video_option])
        ->and(array_search($video_date, $insertion, true))->toBeLessThan(array_search($video_option, $insertion, true));
    $columns += array_intersect_key(get_resource_type_field_columns(), ['keywords_index' => true]);
    expect(save_resource_type_field($video_date, $columns, [
        'advanced_search' => 1, 'order_by' => 30, 'keywords_index' => 1,
    ]))->toBeTrue();
    expect($advanced_refs())->toBe([$eligible_photo, $video_date, $video_option]);
});

it('matches orientation using original dimensions and handles missing height', function () use (&$fixtures): void {
    $term = 'orientation' . generateRandomTestID(12);
    $refs = [];
    $errors = [];
    foreach ([[100, 200], [300, 150], [100, 100], [100, null]] as [$width, $height]) {
        $ref = $fixtures->resource(1, 0);
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        ps_query(
            'INSERT INTO resource_dimensions (resource, width, height) VALUES (?, ?, ?)',
            ['i', $ref, 'i', $width, 'i', $height]
        );
        $refs[] = $ref;
    }
    [$portrait, $landscape, $square, $missing] = $refs;
    $find = static fn (string $orientation): array =>
        array_column(do_search("!propertiesorientation:{$orientation} ,{$term}"), 'ref');
    expect($find('portrait'))->toBe([$portrait])
        ->and($find('square'))->toBe([$square])
        ->and($find('landscape'))->toEqualCanonicalizing([$landscape, $missing]);
});

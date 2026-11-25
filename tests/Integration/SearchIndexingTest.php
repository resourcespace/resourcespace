<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'data_joins', 'sort_fields', 'stemming', 'wildcard_always_applied', 'query_cache_enabled',
        'config_separators', 'keyword_allowed_symbols',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('sorts dotted identifiers by their natural segments in both directions', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $name = 'dotnotation' . $suffix;
    $field = $fixtures->field('Dotted identifiers', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $name);
    $columns = array_intersect_key(get_resource_type_field_columns(), ['sort_method' => true]);
    expect(save_resource_type_field($field, $columns, ['sort_method' => '1']))->toBeTrue()
        ->and((int) get_resource_type_field($field)['sort_method'])->toBe(1);
    $GLOBALS['data_joins'][] = $field;
    $GLOBALS['sort_fields'][] = $field;

    $term = 'dottedresource' . $suffix;
    $resources = [];
    $errors = [];
    foreach (['ab123.456.789', '4539.234.01', '123.342.98', 'ab23.456.789', '123.42.98', '8.99.99', ''] as $identifier) {
        $ref = $fixtures->resource(1, 0);
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue()
            ->and(update_field($ref, $field, $identifier, $errors))->toBeTrue();
        $resources[] = $ref;
    }
    update_fieldx($field);
    $find = static fn (string $order, string $direction): array =>
        array_column(do_search($term, order_by: $order, sort: $direction), 'ref');
    $natural = array_map(static fn (int $i): int => $resources[$i], [6, 3, 0, 5, 4, 2, 1]);
    expect($find('resourceid', 'asc'))->toBe($resources)
        ->and($find('field' . $field, 'asc'))->toBe($natural)
        ->and($find('field' . $field, 'desc'))->toBe(array_reverse($natural));
});

it('finds newly edited metadata and updated collection membership with the query cache disabled', function () use (
    &$fixtures,
): void {
    $GLOBALS['query_cache_enabled'] = false;
    $resource = $fixtures->resource(1, 0);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Uncached collection ' . generateRandomTestID(12));
    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    $before = 'uncachedold' . generateRandomTestID(12);
    $after = 'uncachednew' . generateRandomTestID(12);
    $collection_query = '!collection' . $collection;
    $errors = [];

    expect(update_field($resource, $GLOBALS['view_title_field'], $before, $errors))->toBeTrue()
        ->and($find($before))->toBe([$resource])
        ->and($find($after))->toBeEmpty()
        ->and($find($collection_query))->toBeEmpty();
    expect(update_field($resource, $GLOBALS['view_title_field'], $after, $errors))->toBeTrue()
        ->and(add_resource_to_collection($resource, $collection))->toBeTrue()
        ->and($find($before))->toBeEmpty()
        ->and($find($after))->toBe([$resource])
        ->and($find($collection_query))->toBe([$resource]);
});

it('matches stemmed words, original forms and case across fixed and text metadata', function () use (&$fixtures): void {
    $GLOBALS['stemming'] = true;
    $field = $fixtures->field('Stemmed vocabulary', 0, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $original_option = set_node(null, $field, 'Animal', null, 10);
    $foxes = set_node(null, $field, 'foxes', null, 20);
    $dancing = set_node(null, $field, 'dancing', null, 30);
    $vocabulary_resource = $fixtures->resource(1, 0);
    $text_resource = $fixtures->resource(1, 0);
    $fox_resource = $fixtures->resource(1, 0);
    $dance_resource = $fixtures->resource(1, 0);
    add_resource_nodes($vocabulary_resource, [$original_option]);
    add_resource_nodes($fox_resource, [$foxes]);
    add_resource_nodes($dance_resource, [$dancing]);
    $errors = [];
    expect(update_field($text_resource, 'caption', 'Animal', $errors))->toBeTrue();

    foreach ([
        'fox' => [$fox_resource],
        'dance' => [$dance_resource],
        'animal' => [$vocabulary_resource, $text_resource],
        'Animal' => [$vocabulary_resource, $text_resource],
        'ANIMAL' => [$vocabulary_resource, $text_resource],
        'Animal;missingstemmedword' => [$vocabulary_resource, $text_resource],
        'caption:Animal' => [$text_resource],
        '-Animal' => [$fox_resource, $dance_resource],
    ] as $query => $expected) {
        $results = array_column((array) do_search($query), 'ref');
        $scope = [$vocabulary_resource, $text_resource, $fox_resource, $dance_resource];
        expect(array_values(array_intersect($results, $scope)))->toEqualCanonicalizing($expected);
    }

    $uppercase = set_node(null, $field, 'Uppercase token', null, 40);
    add_node_keyword($uppercase, 'DANCING', 0);
    $keywords = ps_array(
        'SELECT k.keyword AS value FROM node_keyword nk JOIN keyword k ON k.ref = nk.keyword WHERE nk.node = ?',
        ['i', $uppercase]
    );
    expect($keywords)->toContain('danc', 'dancing');
});

it('matches explicit wildcards and punctuation in whole and field-specific search terms', function (
    string $search,
    string $value,
    string $field = 'title',
    bool $expected = true,
) use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($resource, $field, $value, $errors))->toBeTrue();
    foreach ([false, true] as $always) {
        $GLOBALS['wildcard_always_applied'] = $always;
        $refs = array_column((array) do_search($search), 'ref');
        expect(in_array($resource, $refs, true))->toBe($expected);
    }
})->with([
    'plain word' => ['plant', 'plant'],
    'UUID-style prefix' => ['a3ewd44a43-a80eha-a464t0-aba24r*', 'a3ewd44a43-a80eha-a464t0-aba24r-acf2b011a0763w'],
    'extension and underscore' => ['ab123_*.jpg', 'ab123_junk.jpg', 'originalfilename'],
    'wildcard scoped to a field' => ['title:book*', 'booking form'],
    'exact filename' => ['originalfilename:dog_photo-1.jpg', 'dog_photo-1.jpg', 'originalfilename'],
    'dot in title' => ['title:pumpkin.patch', 'pumpkin.patch'],
    'hyphens in title' => ['title:up-at-em', 'up-at-em'],
    'date prefix in title' => ['title:2025.04*', '2025.04.13'],
    'dotted numeric prefix' => ['123.1*', '123.124.125'],
    'incomplete numeric sequence' => ['1998.327.370.*', '1998', 'title', false],
    'different numeric sequence' => ['2010.69*', '2010.701', 'title', false],
    'matching numeric sequence' => ['2010.69*', '2010.690.3.450'],
    'leading and trailing wildcards' => ['*98.327.370.*', '1998.327.370.456'],
    'internal wildcard' => ['bing.*.boom', 'bing.bong.BOOM'],
    'stopword prefix' => ['will.*', 'will.i.am'],
    'multiple field alternatives' => ['title:123.45*;345.12*', '123.456'],
    'short numeric token' => ['42.*', '42.48'],
    'text containing a short word' => ['look up above', 'look up above', 'caption'],
]);

it('distinguishes keywords containing an allowed symbol from their plain equivalents', function (
    string $symbol,
    bool $allow_symbol,
) use (&$fixtures): void {
    $GLOBALS['config_separators'] = array_diff($GLOBALS['config_separators'], [$symbol]);
    $GLOBALS['keyword_allowed_symbols'] = $allow_symbol ? [$symbol] : [];
    $GLOBALS['stemming'] = false;
    $GLOBALS['wildcard_always_applied'] = false;

    $prefix = 'identifier' . generateRandomTestID(12);
    $with_symbol = $prefix . $symbol . 'suffix';
    $without_symbol = $prefix . 'suffix';
    $symbol_resource = $fixtures->resource(1, 0);
    $plain_resource = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($symbol_resource, 'title', $with_symbol, $errors))->toBeTrue()
        ->and(update_field($plain_resource, 'title', $without_symbol, $errors))->toBeTrue();

    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    $symbol_matches = $allow_symbol ? [$symbol_resource] : [$symbol_resource, $plain_resource];
    $plain_matches = $allow_symbol ? [$plain_resource] : [$symbol_resource, $plain_resource];

    expect($find($with_symbol))->toEqualCanonicalizing($symbol_matches)
        ->and($find($without_symbol))->toEqualCanonicalizing($plain_matches)
        ->and($find('title:' . $with_symbol))->toEqualCanonicalizing($symbol_matches)
        ->and($find('title:' . $without_symbol))->toEqualCanonicalizing($plain_matches);
})->with([
    'allowed hyphen' => ['-', true],
    'unlisted hyphen' => ['-', false],
    'allowed ampersand' => ['&', true],
    'unlisted ampersand' => ['&', false],
]);

it('requires both wildcard terms while searching across text and fixed-list metadata', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $first_prefix = 'sam' . $suffix;
    $second_prefix = 'super' . $suffix;
    $field = $fixtures->field('Wildcard combination', 0, FIELD_TYPE_DROP_DOWN_LIST, '', true);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $third = $fixtures->resource(1, 0);
    $first_option = set_node(null, $field, $first_prefix . 'baland', null, 10);
    $second_option = set_node(null, $field, $second_prefix . 'land', null, 20);
    add_resource_nodes($first, [$first_option]);
    add_resource_nodes($second, [$second_option]);
    $errors = [];
    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    expect($find($first_prefix . '*'))->toBe([$first])
        ->and($find($second_prefix . '*'))->toBe([$second]);
    expect(update_field($third, 'title', $second_prefix . 'marine', $errors))->toBeTrue();
    expect($find($second_prefix . '*'))->toEqualCanonicalizing([$second, $third])
        ->and($find("{$first_prefix}* {$second_prefix}*"))->toBeEmpty();

    expect(update_field($first, 'title', $second_prefix . 'cilious', $errors))->toBeTrue()
        ->and(update_field($second, 'title', $first_prefix . 'uel', $errors))->toBeTrue();
    add_resource_nodes($third, [$first_option]);
    expect($find("{$first_prefix}* {$second_prefix}*"))->toEqualCanonicalizing([$first, $second, $third]);
});

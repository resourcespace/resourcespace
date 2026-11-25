<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'stemming', 'resource_field_verbatim_keyword_regex', 'userpermissions',
        'hidden_fields_cache', 'noadd', 'use_refine_searchstring',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('scopes text phrases and alternatives to the named field and applies exclusions', function () use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $name = 'dispatch' . $id;
    $field = $fixtures->field('Dispatch text', 0, FIELD_TYPE_TEXT_BOX_MULTI_LINE, $name, true);
    $options = $fixtures->field('Dispatch options', 0, FIELD_TYPE_CHECK_BOX_LIST, 'opts' . $id, true);
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $third = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($first, $field, 'Launch party', $errors))->toBeTrue()
        ->and(update_field($second, $field, 'Book signing', $errors))->toBeTrue()
        ->and(update_field($third, $field, 'Ship launch', $errors))->toBeTrue();
    add_resource_nodes($second, [set_node(null, $options, 'Launch', null, 10)]);

    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    expect($find('"' . $name . ':launch party"'))->toBe([$first])
        ->and($find($name . ':launch'))->toEqualCanonicalizing([$first, $third])
        ->and($find($name . ':launch, -ship'))->toBe([$first])
        ->and($find($name . ':book;ship'))->toEqualCanonicalizing([$second, $third])
        ->and($find('book;ship'))->toContain($second, $third);
});

it('combines indexed words and literal alternatives through the query and form pipelines', function (
    bool $stemming,
    bool $verbatim,
) use (&$fixtures): void {
    $GLOBALS['stemming'] = $stemming;
    $GLOBALS['use_refine_searchstring'] = true;
    $GLOBALS['noadd'][] = 'reviewignored';
    $name = 'alternatives' . generateRandomTestID(10);
    $field = $fixtures->field('Indexed and literal alternatives', 0, FIELD_TYPE_TEXT_BOX_MULTI_LINE, $name, true);
    if ($verbatim) {
        $GLOBALS['resource_field_verbatim_keyword_regex'][$field] = '/\S+/u';
    }

    $refs = [];
    $errors = [];
    foreach ([
        'foxes', '12.34', 'C++', '.alpha', 'alpha.', "ABC\u{00E9}DEF.123", 'ABC.DEF.123',
        '.alphax', 'xalpha.', '123x456', '123.4567', 'the', 'the.123', 'the 123',
        'reviewignored', 'reviewignored.123', 'www', 'ABC/DEF', 'ABC DEF', '123.456', '234.567', '123.567',
    ] as $index => $text) {
        $ref = $fixtures->resource(1, 0);
        expect(update_field($ref, $field, $text, $errors))->toBeTrue();
        $refs[$index] = $ref;
    }
    expect(update_field($refs[0], $GLOBALS['view_title_field'], 'revieworrequired', $errors))->toBeTrue();

    $cases = [
        ['fox;missing', $stemming ? [0] : []],
        ['fox;12.34', $stemming ? [0, 1] : [1]],
        ['12.34;fox', $stemming ? [0, 1] : [1]],
        ['C++;missing', [2]],
        ['.alpha;missing', [3]],
        ['alpha.;missing', [4]],
        ["ABC\u{00E9}DEF.123;xy", [5]],
        ['C++;.alpha;alpha.', [2, 3, 4]],
        ['C++;.alpha;', [2, 3]],
        ['fox;12.34 title:revieworrequired', $stemming ? [0] : []],
        ['the;12.34', [1]],
        ['the;missing', []],
        ['the;and', []],
        ['the;and title:revieworrequired', []],
        ['THE;12.34', [1]],
        ['the.123;missing', [12]],
        ['reviewignored;12.34', [1]],
        ['reviewignored.123;missing', [15]],
        ['www;12.34', [1, 16]],
        ['ABC/DEF;the', [17]],
        ['ABC/DEF;', [17]],
        ['123.456;234.567', [19, 20]],
        ['123.456;missing', [19]],
        ['234.456;missing', []],
        ['12.34;234.567', [1, 20]],
    ];
    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    foreach ($cases as [$query, $indexes]) {
        $expected = array_map(static fn (int $index): int => $refs[$index], $indexes);
        expect($find(refine_searchstring($name . ':' . $query)))->toEqualCanonicalizing($expected, $query);
        $actual = array_values(array_intersect($find(refine_searchstring($query)), $refs));
        expect($actual)->toEqualCanonicalizing($expected, $query);
    }
    $post = $_POST;
    try {
        $_POST = ['search' => '123.456;234.567'];
        $form = search_form_to_search_query([], true);
    } finally {
        $_POST = $post;
    }
    expect(array_values(array_intersect($find($form), $refs)))->toEqualCanonicalizing([$refs[19], $refs[20]])
        ->and($find('"' . $name . ':123.456;234.567"'))->toEqualCanonicalizing([$refs[19], $refs[20]]);

    $GLOBALS['userpermissions'][] = 'f-' . $field;
    $GLOBALS['hidden_fields_cache'] = null;
    expect(do_search($name . ':fox;12.34'))->toBeFalse()
        ->and(do_search('"' . $name . ':123.456;234.567"'))->toBeFalse()
        ->and(array_intersect($find('fox;12.34'), $refs))->toBeEmpty();
})->with([
    'ordinary index' => [false, false],
    'verbatim index' => [false, true],
    'ordinary index with stemming' => [true, false],
    'verbatim index with stemming' => [true, true],
]);

it('matches literal alternatives at exact word boundaries in the database', function (
    string $query,
    string $value,
    int $expected,
): void {
    $matched = ps_value(
        'SELECT ? RLIKE ? AS value',
        ['s', $value, 's', prepare_regex_search_string($query)],
        0
    );
    expect((int) $matched)->toBe($expected);
})->with([
    'plus signs' => ['C++;missing', 'C++', 1],
    'leading punctuation' => ['.alpha;missing', '.alpha', 1],
    'trailing punctuation' => ['alpha.;missing', 'alpha.', 1],
    'longer suffix' => ['.alpha;missing', '.alphax', 0],
    'longer prefix' => ['alpha.;missing', 'xalpha.', 0],
    'substituted separator' => ['123.456;234.567', '123x456', 0],
    'trailing empty alternative' => ['C++;missing;', 'unrelated', 0],
    'only empty alternatives' => [';;', 'unrelated', 0],
    'multibyte literal' => ["ABC\u{00E9}DEF.123;missing", "ABC\u{00E9}DEF.123", 1],
]);

<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions', 'language', 'defaultlanguage', 'hidden_fields_cache']);
    $GLOBALS['userpermissions'] = ['f*'];
    $GLOBALS['language'] = 'fr';
    $GLOBALS['defaultlanguage'] = 'en';
    $GLOBALS['hidden_fields_cache'] = null;
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

$preprocess = static fn (string $search): string => resolve_field_search_node_names(
    $search,
    split_keywords($search, false, false, false, false, true)
);

it('resolves option names in search terms using field visibility, translations and tree order', function () use (
    &$fixtures,
    $preprocess,
): void {
    $flat = $fixtures->field('Search options', 0, FIELD_TYPE_CHECK_BOX_LIST, 'searchoptions');
    $first = set_node(null, $flat, 'Alpha', null, 10);
    $second = set_node(null, $flat, 'Beta', null, 20);
    $translated = set_node(null, $flat, '~en:English~fr:Francais', null, 30);
    $default = set_node(null, $flat, '~en:Default~de:Deutsch', null, 40);
    $first_language = set_node(null, $flat, '~de:Erste~es:Otra', null, 50);
    $accent = set_node(null, $flat, 'Caf' . "\u{00e9}", null, 60);
    $literal = set_node(null, $flat, '100%_!', null, 70);
    $inactive = set_node(null, $flat, 'Inactive', null, 90);
    ps_query('UPDATE node SET active = 0 WHERE ref = ?', ['i', $inactive]);
    $hidden_child = set_node(null, $flat, 'Hidden child', $first, 1);
    clear_query_cache('schema');

    $cases = [
        'searchoptions:Alpha' => 'searchoptions:Alpha',
        'searchoptions:alpha;BETA' => NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $second,
        'searchoptions:Alpha;missing' => NODE_TOKEN_PREFIX . $first,
        'searchoptions:missing;unknown' => 'searchoptions:missing;unknown',
        'unknown:Alpha;Beta' => 'unknown:Alpha;Beta',
        'searchoptions:Al%;missing' => 'searchoptions:Al%;missing',
        'searchoptions:Alpha;Alpha' => NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $first,
        'searchoptions:Francais;English' => NODE_TOKEN_PREFIX . $translated,
        'searchoptions:Default;Erste' => NODE_TOKEN_PREFIX . $default . NODE_TOKEN_PREFIX . $first_language,
        'searchoptions:Cafe;CAF' . "\u{00c9}" => NODE_TOKEN_PREFIX . $accent,
        'searchoptions:100%_!;Inactive' => NODE_TOKEN_PREFIX . $literal . NODE_TOKEN_PREFIX . $inactive,
        '"searchoptions:Hidden child;Beta"' => NODE_TOKEN_PREFIX . $second,
        '"searchoptions: Alpha ;Beta"' => NODE_TOKEN_PREFIX . $second,
        '"searchoptions:Alpha;Beta:other"' => '"searchoptions:Alpha;Beta:other"',
    ];
    foreach ($cases as $input => $expected) {
        expect($preprocess($input))->toBe($expected, $input);
    }
    $repeated = 'searchoptions:Alpha;Beta searchoptions:Alpha;Beta';
    $tokens = NODE_TOKEN_PREFIX . $first . NODE_TOKEN_PREFIX . $second;
    expect($preprocess($repeated))->toBe($tokens . ' ' . $tokens);
    $GLOBALS['userpermissions'] = ['f*', 'f-' . $flat];
    expect($preprocess($repeated))->toBe($repeated);
    $GLOBALS['userpermissions'] = [];
    expect($preprocess($repeated))->toBe($repeated);
    $GLOBALS['userpermissions'] = ['f*'];

    $tree = $fixtures->field('Search tree', 0, FIELD_TYPE_CATEGORY_TREE, 'searchtree');
    $parent = set_node(null, $tree, 'Parent', null, 10);
    $child = set_node(null, $tree, 'Duplicate', $parent, 100);
    set_node(null, $tree, 'Duplicate', null, 20);
    expect($preprocess('searchtree:Duplicate;Parent'))
        ->toBe(NODE_TOKEN_PREFIX . $child . NODE_TOKEN_PREFIX . $parent);
});

it('honours the option lookup cap and fixed vocabulary fallback at the boundary', function () use (
    &$fixtures,
    $preprocess,
): void {
    $field = $fixtures->field('Large search text', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'searchlarge');
    $partial = set_node(null, $field, 'Contains target', null, 0);
    // The unused options exercise the lookup cap without individual indexing calls.
    for ($batch = 0; $batch < 10; $batch++) {
        $values = array_fill(0, 1000, '(?, ?, NULL, ?)');
        $parameters = [];
        for ($i = 1; $i <= 1000; $i++) {
            $index = $batch * 1000 + $i;
            array_push($parameters, 'i', $field, 's', 'Option ' . $index, 'i', $index);
        }
        ps_query(
            'INSERT INTO node (resource_type_field, name, parent, order_by) VALUES ' . implode(', ', $values),
            $parameters
        );
    }

    $exact = set_node(null, $field, 'target', null, 10001);
    $translated = set_node(null, $field, '~en:Remote~fr:Eloigne', null, 10002);
    $edge = get_node_id('Option 9999', $field);
    expect($preprocess('"searchlarge:target;Option 9999"'))
        ->toBe(NODE_TOKEN_PREFIX . $partial . NODE_TOKEN_PREFIX . $edge)
        ->and($preprocess('searchlarge:Remote;absent'))->toBe(NODE_TOKEN_PREFIX . $translated)
        ->and($preprocess('searchlarge:;absent'))->toBe(NODE_TOKEN_PREFIX . $partial)
        ->and($preprocess('searchlarge:tar%;absent'))->toBe(NODE_TOKEN_PREFIX . $partial);

    ps_query(
        'UPDATE resource_type_field SET type = ? WHERE ref = ?',
        ['i', FIELD_TYPE_CHECK_BOX_LIST, 'i', $field]
    );
    clear_query_cache('schema');
    expect($preprocess('searchlarge:target;absent'))->toBe(NODE_TOKEN_PREFIX . $exact)
        ->and($preprocess('searchlarge:Remote;absent'))->toBe('searchlarge:Remote;absent');

    delete_node($exact);
    delete_node($translated);
    delete_node(get_node_id('Option 10000', $field));
    expect($preprocess('searchlarge:target;absent'))->toBe(NODE_TOKEN_PREFIX . $partial);
});

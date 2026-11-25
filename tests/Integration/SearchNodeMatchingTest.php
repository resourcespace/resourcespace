<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['resource_field_verbatim_keyword_regex']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('combines fixed-list node tokens with AND, OR and exclusion', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $field = $fixtures->field('Node token options', 0, FIELD_TYPE_CHECK_BOX_LIST, 'options' . $id, true);
    [$long_node, $short_node, $other_node] = array_map(
        static fn (string $label): int => set_node(null, $field, $label, null, 10),
        ['Orchard ' . $id, 'Garden ' . $id, 'Forest ' . $id]
    );
    $long = NODE_TOKEN_PREFIX . $long_node;
    $short = NODE_TOKEN_PREFIX . $short_node;
    $other = NODE_TOKEN_PREFIX . $other_node;
    $both = $fixtures->resource(1, 0);
    $one = $fixtures->resource(1, 0);
    $neither = $fixtures->resource(1, 0);
    add_resource_nodes($both, [$long_node, $short_node]);
    add_resource_nodes($one, [$long_node]);
    add_resource_nodes($neither, [$other_node]);

    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    $negative = NODE_TOKEN_PREFIX . '!' . $short_node;
    expect($find((string) $both))->toBe([$both])
        ->and($find($long))->toEqualCanonicalizing([$both, $one])
        ->and($find("$long $short"))->toBe([$both])
        ->and($find($short . $other))->toEqualCanonicalizing([$both, $neither])
        ->and($find("$long $negative"))->toBe([$one])
        ->and($find($negative))->toContain($one, $neither)->not->toContain($both)
        ->and($find('"Garden ' . $id . '"'))->toBe([$both]);

    $GLOBALS['resource_field_verbatim_keyword_regex'][$GLOBALS['view_title_field']] = '/.+/';
    expect($find("$long $short"))->toBe([$both]);
});

it('finds and excludes keywords across fixed options and free text while preserving phrase order', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $field = $fixtures->field('Indexed options', 0, FIELD_TYPE_CHECK_BOX_LIST, 'indexed' . $id, true);
    $shared = 'mammal' . $id;
    $only_node = 'giraffe' . $id;
    $only_text = 'rodent' . $id;
    $node_phrase = 'blind mice' . $id;
    $text_phrase = 'second third' . $id;
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(2, 0);
    $third = $fixtures->resource(2, 0);
    $node = set_node(null, $field, $shared, null, 10);
    add_resource_nodes($first, [$node]);
    add_resource_nodes($second, [
        $node,
        set_node(null, $field, $only_node, null, 20),
        set_node(null, $field, $node_phrase, null, 30),
    ]);
    $errors = [];
    $title = $GLOBALS['view_title_field'];
    $scope = 'animal' . $id;
    expect(update_field($first, $title, "$scope $text_phrase", $errors))->toBeTrue()
        ->and(update_field($second, $title, "$scope swimming", $errors))->toBeTrue()
        ->and(update_field($third, $title, "$scope $shared $only_text", $errors))->toBeTrue();

    $find = static fn (string $query): array => array_column((array) do_search($query), 'ref');
    expect($find($shared))->toEqualCanonicalizing([$first, $second, $third])
        ->and($find($only_text))->toBe([$third])
        ->and($find($only_node))->toBe([$second])
        ->and($find("$shared -swimming"))->toEqualCanonicalizing([$first, $third])
        ->and($find("$scope -$only_node"))->toEqualCanonicalizing([$first, $third])
        ->and($find('"' . $text_phrase . '"'))->toBe([$first])
        ->and($find('"' . $node_phrase . '"'))->toBe([$second])
        ->and($find('"' . 'mice' . $id . ' blind"'))->toBeEmpty();
});

it('omits configured stop words from a fixed option index', function () use (&$fixtures): void {
    $field = $fixtures->field('Stop word options', 0, FIELD_TYPE_CHECK_BOX_LIST, '', true);
    $node = set_node(null, $field, implode(' ', $GLOBALS['noadd']), null, 10);
    expect(
        ps_array('SELECT keyword AS value FROM node_keyword WHERE node = ?', ['i', $node])
    )->toBeEmpty();
});

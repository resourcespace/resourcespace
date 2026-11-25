<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'userpermissions', 'hidden_fields_cache', 'autocomplete_search_min_hitcount', 'keywords_remove_diacritics',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('does not suggest hidden keywords from text or fixed-list metadata', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(12);
    $GLOBALS['autocomplete_search_min_hitcount'] = 1;
    foreach ([FIELD_TYPE_TEXT_BOX_SINGLE_LINE => 'hiddenowl', FIELD_TYPE_CHECK_BOX_LIST => 'hiddenlion'] as $type => $str) {
        $field = $fixtures->field('Hidden suggestion', 0, $type, '', true);
        $resource = $fixtures->resource(1, 0);
        $keyword = $str . $suffix;
        if ($type === FIELD_TYPE_CHECK_BOX_LIST) {
            $node = set_node(null, $field, $keyword, null, 10);
            add_resource_nodes($resource, [$node]);
        } else {
            $errors = [];
            expect(update_field($resource, $field, $keyword, $errors))->toBeTrue();
        }
        $GLOBALS['userpermissions'][] = "f-{$field}";
        unset($GLOBALS['hidden_fields_cache']);
        expect(get_suggested_keywords($str))->not->toContain($keyword);
    }
});

it('suggests matching dynamic keywords and requires permission to propose another', function () use (&$fixtures): void {
    $field = $fixtures->field('Dynamic suggestions', 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, '', true);
    $accented = set_node(null, $field, 'Anaée', null, 10);
    $plain = set_node(null, $field, 'Anais', null, 20);
    $values = static fn (string $input, bool $readonly): array =>
        array_column(suggest_dynamic_keyword_nodes($field, $input, $readonly), 'value');

    $suggestions = $values('ana', false);
    expect($suggestions)->toHaveCount(3)->toContain($accented, $plain);
    expect($values('anaé', false))->toHaveCount(2)->toContain($accented)->not->toContain($plain);
    expect($values('ana', true))->toEqualCanonicalizing([$accented, $plain]);

    foreach (["bdk{$field}", "F{$field}"] as $restriction) {
        $permissions = $GLOBALS['userpermissions'];
        $GLOBALS['userpermissions'][] = $restriction;
        expect($values('ana', false))->toEqualCanonicalizing([$accented, $plain]);
        $GLOBALS['userpermissions'] = $permissions;
    }
    $GLOBALS['userpermissions'][] = "f-{$field}";
    expect($values('ana', true))->toBeEmpty();
});

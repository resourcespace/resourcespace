<?php

declare(strict_types=1);

$keywords = [];
afterEach(function () use (&$keywords): void {
    foreach ($keywords as $ref) {
        ps_query('DELETE FROM keyword WHERE ref = ?', ['i', $ref]);
    }
    clear_query_cache('keyword');
    $keywords = [];
});

it('resolves an existing keyword and creates a distinct new one when requested', function () use (&$keywords): void {
    $first = 'existing' . generateRandomTestID(12);
    $second = 'new' . generateRandomTestID(12);
    $first_ref = resolve_keyword($first, true);
    $second_ref = resolve_keyword($second, true);
    $keywords = [$first_ref, $second_ref];

    expect($first_ref)->toBeNumeric()
        ->and($second_ref)->toBeNumeric()
        ->and($second_ref)->not->toEqual($first_ref)
        ->and(resolve_keyword($first, false))->toEqual($first_ref);
});

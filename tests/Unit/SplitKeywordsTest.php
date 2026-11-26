<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->originalKeywordConfig = array_intersect_key($GLOBALS, [
        'config_separators' => true,
        'keyword_allowed_symbols' => true,
    ]);
});

afterEach(function (): void {
    unset($GLOBALS['config_separators'], $GLOBALS['keyword_allowed_symbols']);
    foreach ($this->originalKeywordConfig as $key => $value) {
        $GLOBALS[$key] = $value;
    }
});

it('applies the keyword allowlist in searches and indexing', function (
    array $separators,
    array $allowlist,
    string $input,
    array $searchKeywords,
    array $indexKeywords
): void {
    $GLOBALS['config_separators'] = $separators;
    $GLOBALS['keyword_allowed_symbols'] = $allowlist;

    expect(split_keywords($input, keepquotes: true))->toBe($searchKeywords)
        ->and(split_keywords($input, index: true))->toBe($indexKeywords);
})->with([
    'empty allowlist' => [['_'], [], '2020.3a-l', ['2020.3al'], ['2020.3al']],
    'allowlisted hyphen' => [['_'], ['-'], '2020.3a-l', ['2020.3a-l'], ['2020.3a-l']],
    'allowlisted ampersand' => [['_'], ['&'], 'a&b', ['a&b'], ['a&b']],
    'allowlisted forward slash' => [['_'], ['/'], 'album/disc', ['album/disc'], ['album/disc']],
    'separator takes precedence' => [['_', '-'], ['-'], '2020.3a-l', ['2020.3a', 'l'], ['2020.3a', 'l']],
    'keyword exclusion' => [['_', '-'], ['&'], 'photo -draft', ['photo', '-draft'], ['photo', 'draft']],
]);

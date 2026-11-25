<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['index_contributed_by']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('finds dates inside a requested range and excludes dates outside it', function (
    string $from,
    string $to,
    array $expected,
) use (&$fixtures): void {
    $name = 'rangedate' . generateRandomTestID(8);
    $field = $fixtures->field('Range search date', 0, FIELD_TYPE_DATE_AND_OPTIONAL_TIME, $name);
    $resources = [];
    $errors = [];
    foreach (['1985-07-15', '1999-12-31', '2015-03-08'] as $value) {
        $resource = $fixtures->resource(1, 0);
        expect(update_field($resource, $field, $value, $errors))->toBeTrue();
        $resources[] = $resource;
    }

    $result = do_search("{$name}:rangestart{$from}end{$to}");
    $ids = is_array($result) ? array_column($result, 'ref') : [];
    expect($ids)->toEqualCanonicalizing(array_map(static fn (int $index): int => $resources[$index], $expected));
})->with([
    'all dates' => ['1970-02-02', '2016-09-06', [0, 1, 2]],
    'no dates' => ['1975-03-25', '1982-02-04', []],
    'some dates' => ['1976-06-01', '2000-01-02', [0, 1]],
]);

it('can narrow search results to populated or empty metadata', function () use (&$fixtures): void {
    $field = $fixtures->field('Presence to test');
    $populated = $fixtures->resource(1, 0);
    $empty = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($populated, $field, 'Present', $errors))->toBeTrue();

    $ids = array_column(do_search("!hasdata{$field}"), 'ref');
    expect($ids)->toContain($populated)->not->toContain($empty);
    $ids = array_column(do_search("!empty{$field}"), 'ref');
    expect($ids)->toContain($empty)->not->toContain($populated);
});

it('can match an uploader by name and returns only their contributions for explicit searches', function () use (&$fixtures): void {
    $username = 'creator' . generateRandomTestID(16);
    $author = $fixtures->user($username);
    $first = $fixtures->resource(1, 0, $author);
    $second = $fixtures->resource(1, 0, $author);
    $decoy = $fixtures->resource(1, 0);
    $errors = [];
    expect(update_field($second, $GLOBALS['view_title_field'], $username . ' title', $errors))->toBeTrue();

    $GLOBALS['index_contributed_by'] = false;
    expect(array_column(do_search($username), 'ref'))->toBe([$second]);
    $GLOBALS['index_contributed_by'] = true;
    expect(array_column(do_search($username, order_by: 'resourceid', sort: 'ASC'), 'ref'))->toBe([$first, $second]);

    foreach (["!propertiescu:{$author}", "!contributions{$author}"] as $query) {
        expect(array_column(do_search($query), 'ref'))->toEqualCanonicalizing([$first, $second])
            ->not->toContain($decoy);
    }
});

it('scopes special searches for explicit lists, duplicates, and non-ingested files', function () use (&$fixtures): void {
    $a = $fixtures->resource(1, 0);
    $b = $fixtures->resource(1, 0);
    $c = $fixtures->resource(1, 0);
    $non_ingested = $fixtures->resource(1, 0);
    $field = $fixtures->field('Special search option', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $node = set_node(null, $field, 'Include only this resource', null, 10);
    expect($node)->toBeInt();
    add_resource_nodes($a, [$node]);
    $checksum = generateRandomTestID(32);
    ps_query(
        'UPDATE resource SET file_checksum = ? WHERE ref IN (?, ?, ?)',
        ['s', $checksum, 'i', $a, 'i', $b, 'i', $c]
    );
    ps_query('UPDATE resource SET file_path = ? WHERE ref = ?', [
        's', 'pest-noningested-' . generateRandomTestID(16) . '.jpg', 'i', $non_ingested,
    ]);

    $cases = [
        "!list{$a}" => [$a],
        "!list{$a}:{$b}" => [$a, $b],
        "!list{$a},@@{$node}" => [$a],
        "!list{$b},@@{$node}" => [],
        "!unused,@@{$node}" => [$a],
        "!duplicates{$b}" => [$a, $b, $c],
        "!duplicates{$b},@@{$node}" => [$a],
        '!duplicates' => [$a, $b, $c],
        '!noningested' => [$non_ingested],
    ];
    foreach ($cases as $query => $expected) {
        $result = do_search($query, return_refs_only: true);
        $refs = is_array($result) ? array_column($result, 'ref') : [];
        expect(array_values(array_intersect($refs, [$a, $b, $c, $non_ingested])))->toEqualCanonicalizing($expected);
    }
});

it('searches within geographic bounds and excludes unlocated and out-of-bounds resources', function () use (&$fixtures): void {
    $inside = $fixtures->resource(1, 0);
    $outside = $fixtures->resource(1, 0);
    $unlocated = $fixtures->resource(1, 0);
    expect(put_resource_data($inside, ['geo_lat' => 51.5, 'geo_long' => -0.12]))->toBeTrue()
        ->and(put_resource_data($outside, ['geo_lat' => 52.2, 'geo_long' => -0.12]))->toBeTrue();

    $results = array_column(do_search('!geo51bm1t52b0'), 'ref');
    expect($results)->toContain($inside)->not->toContain($outside)->not->toContain($unlocated);
});

it('paginates search previews and returns matching thumbnail URLs and totals', function () use (&$fixtures): void {
    $term = 'thumbnailresults' . generateRandomTestID(12);
    $file = testFixture('Images/image_1.jpg');
    $resources = [];
    $errors = [];
    for ($i = 0; $i < 8; $i++) {
        $ref = $fixtures->resource(1, 0);
        expect(copy($file, get_resource_path($ref, true, 'thm', true, 'jpg')))->toBeTrue();
        ps_query(
            'UPDATE resource SET has_image = ?, preview_extension = ? WHERE ref = ?',
            ['i', RESOURCE_PREVIEWS_ALL, 's', 'jpg', 'i', $ref]
        );
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        $resources[] = $ref;
    }
    $find = static fn (int|array|string $rows): array|string =>
        search_get_previews($term, order_by: 'ref', fetchrows: $rows, sort: 'ASC', getsizes: ['thm']);
    $assert_urls = static function (array $data): void {
        foreach ($data as $result) {
            expect($result['url_thm'])->toBe(get_resource_path($result['ref'], false, 'thm'));
        }
    };
    $all = $find(-1);
    expect(array_column($all, 'ref'))->toBe($resources);
    $assert_urls($all);
    $limited = array_values(array_filter($find(3), 'is_array'));
    expect(array_column($limited, 'ref'))->toBe(array_slice($resources, 0, 3));
    $assert_urls($limited);

    foreach ([[[0, 3], 0, 3], [[6, 5], 6, 2], ['2,3', 2, 3], ['2,', 2, 0], [',4', 0, 4]] as [$rows, $offset, $count]) {
        $results = $find($rows);
        expect($results['total'])->toBe(count($resources))
            ->and(array_column($results['data'], 'ref'))->toBe(array_slice($resources, $offset, $count));
        $assert_urls($results['data']);
    }
    expect($find('foo,5,'))->toBe('');
});

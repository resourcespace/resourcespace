<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['user_rating_only_once']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('maintains per-user ratings and resource totals across create, update and removal', function () use (&$fixtures): void {
    $GLOBALS['user_rating_only_once'] = true;
    $resource = $fixtures->resource(1, 0);
    $first = $GLOBALS['userref'];
    $second = $fixtures->user('rating_' . generateRandomTestID(12));

    $ratings = [[$first, 3, 3, 1], [$second, 5, 8, 2], [$first, 4, 9, 2], [$first, 0, 5, 1]];
    foreach ($ratings as [$user, $rating, $total, $count]) {
        user_rating_save($user, $resource, $rating);
        $data = get_resource_data($resource, false);
        expect((int) $data['user_rating_total'])->toBe($total)
            ->and((int) $data['user_rating_count'])->toBe($count)
            ->and(ps_value('SELECT COUNT(*) AS value FROM user_rating WHERE ref = ?', ['i', $resource], -1))
            ->toEqual($count);
    }

    expect(ps_value(
        'SELECT rating AS value FROM user_rating WHERE user = ? AND ref = ?',
        ['i', $second, 'i', $resource],
        -1
    ))->toEqual(5);
});

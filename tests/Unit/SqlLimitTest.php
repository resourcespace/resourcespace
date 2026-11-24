<?php

declare(strict_types=1);

it('formats a SQL limit and optional offset', function (?int $offset, ?int $rows, string $expected): void {
    expect(sql_limit($offset, $rows))->toBe($expected);
})->with([
    'no limit or offset' => [null, null, ''],
    'limit only' => [null, 15, 'LIMIT 15'],
    'offset without a limit is ignored' => [15, null, ''],
    'limit and offset' => [15, 15, 'LIMIT 15,15'],
]);

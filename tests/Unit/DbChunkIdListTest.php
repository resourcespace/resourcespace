<?php

declare(strict_types=1);

it('filters invalid IDs', function (array $input, array $expected): void {
    expect(db_chunk_id_list($input))->toBe($expected);
})->with([
    'mixed input' => [[1, 'bad', 3], [[1, 3]]],
    'only invalid input' => [['bad'], []],
]);

it('keeps one complete chunk and drops trailing invalid IDs', function (): void {
    $ids = range(1, SYSTEM_DATABASE_IDS_CHUNK_SIZE);

    expect(db_chunk_id_list([...$ids, 'bad']))->toBe([$ids]);
});

it('starts a new chunk when the limit is exceeded', function (): void {
    $first_chunk = range(1, SYSTEM_DATABASE_IDS_CHUNK_SIZE);
    $next_id = SYSTEM_DATABASE_IDS_CHUNK_SIZE + 1;

    expect(db_chunk_id_list($first_chunk))->toBe([$first_chunk])
        ->and(db_chunk_id_list([...$first_chunk, $next_id]))->toBe([$first_chunk, [$next_id]]);
});

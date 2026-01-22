<?php

command_line_only();

// --- Set up
$expect_count_match = fn(array $R, int $match): bool => count($R) === $match;
// --- End of Set up

$use_cases = [
    [
        'name' => 'Validate input is all numeric (int loose)',
        'input' => [1, 'bad', 3],
        'expected' => [[1, 3]],
    ],
    [
        'name' => 'Only invalid input',
        'input' => ['bad'],
        'expected' => [],
    ],
    [
        'name' => 'Chunks w/ invalid data (empty chunks) get removed',
        'input' => array_merge(range(1, SYSTEM_DATABASE_IDS_CHUNK_SIZE), ['bad']),
        'expected' => fn(array $res): bool => $expect_count_match($res, 1),
    ],
    [
        'name' => 'Chunking in line with SYSTEM_DATABASE_IDS_CHUNK_SIZE',
        'input' => range(1, SYSTEM_DATABASE_IDS_CHUNK_SIZE),
        'expected' => fn(array $res): bool => $expect_count_match($res[0], SYSTEM_DATABASE_IDS_CHUNK_SIZE),
    ],
];
foreach ($use_cases as $uc) {
    $result = db_chunk_id_list($uc['input']);
    $expected = is_callable($uc['expected']) ? $uc['expected']($result) : $uc['expected'];

    if (
        (is_callable($uc['expected']) && !$expected)
        || (is_array($expected) && $expected !== $result)
    ) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases, $result, $expect_count_match);

return true;

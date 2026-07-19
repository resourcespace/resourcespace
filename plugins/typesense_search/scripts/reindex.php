<?php

/**
 * CLI script to reindex existing resources into Typesense.
 */

include_once dirname(__DIR__, 3) . '/include/boot.php';
include_once dirname(__DIR__) . '/include/typesense_search_functions.php';

command_line_only();
set_time_limit(0);

$batch_size = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 100;
$after = isset($argv[2]) && is_numeric($argv[2]) ? (int)$argv[2] : 0;

$total_indexed = 0;
$total_failed = 0;
$total_content_length = 0;

$overall_start = microtime(true);

if (!typesense_search_ensure_collection()) {
    echo 'Failed to ensure Typesense collection exists.' . PHP_EOL;
    exit(1);
}

// Sync the related keywords.
typesense_search_sync_related_keywords();

echo 'Starting Typesense reindex'
    . ' | Batch size: ' . $batch_size
    . ' | Starting after ref: ' . $after
    . PHP_EOL;

ob_flush();
flush();

do {
    $batch_start = microtime(true);

    $result = typesense_search_reindex_all($batch_size, $after);

    $batch_time = microtime(true) - $batch_start;

    $total_indexed += $result['indexed'];
    $total_failed += $result['failed'];
    $total_content_length += $result['content_length'];

    $overall_time = microtime(true) - $overall_start;

    $rate = $overall_time > 0
        ? round($total_indexed / $overall_time, 2)
        : 0;

    echo '[' . date('Y-m-d H:i:s') . '] '
        . 'Indexed this batch: ' . $result['indexed']
        . ' | Failed this batch: ' . $result['failed']
        . ' | Total indexed: ' . $total_indexed
        . ' | Total failed: ' . $total_failed
        . ' | Last ref: ' . $result['last']
        . ' | Batch content: ' . number_format($result['content_length']) . ' chars'
        . ' | Total content: ' . number_format($total_content_length) . ' chars'
        . ' | Batch time: ' . round($batch_time, 2) . 's'
        . ' | Rate: ' . $rate . ' resources/sec'
        . ' | Memory: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        . PHP_EOL;

    ob_flush();
    flush();

    $after = (int)$result['last'];
} while (!$result['complete']);

echo PHP_EOL
    . 'Reindex complete'
    . ' | Total indexed: ' . $total_indexed
    . ' | Total failed: ' . $total_failed
    . ' | Total content: ' . number_format($total_content_length) . ' chars'
    . ' | Total time: ' . round(microtime(true) - $overall_start, 2) . 's'
    . PHP_EOL;

ob_flush();
flush();

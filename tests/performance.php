<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

if (PHP_SAPI !== 'cli' || getenv('RS_TEST_MODE') !== '1') {
    exit('Use composer test:performance to run the ResourceSpace benchmarks.' . PHP_EOL);
}

require_once __DIR__ . '/Support/bootstrap-resourcespace-test-env.php';
require_once dirname(__DIR__) . '/include/image_processing.php';

ResourceSpaceTestEnvironment::start();

$benchmarks = glob(__DIR__ . '/performance_tests/*.php');
$selected = getenv('RS_BENCHMARK');
if ($selected !== false && $selected !== '') {
    $selected = str_pad($selected, 6, '0', STR_PAD_LEFT);
    $benchmarks = array_filter(
        $benchmarks,
        static fn (string $path): bool => str_starts_with(basename($path), $selected . '_')
    );
}
if ($benchmarks === []) {
    fwrite(STDERR, 'No matching ResourceSpace benchmarks.' . PHP_EOL);
    exit(1);
}

foreach ($benchmarks as $benchmark) {
    echo 'Running benchmark ' . basename($benchmark) . PHP_EOL;
    $start = microtime(true);
    if ((require $benchmark) === false) {
        fwrite(STDERR, 'Benchmark failed: ' . basename($benchmark) . PHP_EOL);
        exit(1);
    }
    echo PHP_EOL . 'Elapsed ' . number_format(microtime(true) - $start, 2) . ' seconds.' . PHP_EOL;
}

<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Run a real command-line entry point against the isolated test environment.
 */
final class LocalTestScript
{
    /**
     * Boot ResourceSpace and apply fixture settings before executing a script.
     *
     * @param string $script Repository-relative script path.
     * @param array<string, mixed> $settings Non-sensitive settings for this run.
     * @param list<string> $arguments Command-line arguments.
     * @return string The script's standard output.
     */
    public static function run(string $script, array $settings = [], array $arguments = []): string
    {
        if (getenv('RS_TEST_MODE') !== '1') {
            throw new RuntimeException('Script integration tests require the isolated test environment.');
        }

        $entry_point = RESOURCESPACE_BASE_PATH . '/' . $script;
        if ($settings === []) {
            $process = new Process([PHP_BINARY, $entry_point, ...$arguments], dirname($entry_point), ['RS_TEST_MODE' => '1']);
            $process->mustRun();
            return $process->getOutput();
        }

        $bootstrap = tempnam(sys_get_temp_dir(), 'rs-pest-script-');
        if ($bootstrap === false) {
            throw new RuntimeException('Could not allocate the script bootstrap.');
        }

        try {
            $test_bootstrap = __DIR__ . '/bootstrap-resourcespace-test-env.php';
            $source = '<?php require ' . var_export($test_bootstrap, true) . ';' . PHP_EOL
                . 'foreach (' . var_export($settings, true) . ' as $fixture_key => $fixture_value) {'
                . '$GLOBALS[$fixture_key] = $fixture_value;' . '}' . PHP_EOL
                . 'ob_start();' . PHP_EOL;
            if (file_put_contents($bootstrap, $source) === false) {
                throw new RuntimeException('Could not write the script bootstrap.');
            }

            $process = new Process(
                [PHP_BINARY, '-d', 'auto_prepend_file=' . $bootstrap, $entry_point, ...$arguments],
                dirname($entry_point),
                ['RS_TEST_MODE' => '1']
            );
            $process->mustRun();
            return $process->getOutput();
        } finally {
            unlink($bootstrap);
        }
    }
}

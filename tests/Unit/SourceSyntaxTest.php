<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('parses the project PHP sources without executing them', function (): void {
    $root = RESOURCESPACE_BASE_PATH;
    $excluded = ['.svn', '.git', '.runtime', 'filestore', 'lib', 'node_modules', 'vendor'];
    $entries = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $source = new RecursiveCallbackFilterIterator(
        $entries,
        static function (SplFileInfo $entry) use ($excluded, $root): bool {
            if ($entry->isDir()) {
                return !in_array($entry->getFilename(), $excluded, true);
            }

            return $entry->getExtension() === 'php'
                && !in_array($entry->getPathname(), [$root . '/rector.php', $root . '/include/config.php'], true);
        }
    );
    $files = [];
    foreach (new RecursiveIteratorIterator($source) as $entry) {
        $files[] = $entry->getPathname();
    }
    expect($files)->not->toBeEmpty();

    $fails = [];
    foreach (array_chunk($files, 100) as $chunk) {
        $lint = new Process([PHP_BINARY, '-l', ...$chunk], $root);
        $lint->run();
        if (!$lint->isSuccessful()) {
            $fails[] = trim($lint->getErrorOutput()) ?: trim($lint->getOutput());
        }
    }

    expect($fails)->toBeEmpty(implode(PHP_EOL, $fails));
});

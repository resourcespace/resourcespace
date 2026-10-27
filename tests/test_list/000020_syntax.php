<?php

command_line_only();

$php_path = PHP_BINARY;

// Collect files
$exclude_paths = ["/lib/", "/filestore/", "/vendor/", "rector.php"];
$Directory = new RecursiveDirectoryIterator(__DIR__ . "/../../", FilesystemIterator::SKIP_DOTS);
// Prune excluded directories before descending into them (especially filestore).
$filtered_directory = new RecursiveCallbackFilterIterator($Directory, function ($entry) use ($exclude_paths) {
    foreach ($exclude_paths as $ex_path) {
        if (strpos($entry->getPathname() . ($entry->isDir() ? '/' : ''), $ex_path) !== false) {
            return false;
        }
    }
    return true;
});
$Iterator = new RecursiveIteratorIterator($filtered_directory);

$pages = [];
foreach ($Iterator as $i) {
    if ($i->getExtension() !== "php") {
        continue;
    }
    $path = $i->getPathname();
    foreach ($exclude_paths as $ex_path) {
        if (strpos($path, $ex_path) !== false) {
            continue 2;
        }
    }
    $pages[] = $path;
}

$total   = count($pages);
$counter = 0;
$shown   = -1;

// Number of parallel workers
$parallel = 8; // tweak to CPU cores

// Split into chunks
$chunks = array_chunk($pages, max(1, (int) ceil($total / $parallel)));

$pipes = [];
$procs = [];

foreach ($chunks as $chunk) {
    $cmd = escapeshellarg($php_path) . ' -l ' . implode(' ', array_map('escapeshellarg', $chunk));

    // Files avoid workers blocking on full stdout/stderr pipes while another is read.
    $output = tmpfile();
    if ($output === false) {
        throw new RuntimeException('Could not create syntax check output file');
    }
    $procPipes = [];
    $proc = proc_open(
        $cmd,
        [0 => ['pipe', 'r'], 1 => $output, 2 => $output],
        $procPipes
    );

    if (is_resource($proc)) {
        fclose($procPipes[0]);
        $procs[] = $proc;
        $pipes[] = $output;
    } else {
        fclose($output);
        throw new RuntimeException('Could not start syntax checker');
    }
}


$errors = [];
foreach ($procs as $idx => $proc) {
    if (is_resource($proc)) {
        $exit_code = proc_close($proc);
        rewind($pipes[$idx]);
        $stdout = stream_get_contents($pipes[$idx]);
        fclose($pipes[$idx]);
        if ($exit_code !== 0) {
            $errors[] = 'Syntax checker exited with status ' . $exit_code;
        }

        // Progress update (fake-percentage from chunks)
        $counter += count($chunks[$idx]);
        $percent = round($counter * 100 / $total);
        if ($percent !== $shown) {
            echo "\e[4D" . str_pad($percent, 3, " ", STR_PAD_LEFT) . "%";
            ob_flush();
            $shown = $percent;
        }

        // Capture errors
        foreach (explode("\n", $stdout) as $line) {
            if ($line && strpos($line, "No syntax errors") === false) {
                $errors[] = $line;
            }
        }
    }
}

echo "\e[4D    ";

if ($errors) {
    echo "Syntax errors found:\n" . implode("\n", $errors) . "\n";
    return false;
}

return true;

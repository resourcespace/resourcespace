<?php

command_line_only();

$php_path = PHP_BINARY;

// Collect files
$exclude_paths = ["/lib/", "/filestore/", "/vendor/", "rector.php"];
$Directory = new RecursiveDirectoryIterator(__DIR__ . "/../../");
$Iterator  = new RecursiveIteratorIterator($Directory);

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
$chunks = array_chunk($pages, ceil($total / $parallel));

$pipes = [];
$procs = [];

foreach ($chunks as $chunk) {
    $cmd = $php_path . ' -l ' . implode(' ', array_map('escapeshellarg', $chunk));

    $procPipes = [];
    $proc = proc_open(
        $cmd,
        [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]],
        $procPipes
    );

    if (is_resource($proc)) {
        $procs[] = $proc;
        $pipes[] = $procPipes;
    }
}


$errors = [];
foreach ($procs as $idx => $proc) {
    if (is_resource($proc)) {
        $stdout = stream_get_contents($pipes[$idx][1]);
        $stderr = stream_get_contents($pipes[$idx][2]);
        fclose($pipes[$idx][1]);
        fclose($pipes[$idx][2]);

        proc_close($proc);

        // Progress update (fake-percentage from chunks)
        $counter += count($chunks[$idx]);
        $percent = round($counter * 100 / $total);
        if ($percent !== $shown) {
            echo "\e[4D" . str_pad($percent, 3, " ", STR_PAD_LEFT) . "%";
            ob_flush();
            $shown = $percent;
        }

        // Capture errors
        foreach (explode("\n", $stdout . $stderr) as $line) {
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

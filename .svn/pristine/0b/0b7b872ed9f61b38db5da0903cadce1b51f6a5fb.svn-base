<?php

command_line_only();

$start_time = microtime(true);

// Create resources
$rescount = 1000;
for ($n = 0; $n < $rescount; $n++) {
    create_resource(1, 0, -1);
}

echo str_pad("Time to create " . $rescount . " resources (seconds):", 60) . round(microtime(true) - $start_time, 2) . " - ";

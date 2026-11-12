<?php

include_once dirname(__DIR__, 2) . "/include/boot.php";

$output_newline = PHP_EOL;
if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    $output_newline = '<br>';
}

include_once dirname(__DIR__, 2) . '/include/geolocation_functions.php';
geo_setcoords_from_country($output_newline);

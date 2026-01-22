<?php

if ('cli' != PHP_SAPI) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Command line execution only');
}

include __DIR__ . "/../../include/boot.php";

resign_all_code();

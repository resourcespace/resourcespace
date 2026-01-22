<?php

include "../../include/boot.php";
include "../../include/authenticate.php";

$page = getval('page', "");
$plugin = getval('plugin', "");
// In some cases the page has extra parameters
if (strpos($page, ".php")) {
    $page = explode(".php", $page)[0];
}

header('Content-Type: text/plain');
echo get_page_title($page, $plugin);

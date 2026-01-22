<?php

# AJAX ratings save

include "../../include/boot.php";
include "../../include/authenticate.php";

if (getval("action", "") == "showcollection") {
    show_hide_collection(getval("collection", "", true), true, $userref);
    exit("UNHIDDEN");
}

if (getval("action", "") == "hidecollection") {
    show_hide_collection(getval("collection", "", true), false, $userref);
    exit("HIDDEN");
}

exit("no action specified");

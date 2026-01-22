<?php

# Quick script to check all site text entries are valid HTML and error for any that aren't.

include "../../include/boot.php";

include "../../include/authenticate.php";
if (!checkperm("a")) {
    exit("Permission denied");
}

echo "<pre>";

$site_texts = ps_query("select * from site_text order by page,name,ref");
foreach ($site_texts as $site_text) {
    $html = trim($site_text["text"]);
    $result = validate_html($html);

    echo $site_text["page"] . "/" . $site_text["name"] . " : ";
    if ($result === true) {
        echo "OK\n";
    } else {
        echo "FAIL - $result \n";
    }
}


echo "</pre>";

<?php

# This script takes content that is stored in site_text and outputs language strings suitable for inclusion in the language files.
# It was used for porting from the old site_text/dbstruct method of storing default site content to the new language string system.
# It's been included in case users have a large amount of locally translated content in site_text that needs to be pulled out in to language files.
# ~Dan Huby, Montala Limited, Feb 2015.

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

$lang = getval("lang", "en");

header("Content-type: text/plain; charset=utf-8");

$site_text = ps_query("SELECT page, name, text FROM site_text WHERE language = ? GROUP BY page, name", ["s", $lang]);
foreach ($site_text as $s) {
    $key = $s["page"] . "__" . $s["name"];
    if ($s["page"] == "") {
        $key = $s["name"];
    }
    echo "\$lang[\"" . $key . "\"]=\"" . str_replace("\n", "\\n", addslashes($s["text"])) . "\";\n";
}

<?php

#
# Reindex_collections.php
#
#
# Reindexes the collection index used for public searching.
#

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

set_time_limit(60 * 60 * 5);
echo "<pre>";

$collections = ps_query("select * from collection order by ref");
for ($n = 0; $n < count($collections); $n++) {
        $ref = $collections[$n]["ref"];

        $words = index_collection($ref);

        echo "Done $ref (" . ($n + 1) . "/" . count($collections) . ") - $words words<br />\n";
}

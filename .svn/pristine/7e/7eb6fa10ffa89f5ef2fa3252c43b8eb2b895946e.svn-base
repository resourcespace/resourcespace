<?php

# Remove all resource records where the file on disk is missing.
# Useful for cleaning up databases where the files on disk have been lost (intentionally or otherwise!)

include "../../include/boot.php";

if (php_sapi_name() != "cli") {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
}

set_time_limit(0);

echo "<pre>";
// NB $view_title_field is not user provided and is part of a table name does not need to be (and cannot be!) passed in as a parameter
$resources = ps_query("select ref,field" . $view_title_field . ",file_extension from resource where ref>0 and file_extension is not null and length(file_extension)>0 and archive<>3 order by ref");
foreach ($resources as $resource) {
    echo "\n Checking " . $resource["ref"] . " ... ";
    $resource_path = get_resource_path($resource['ref'], true, "", false, $resource['file_extension']);
    if (!file_exists($resource_path)) {
        echo " file not found, deleting $resource_path \n";
        ps_query("update resource set archive=3 where ref=? limit 1", array("i",$resource["ref"]));
    }
}

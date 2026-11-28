<?php

# This script is for updating the $portrait_landscape_field

include "../../include/boot.php";

if ('cli' != PHP_SAPI) {
    include "../../include/authenticate.php";

    if (!checkperm("a")) {
        exit("Permission denied");
    }
}

if (!isset($portrait_landscape_field) || $portrait_landscape_field === '') {
    exit("portrait_landscape_field not set in config");
}

include_once "../../include/image_processing.php";

set_time_limit(60 * 60 * 40);

echo "Updating portrait_landscape_field (field $portrait_landscape_field)...";

$rd = ps_query("SELECT ref, file_extension FROM resource WHERE has_image > 0");
for ($n = 0; $n < count($rd); $n++) {
    $ref = $rd[$n]['ref'];
    echo ('cli' == PHP_SAPI ? "\n" : "<br />") . $ref;
    update_portrait_landscape_field($ref);
    flush();
    ob_flush();
}
echo "...done." . ('cli' == PHP_SAPI ? "\n" : "<br />");


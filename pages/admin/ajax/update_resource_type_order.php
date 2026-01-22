<?php

include __DIR__ . "/../../../include/boot.php";
include __DIR__ . "/../../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

# Reordering capability

#  Check for the parameter and reorder as necessary.
$reorder = getval("reorder", false);
if ($reorder) {
        $neworder = json_decode(getval("order", false));
        update_resource_type_order($neworder);
        exit("SUCCESS");
}

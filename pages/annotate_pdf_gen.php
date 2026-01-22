<?php

// this program creates a new PDF document with annotations
include dirname(__DIR__) . '/include/boot.php';
include RESOURCESPACE_BASE_PATH . '/include/authenticate.php';

if (canSeeAnnotationsFields() === []) {
    exit($lang['error-permissiondenied']);
}

$ref = getval("ref", 0);
$size = getval("size", "letter");
$color = getval("color", "yellow");
$previewpage = getval("previewpage", 1, true);

if (getval("preview", "") != "") {
    $preview = true;
} else {
    $preview = false;
}

$is_collection = false;
if (substr($ref, 0, 1) == "C") {
    $ref = substr($ref, 1);
    $is_collection = true;
}
$result = create_annotated_pdf((int)$ref, $is_collection, $size, true, $preview);

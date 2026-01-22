<?php

include "../../include/boot.php";

include "../../include/authenticate.php";
include "../../include/comment_functions.php"; 

$ref = (int) getval('ref', 0, true);
if ($ref === 0) {
    exit();
}

# User must be able to view the resource to add or load comments.
$can_view_resource = get_resource_access($ref) < RESOURCE_ACCESS_CONFIDENTIAL;

$resource_ref = (int) getval('resource_ref', 0, true);
if (
    'POST' == $_SERVER['REQUEST_METHOD']
    && !empty($username)
    && $resource_ref === $ref
    && $can_view_resource
     ) {
        comments_submit();
}

if ($can_view_resource) {
    $collection_mode = ('' != getval('collection_mode', '') ? true : false);
    comments_show($ref, $collection_mode);
}


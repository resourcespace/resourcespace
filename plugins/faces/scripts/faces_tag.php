<?php

# IMPORTANT NOTE: The functionality this script provides is now accessible via the front-end job runner, so 
#                 this script may be deprecated in future versions

include_once dirname(__FILE__, 4) . '/include/boot.php';
include_once dirname(__FILE__, 2) . '/include/faces_functions.php';

command_line_only();

# Script to tag untagged faces that have previously been detected using faces_detect.php
# -c/--collection can be used so only resources in the specified collections will be updated, parameter can be repeated e.g php faces_tag.php -c123 -c456

$collections    = [];
$collectionset  = false;

$cli_short_options = 'c::';
$cli_long_options  = array('collection::');
$cli_options = getopt($cli_short_options, $cli_long_options);

if ($cli_options !== false) {

    foreach ($cli_options as $option_name => $option_value) {
        if (in_array($option_name, array('c', 'collection'))) {

            $collectionset = true;

            if (is_array($option_value)) {
                $collections = $option_value;
                continue;
            } elseif (is_int_loose($option_value)) {
                $collections[] = $option_value;
            }
            
        }
    }

}

if($collectionset && (empty($collections) || array_filter($collections, fn($v) => filter_var($v, FILTER_VALIDATE_INT) === false))) {
    exit("Invalid syntax. Please note that a collection ID must be specified immediately following the '-c' or '--collection' e.g -c55\n\n");
}

$resources = [];

if(!empty($collections)) {
    $collections = array_unique($collections);

     // Find all faces that have not been identified that are part of the collections passed.
    $resources = ps_array("SELECT DISTINCT resource value
                            FROM (
                                SELECT rf.resource 
                                FROM resource_face rf
                                INNER JOIN collection_resource cr ON cr.resource = rf.resource
                                WHERE (node IS NULL OR node = 0)
                                AND cr.collection IN (" . ps_param_insert(count($collections)) . ")
                                ORDER BY rf.ref DESC) resources", ps_param_fill($collections, "i"));

} else {
    // Find all faces that have not been identified.
    $resources = ps_array("SELECT DISTINCT resource value FROM resource_face WHERE (node IS NULL OR node = 0) ORDER BY ref DESC");
}

if (count($resources) > 0) {
    foreach ($resources as $resource) {
        faces_tag($resource);
    }
}

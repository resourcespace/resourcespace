<?php

include __DIR__ . '/../../include/boot.php';

if (PHP_SAPI != 'cli') {
    include_once "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Access denied");
    }
    $field = getval("field", 0, true);
    if ($field == 0) {
        exit("Please pass a valid metadata field ref as the 'field' parameter in the query string e.g. https://yoururl.com/pages/tools/fix_resource_field_column.php?field=8" . PHP_EOL);
    }
} else {
    if (isset($argv[1]) && is_int_loose($argv[1])) {
        $field = (int) $argv[1];
    } else {
        exit('Invalid or missing field reference. Supply the ref of the field to process, for example: php fix_resource_field_column.php 8' . PHP_EOL);
    }
}

$fieldinfo = get_resource_type_field($field);
if (!$fieldinfo) {
    exit('Invalid resource type field ref supplied.' . PHP_EOL);
}

$total_resources_count = (int) ps_value('SELECT count(ref) value from resource where ref > 0 order by ref ASC', [], 0);
$chunk_size = 50000; # Default is a large value - chunking to avoid memory limit issues in systems with very large number of resources.
$chunk_progress = 0;

while ($chunk_progress < $total_resources_count) {
    $allresources = ps_array('SELECT ref value from resource where ref > 0 order by ref ASC LIMIT ?, ?', ['i', $chunk_progress, 'i', $chunk_size], 0);
    if (in_array($fieldinfo['type'], $NODE_FIELDS)) {
        foreach ($allresources as $resource) {
            $resnodes = get_resource_nodes($resource, $field, true);
            $resvals = array_column($resnodes, "name");
            $resdata = implode($field_column_string_separator, $resvals);
            $value = truncate_join_field_value(strip_leading_comma($resdata));
            ps_query("update resource set field" . $field . "= ? where ref= ?", ['s', $value, 'i', $resource]);
        }
    } else {
        foreach ($allresources as $resource) {
            $resdata = get_data_by_field($resource, $field);
            $value = truncate_join_field_value(strip_leading_comma($resdata));
            ps_query("update resource set field" . $field . "= ? where ref= ?", ['s', $value, 'i', $resource]);
        }
    }
    $chunk_progress += $chunk_size;
}

exit("Script completed for resource type field $field - $total_resources_count resources processed." . PHP_EOL);

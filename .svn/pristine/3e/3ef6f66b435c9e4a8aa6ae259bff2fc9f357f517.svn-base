<?php

include "../../include/boot.php";
include_once "../../include/authenticate.php";
if (!checkperm("a")) {
    exit("Access denied");
}

$field = getval("field", 0, true);

if ($field > 0) {
    $fieldinfo = get_resource_type_field($field);
    $allresources = ps_array("SELECT ref value from resource where ref>0 order by ref ASC", [], 0);
    if (in_array($fieldinfo['type'], $NODE_FIELDS)) {
        foreach ($allresources as $resource) {
            $resnodes = get_resource_nodes($resource, $field, true);
            $resvals = array_column($resnodes, "name");
            $resdata = implode($field_column_string_separator, $resvals);
            $value = truncate_join_field_value(strip_leading_comma($resdata));
            ps_query("update resource set field" . $field . "= ? where ref= ?", ['s', $value, 'i', $resource]);
            echo "Updated resource " . $resource . ". Value: " . $value . "<br />";
        }
    } else {
        foreach ($allresources as $resource) {
            $resdata = get_data_by_field($resource, $field);
            $value = truncate_join_field_value(strip_leading_comma($resdata));
            ps_query("update resource set field" . $field . "= ? where ref= ?", ['s', $value, 'i', $resource]);
            echo "Updated resource " . $resource . ". Value: " . $value . "<br />";
        }
    }
} else {
    exit("Please pass a valid metadata field ref as the 'field' parameter in the query string e.g. https://yoururl.com/pages/tools/fix_resource_field_column.php?field=8");
}

<?php

command_line_only();

// Check date range search

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// create new date range field
$daterangefield = create_resource_type_field("Date test", 0, FIELD_TYPE_DATE_AND_OPTIONAL_TIME, "testdate");

update_field($resourcea, $daterangefield, "1985-07-15");
update_field($resourceb, $daterangefield, "1999-12-31");
update_field($resourcec, $daterangefield, "2015-03-08");

// SUBTEST A
// cover all dates - get all 3 resources
$results = do_search('testdate:rangestart1970-02-02end2016-09-06') ?: [];
if (count($results) !=  3 || !in_array($resourcea, array_column($results, "ref")) || !in_array($resourceb, array_column($results, "ref")) || !in_array($resourcec, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// SUBTEST B
// Exclude all dates
$results = do_search('testdate:rangestart1975-03-25end1982-02-04');
if (is_array($results)) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// SUBTEST C
// Include 2 dates
$results = do_search('testdate:rangestart1976-06-01end2000-01-02');
if (count($results) !=  2 || !in_array($resourcea, array_column($results, "ref")) || !in_array($resourceb, array_column($results, "ref")) || in_array($resourcec, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

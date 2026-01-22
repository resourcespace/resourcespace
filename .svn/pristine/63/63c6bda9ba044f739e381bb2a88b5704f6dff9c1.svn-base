<?php

command_line_only();

// Create a test field
$field2502 = create_resource_type_field("field2502", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "field2502", true);

// Create 50 resources
$resources = [];
for ($n = 0; $n < 50; $n++) {
    $resources[$n] = create_resource(1, 0, -1);
    resource_random_jpg($resources[$n], 100, 100);
    update_field($resources[$n], $field2502, 'text2502');
}

// Default $fetchrows = -1
$results = search_get_previews('text2502', '', '', 0, -1, 'DESC', false, false, false, '', false, false, false, false, false, ["thm"]);
if (!is_array($results) || count($results) != 50) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// $fetchrows = 20 (results will be padded)
$results = search_get_previews('text2502', '', 'ref', 0, 20, "ASC", false, false, false, '', false, false, false, false, false, ["thm"]);
$testpath = get_resource_path($resources[0], false, 'thm');
if (
    count(array_column($results, "ref")) != 20 ||
    $results[0]["ref"] != $resources[0] ||
    !isset($results[0]["url_thm"]) ||
    $testpath != $results[0]["url_thm"]
) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// $fetchrows = [0,10]
$results = search_get_previews('text2502', '', 'ref', 0, [0,10], "ASC", false, false, false, '', false, false, false, false, false, ["thm"]);
$testpath = get_resource_path($resources[9], false, 'thm');
if (
    !isset($results["total"]) ||
    $results["total"] != 50 ||
    count($results["data"]) != 10 ||
    $results["data"][9]["ref"] != $resources[9] ||
    !isset($results["data"][9]["url_thm"]) ||
    $testpath != $results["data"][9]["url_thm"]
) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// $fetchrows = [40,15] - asking for more than available, should only get 10 returned
$results = search_get_previews('text2502', '', 'ref', 0, [40,15], "ASC", false, false, false, '', false, false, false, false, false, ["thm"]);
$testpath = get_resource_path($resources[49], false, 'thm');
if (
    !isset($results["total"]) ||
    $results["total"] != 50 ||
    count($results["data"]) != 10 ||
    $results["data"][9]["ref"] != $resources[49] ||
    !isset($results["data"][9]["url_thm"]) ||
    $testpath != $results["data"][9]["url_thm"]
) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

// $fetchrows = '2,5' - string format
$results = search_get_previews('text2502', '', 'ref', 0, '2,5', "ASC", false, false, false, '', false, false, false, false, false, ["thm"]);
$testpath = get_resource_path($resources[2], false, 'thm');
if (
    !isset($results["total"]) ||
    $results["total"] != 50 ||
    count($results["data"]) != 5 ||
    $results["data"][0]["ref"] != $resources[2] ||
    !isset($results["data"][0]["url_thm"]) ||
    $testpath != $results["data"][0]["url_thm"]
) {
    echo "ERROR - SUBTEST E\n";
    return false;
}

// $fetchrows = '2,' - invalid string format, should return no data
$results = search_get_previews('text2502', '', 'ref', 0, '2,');
if (
    !isset($results["total"]) ||
    $results["total"] != 50 ||
    count($results["data"]) != 0
) {
    echo "ERROR - SUBTEST E\n";
    return false;
}

// $fetchrows = ',5' - should return first 4 rows
$results = search_get_previews('text2502', '', 'ref', 0, ',4');
if (
    !isset($results["total"]) ||
    $results["total"] != 50 ||
    count($results["data"]) != 4
) {
    echo "ERROR - SUBTEST F\n";
    return false;
}

// $fetchrows = 'foo,5,' should return blank suggestion
$results = search_get_previews('text2502', '', 'ref', 0, 'foo,5,');
if ($results !== "") {
    echo "ERROR - SUBTEST G\n";
    return false;
}

return true;

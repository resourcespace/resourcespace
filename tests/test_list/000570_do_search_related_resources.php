<?php

command_line_only();

// Save state
$related_pushed_order_by_saved = $related_pushed_order_by ?? null;
$sort_fields_saved = $sort_fields ?? [];

// Set up related resources

$col_data_type = create_resource_type("Collection data");
$col_image_type = create_resource_type("Collection images");

$test943_resources = [];
$test943_resources[0] = create_resource($col_image_type, 0);
$test943_resources[1] = create_resource($col_data_type, 0);
$test943_resources[2] = create_resource($col_data_type, 0);
$test943_resources[3] = create_resource($col_data_type, 0);
relate_all_resources($test943_resources);

// SUBTEST A
$results = do_search('!related' . $test943_resources[0]);
$arr_expected = [$test943_resources[1], $test943_resources[2],$test943_resources[3]];
$arr_result = array_column($results, 'ref');
if (count($results) !== 3 || !match_values($arr_result, $arr_expected)) {
    echo "ERROR - SUBTEST A, expected [" . implode(",", $arr_expected) . "], got [" . implode(",", $arr_result) . "]\n";
    return false;
}

// SUBTEST B - Check relatedpushed and $related_pushed_order_by

// Create new field to order by and add data to the resources
$relatedorderfield = create_resource_type_field("Page943", 0, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, "page943");
$GLOBALS["related_pushed_order_by"] = $GLOBALS["sort_fields"][] = $relatedorderfield;

$resource_setvals = [
    2 => "1",
    3 => "2",
    1 => "3",
];
$nodes = [];
foreach ($resource_setvals as $resource => $value) {
    $nodes[$value] = set_node(null, $relatedorderfield, $value, '', 1000);
    add_resource_nodes($test943_resources[$resource], [$nodes[$value]]);
    update_resource_field_column($test943_resources[$resource], $relatedorderfield, (string)$value);
}

save_resource_type($col_data_type, ["push_metadata" => true]);
$results = do_search('!relatedpushed' . $test943_resources[0]);
$arr_expected = [$test943_resources[2], $test943_resources[3],$test943_resources[1]];
$arr_result = array_column($results, 'ref');

if (count($results) !== 3 || $arr_result !== $arr_expected) {
    echo "ERROR - SUBTEST B (\$related_pushed_order_by), expected [" . implode(",", $arr_expected) . "], got [" . implode(",", $arr_result) . "]\n";
    return false;
}

// Restore state
if (!is_null($related_pushed_order_by_saved)) {
    $GLOBALS["related_pushed_order_by_saved"] = $related_pushed_order_by_saved;
}
$GLOBALS["sort_fields"] = $sort_fields_saved;

return true;

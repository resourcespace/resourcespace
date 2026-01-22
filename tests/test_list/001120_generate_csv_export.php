<?php

command_line_only();

include_once __DIR__ . '/../../include/csv_export_functions.php';

// create new resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(2, 0);
$resources_list = [$resourcea, $resourceb, $resourcec];

debug("Resource A: " . $resourcea);
debug("Resource B: " . $resourceb);
debug("Resource C: " . $resourcec);

// create new 'person' field
$personfield = create_resource_type_field("Person", 0, FIELD_TYPE_CHECK_BOX_LIST, "person");
ps_query("UPDATE resource_type_field SET personal_data=1 WHERE ref = ?", ["i",$personfield]);

// create new 'Shape' field
$shapefield = create_resource_type_field("Shape", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "shape");

// create new 'Technical data' field  - not to be included in export
$technicalfield = create_resource_type_field("Technical data", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "technical");
ps_query("UPDATE resource_type_field SET include_in_csv_export=0 WHERE ref = ?", ["i",$technicalfield]);

// Add new nodes to person field
$pricenode = set_node(null, $personfield, "Vincent Price", '', 1000);
$cushingnode = set_node(null, $personfield, "Peter Cushing", '', 1000);
$leenode = set_node(null, $personfield, "Christopher Lee", '', 1000);

// Add nodes to resource a
add_resource_nodes($resourcea, array($pricenode, $cushingnode));
// Add node to resource b
add_resource_nodes($resourceb, array($pricenode, $leenode));
// Add nodes to resource c
add_resource_nodes($resourcec, array($leenode));

// Add text data to resources
update_field($resourcea, $shapefield, "Circle");
update_field($resourceb, $shapefield, "Triangle");
update_field($resourcec, $shapefield, "Square");

update_field($resourcea, $technicalfield, "Boring");
update_field($resourceb, $technicalfield, "Mundane");
update_field($resourcec, $technicalfield, "Unimportant");

$search_results = do_search("!list" . $resourcea . ":" . $resourceb . ":" . $resourcec);
$tempcsv = get_temp_dir() . "test.csv";

// A) Include only personal data fields
generateResourcesMetadataCSV(array_column($search_results, "ref"), true, false, $tempcsv);
$csvh = fopen($tempcsv, "r");
while (($row = fgetcsv($csvh, 5000)) !== false) {
    if (
        ($row[0] == $resourcea && !in_array("Vincent Price, Peter Cushing", $row))
        ||
        ($row[0] == $resourceb && !in_array("Vincent Price, Christopher Lee", $row))
        ||
        ($row[0] == $resourcec && !in_array("Christopher Lee", $row))
        ||
        ($row[0] == $resourcea && in_array("Circle", $row))
        ||
        ($row[0] == $resourceb && in_array("Triangle", $row))
        ||
        ($row[0] == $resourcec && in_array("Square", $row))
    ) {
        echo "ERROR - SUBTEST A\n";
        return false;
    }
}
fclose($csvh);
unlink($tempcsv);

// B) Not just personal data and not all fields
generateResourcesMetadataCSV(array_column($search_results, "ref"), false, false, $tempcsv);
$csvh = fopen($tempcsv, "r");
while (($row = fgetcsv($csvh, 5000)) !== false) {
    if (
        ($row[0] == $resourcea && in_array("Boring", $row))
        ||
        ($row[0] == $resourceb && in_array("Mundane", $row))
        ||
        ($row[0] == $resourcec && in_array("Unimportant", $row))
    ) {
        echo "ERROR - SUBTEST B\n";
        return false;
    }
}

fclose($csvh);
unlink($tempcsv);

// C) Include all fields
generateResourcesMetadataCSV(array_column($search_results, "ref"), false, true, $tempcsv);
$csvh = fopen($tempcsv, "r");
while (($row = fgetcsv($csvh, 5000)) !== false) {
    if (
        // Resource property
        (in_array($row[0], $resources_list) && !in_array($lang['status0'], $row))

        // Metadata fields
        || ($row[0] == $resourcea && !in_array("Boring", $row))
        || ($row[0] == $resourceb && !in_array("Mundane", $row))
        || ($row[0] == $resourcec && !in_array("Unimportant", $row))
    ) {
        echo "ERROR - SUBTEST C\n";
        return false;
    }
}

fclose($csvh);
unlink($tempcsv);

return true;

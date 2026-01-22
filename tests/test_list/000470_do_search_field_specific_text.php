<?php

command_line_only();

// Check search for field specific free text search e.g. '"title:launch party"'
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// Add plain text to resources
update_field($resourcea, 8, "Launch party");
update_field($resourceb, 8, "Book signing");
update_field($resourcec, 8, "Ship launch");

// TEST A: Do field specific search for 'launch party' (should return resource a)
$results = do_search('"title:launch party"');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    echo "TEST A ";
    return false;
}

// TEST B: Do field specific search for 'launch' (should return resources a and c)
$results = do_search('"title:launch"');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
) {
    echo "TEST B ";
    return false;
}

// TEST C: Omit keyword search:
$results = do_search("title:launch, -ship");
if (
    count($results) != 1 
    || !isset($results[0]['ref']) 
    || $results[0]['ref'] != $resourcea
) {
    echo "TEST C ";
    return false;
}

// Create and add a node with same name to resource b
$launchnode = set_node(null, 74, "launch", '', 1000);
add_resource_nodes($resourceb, array($launchnode));

// TEST D: This shouldn't return resource b
$results = do_search('"title:launch"');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
) {
    echo "TEST D ";
    return false;
}

// TEST E: Alternative keywords search:
$multi_keywords_results = do_search("title:Book;Ship");
if (
    !is_array($multi_keywords_results)
    || count($multi_keywords_results) < 2
    || $multi_keywords_results[1]["ref"] != $resourceb
    || $multi_keywords_results[0]["ref"] != $resourcec
) {
    echo "TEST E ";
    return false;
}

// TEST F: Alternative keywords with special characters:
$resource_field_verbatim_keyword_regex[8]="/^(?:[0-9]+(?:.[0-9]+)+)$/";
$resourced = create_resource(1, 0);
$resourcee = create_resource(1, 0);
update_field($resourced, 8, "123.456");
update_field($resourcee, 8, "234.567");
$results = do_search("title:123.456;234.567");
if (
    !is_array($results)
    || count($results) < 2
    || !in_array($resourced, array_column($results, "ref"))
    || !in_array($resourcee, array_column($results, "ref"))
) {
    echo "TEST F - i\n";
    return false;
}

$results = do_search("title:123.567;234.456");

if (
    is_array($results)
    && (
        in_array($resourced, array_column($results, "ref"))
        || in_array($resourcee, array_column($results, "ref"))
        )
) {
    echo "TEST F - ii\n";
}
unset($resource_field_verbatim_keyword_regex);

return true;

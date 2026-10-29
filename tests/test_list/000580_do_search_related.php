<?php

command_line_only();


// Check for searching using related keywords and with keyword_relationships_one_way set

$keyword_relationships_one_way = false;

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);

// Add new node to field, use dummy countries in case dbstruct changes and avoid possible stems
$rggweqvdfrnode = set_node(null, 3, "rggweqvdfr", '', 1000);

// Add trdpqtskc node to resource a
add_resource_nodes($resourcea, array($rggweqvdfrnode));
save_related_keywords("rggweqvdfr", "fdgrefgfr");


// SUBTEST A
// Do search for related keyword 'fdgrefgfr' (should return resource a)
$results = do_search('fdgrefgfr');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// SUBTEST B
// Now create a new node with the related keyword and add it to resource b
$fdgrefgfrnode = set_node(null, 3, "fdgrefgfr", '', 1000);
add_resource_nodes($resourceb, array($fdgrefgfrnode));

// Do search for first keyword 'rggweqvdfr' (should return both resources a and  b)
$results = do_search('rggweqvdfr');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref'])
    ||
    !match_values(array_column($results, 'ref'), array($resourcea, $resourceb))
) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// SUBTEST C
// Now set $keyword_relationships_one_way. Also need to wipe the related keyword cache!
$keyword_relationships_one_way = true;
unset($related_keywords_cache);

// Do search for 'fdgrefgfr' again, should only return resource b
$results = do_search('fdgrefgfr');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// SUBTEST D
// Do search for 'rggweqvdfr' again - should still return resources a and b
$results = do_search('rggweqvdfr');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref'])
    ||
    !match_values(array_column($results, 'ref'), array($resourcea, $resourceb))
) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

$keyword_relationships_one_way = false;
return true;

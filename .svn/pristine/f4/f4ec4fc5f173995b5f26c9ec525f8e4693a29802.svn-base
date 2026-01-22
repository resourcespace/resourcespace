<?php

command_line_only();


// create 3 new resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);
debug("Resource A: " . $resourcea);
debug("Resource B: " . $resourceb);
debug("Resource C: " . $resourcec);

// Add new nodes to field
$joeynode = set_node(null, 73, "Joey", '', 1000);
$johnnynode = set_node(null, 73, "Johnny", '', 1000);
$deedeenode = set_node(null, 73, "Dee Dee", '', 1000);
debug("node1: " . $joeynode . "\n");
debug("node2: " . $johnnynode . "\n");
debug("node3: " . $deedeenode . "\n");

// Add two nodes to resource a
add_resource_nodes($resourcea, array($johnnynode, $deedeenode));

// Add Joey node to resource b
add_resource_nodes($resourceb, array($joeynode));

// Add Johnny node to resource c
add_resource_nodes($resourcec, array($johnnynode));

// straight search of ref
debug("searching for resource by ref " . $resourcea);
$results = do_search($resourcea);

if (!isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    return false;
}
debug("Successfully searched for resource by resource id");

// search for 'Joey' (should be just resource b)
$results = do_search('@@' . $joeynode);
if (count($results) !== 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    return false;
}
debug("Successfully searched for resource by node");

// search for 'Johnny' (should return both resources a and c)
$results = do_search('@@' . $johnnynode);
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
) {
    return false;
}

// search for 'Johnny' AND 'Dee Dee' (should be just resource a)
$results = do_search('@@' . $johnnynode . ' @@' . $deedeenode);
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    return false;
}
debug("Successfully searched for resources with two nodes");

// search for everything (to get the count)
$results = do_search('');
$total = count($results);
// search for everything but 'Dee Dee' (should be n-1)
$results = do_search('@@!' . $deedeenode);
// there should be a difference of 1
if (count($results) != $total - 1) {
    return false;
}
debug("Successfully searched for resources excluding node");

// search for 'Johnny' or 'Dee Dee', should get 2 results
$results = do_search('@@' . $johnnynode . '@@' . $deedeenode);
if (count($results) != 2 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec || $results[1]['ref'] != $resourcea) {
    return false;
}
debug("Successfully searched for resources with either of two nodes");

// search for 'Johnny' and NOT 'Dee Dee' (should be resource c)
$results = do_search('@@' . $johnnynode . ' @@!' . $deedeenode);
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec) {
    return false;
}
debug("Successfully searched for resources with one node and NOT another node");

// search for 'Dee Dee' (should be resource a)
$results = do_search('"Dee Dee"');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    return false;
}
debug("Successfully searched for resources with quoted node string");

// Add a node containing stop words and check nothing was indexed.
$stop_list_check_node = set_node(null, 73, join(" ", $noadd), '', 1000);
if (ps_value("SELECT count(*) value FROM node_keyword WHERE node=?", array("i",$stop_list_check_node), 0) > 0) {
    echo "Kewords were indexed that are in the stop list.";
    print_r(ps_array("SELECT keyword value FROM keyword WHERE ref IN (SELECT keyword FROM node_keyword WHERE node=?)", array("i", $stop_list_check_node), 0));
    return false;
}

// Check that searches work with $resource_field_verbatim_keyword_regex
global $resource_field_verbatim_keyword_regex;
$resource_field_verbatim_keyword_regex_cache = $resource_field_verbatim_keyword_regex ?? null;
$resource_field_verbatim_keyword_regex = [51 => "/.+/"];

// search for 'Johnny' AND 'Dee Dee' (should be just resource a)
$results = do_search('@@' . $johnnynode . ' @@' . $deedeenode);
if (!is_array($results) || count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    echo "Verbatim keyword regex ";
    return false;
}
debug("Successfully searched for resources with two nodes");

if ($resource_field_verbatim_keyword_regex_cache == null) {
    unset($resource_field_verbatim_keyword_regex);
} else {
    $resource_field_verbatim_keyword_regex = $resource_field_verbatim_keyword_regex_cache;
}

// Check that using update_field to add nodes to resource returns false
$errors = array();
$fixedfields = ps_array("select ref value from resource_type_field where type in (" . implode(",", $FIXED_LIST_FIELD_TYPES) . ")", array()); // No need to use PS params for constants
foreach ($fixedfields as $fixedfield) {
    update_field($resourcea, $fixedfield, 'DUMMY STRING', $errors);
    if (!is_array($errors)) {
        echo "Using update_field should return false if updating a node field";
        return false;
    }
}


return true;

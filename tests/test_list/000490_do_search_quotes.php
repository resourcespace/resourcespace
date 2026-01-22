<?php

command_line_only();


// Check indexing of quoted text works ok

// First use non-fixed list data field
$resourcea = create_resource(1, 0);

// create new field
$quotefield = create_resource_type_field("Quoted text test", 0, FIELD_TYPE_TEXT_BOX_MULTI_LINE, "quotetest", 1);

$quotedata = "\"romeo lima foxtrot.\" said the author";

update_field($resourcea, $htmlfield, $quotedata);

// Do search for 'lima romeo' (should return resource a)
$results = do_search("lima romeo");
if (!is_array($results) || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Do quoted search for quoted 'romeo lima' (should include resource a)
$results = do_search("\"romeo lima\"");
if (!is_array($results) || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// Now test using nodes
$resourceb = create_resource(1, 0);
$quotenode = set_node(null, 3, $quotedata, '', 1000);

// Add node to resource b
add_resource_nodes($resourceb, array($quotenode));

// Do search for 'lima romeo' (should return resource b)
$results = do_search("lima romeo");
if (!is_array($results) || !in_array($resourceb, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// Do search for quoted 'romeo lima' (should return resource b)
$results = do_search("\"romeo lima\"");
if (!is_array($results) || !in_array($resourceb, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

// Do search for quoted 'romeo foxtrot' (shouldn't return resource a or b)
$results = do_search("\"romeo foxtrot\"");
if (is_array($results) && (in_array($resourcea, array_column($results, "ref")) || in_array($resourceb, array_column($results, "ref")))) {
    echo "ERROR - SUBTEST E\n";
    return false;
}

// Do search for quoted 'romeo lima foxtrot said the author' should return resource a and b)
$results = do_search("\"romeo lima foxtrot said the author\"");
if (!is_array($results) || !in_array($resourceb, array_column($results, "ref")) || !in_array($resourceb, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST F\n";
    return false;
}

return true;

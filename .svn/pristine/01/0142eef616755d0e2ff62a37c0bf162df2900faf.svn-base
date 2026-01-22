<?php

command_line_only();


// Check searching html text works ok

// First use non-fixed list data field
$resourcea = create_resource(1, 0);

// create new html field
$htmlfield = create_resource_type_field("HTML test", 0, FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE, "htmltest", 1);

$htmldata = "<div id='header'>
  <ul>
    <li><a href='https://www.resourcespace.com' target='_blank' >Open Source Digital Asset Management</a></li>
  </ul>
</div>";

update_field($resourcea, $htmlfield, $htmldata);

// Do search for 'open source' (should return resource a)
$results = do_search('open source');
if (!is_array($results) || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Do search for 'resourcespace' (should include resource a)
$results = do_search('resourcespace');
if (!is_array($results) || !in_array($resourcea, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// Now use nodes
$resourceb = create_resource(1, 0);
$htmlnode = set_node(null, 3, $htmldata, '', 1000);

// Add node to resource b
add_resource_nodes($resourceb, array($htmlnode));

// Do search for 'open source' (should return resource b)
$results = do_search('open source');
if (!is_array($results) || !in_array($resourceb, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST C\n";
    return false;
}

// Do search for 'resourcespace' (should include resource b)
$results = do_search('resourcespace');
if (!is_array($results) || !in_array($resourceb, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST D\n";
    return false;
}

// Now use non html field

$resourcec = create_resource(1, 0);
$nonhtmlfield = create_resource_type_field("HTML test", 0, FIELD_TYPE_TEXT_BOX_MULTI_LINE, "nonhtmltest", 1);
update_field($resourcec, $nonhtmlfield, $htmldata);

// Do search for 'open source' (should return resource a)
$results = do_search('open source');
if (!is_array($results) || !in_array($resourcec, array_column($results, "ref"))) {
    echo "ERROR - SUBTEST E\n";
    return false;
}

// Do search for html element 'div'. Shouldn't return resource a, b or c otherwise html hasn't been removed before indexing
$results = do_search("div");
if (is_array($results) && (in_array($resourcea, array_column($results, "ref")) || in_array($resourceb, array_column($results, "ref")) || in_array($resourcec, array_column($results, "ref")))) {
    echo "ERROR - SUBTEST F\n";
    return false;
}

// Do search for html attribute 'href'. Shouldn't return resource a, b or c
$results = do_search("href");
if (is_array($results) && (in_array($resourcea, array_column($results, "ref")) || in_array($resourceb, array_column($results, "ref")) || in_array($resourcec, array_column($results, "ref")))) {
    echo "ERROR - SUBTEST G\n";
    return false;
}

return true;

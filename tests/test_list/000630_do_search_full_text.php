<?php

command_line_only();

// Check searching using full text index

// Create field
$longtextfield  = create_resource_type_field("Long text", 0, FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE, "test970longtext", true);

// create resources
$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// Add long text to resources
$padtext = str_repeat("indexed text", 50);
$indexedstring = "index970";
$fulltextstring = "extratext970";
$fulltextsplitstring = "quotedstart970 quotedend970";
$excludetext = "970exclude";

update_field($resourcea, $longtextfield, $indexedstring . " " . $padtext . " " . $fulltextstring);
update_field($resourceb, $longtextfield, $padtext . " " . $indexedstring . " " . $fulltextsplitstring);
update_field($resourcec, $longtextfield, $indexedstring . " " . $padtext . " " . $fulltextstring);
update_field($resourcea, 8, $excludetext);

// Test A: Standard search for "index970" (should return resources a and c)
$results = do_search($indexedstring);
if (!is_array($results) || !match_values(array_column($results, 'ref'), array($resourcea, $resourcec))) {
    echo "ERROR - subtest A ";
    return false;
}

// Test B: Standard search for "extratext970" (should return 0 resources)
$results = do_search($fulltextstring);
if (is_array($results)) {
    echo "ERROR - subtest B ";
    return false;
}

// Test A: Standard search for "index970" (should return resources a and c)
$results = do_search("\"" . FULLTEXT_SEARCH_PREFIX . ":" . $fulltextstring . "\"");
if (!is_array($results) || !match_values(array_column($results, 'ref'), array($resourcea, $resourcec))) {
    echo "ERROR - subtest C ";
    return false;
}

// Test D: Full text search for "quotedstart970 quotedend970" (should return resource b)
$results = do_search("\"" . FULLTEXT_SEARCH_PREFIX . ":" . FULLTEXT_SEARCH_QUOTES_PLACEHOLDER . $fulltextsplitstring . FULLTEXT_SEARCH_QUOTES_PLACEHOLDER . "\"");
if (!is_array($results) || count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    echo "ERROR - subtest D ";
    return false;
}

// Test E: Full text search for "index970"  (should return resources a, b and c)
$results = do_search("\"" . FULLTEXT_SEARCH_PREFIX . ":" . $indexedstring . "\"");
if (!is_array($results) || !match_values(array_column($results, 'ref'), array($resourcea, $resourceb, $resourcec))) {
    echo "ERROR - subtest E ";
    return false;
}

// Test F: Full text search for +index970 -"quotedstart970 quotedend970" (should return resources a and c)
$results = do_search("\"" . FULLTEXT_SEARCH_PREFIX . ":+" . $indexedstring . " -" . FULLTEXT_SEARCH_QUOTES_PLACEHOLDER . $fulltextsplitstring . FULLTEXT_SEARCH_QUOTES_PLACEHOLDER . "\"");
if (!is_array($results) || !match_values(array_column($results, 'ref'), array($resourcea, $resourcec))) {
    echo "ERROR - subtest F ";
    return false;
}

// Test G: Full text search for +index970 and excluding standard indexed text (should return resource c)
$results = do_search("\"" . FULLTEXT_SEARCH_PREFIX . ":" . $fulltextstring . "\" -" . $excludetext);
if (!is_array($results) || count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcec) {
    echo "ERROR - subtest G ";
    return false;
}

return true;

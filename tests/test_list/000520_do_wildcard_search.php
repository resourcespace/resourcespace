<?php

command_line_only();
$wildcard_always_applied_cache = $wildcard_always_applied;

// Check wildcard search e.g. 'sam*' and 'super*'

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);

// Add new nodes to field, use dummy countries in case dbstruct changes
$sambalandnode = set_node(null, 3, "Sambaland", '', 1000);
$superlandnode = set_node(null, 3, "Superland", '', 1000);

// Add sambalandnode node to resource a
add_resource_nodes($resourcea, array($sambalandnode));
// Add superlandnode node to resource b
add_resource_nodes($resourceb, array($superlandnode));

// Do search for 'sam*' (should return resource a)
$results = do_search('sam*');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourcea) {
    return false;
}

// Do search for 'super*' (should return resource b)
$results = do_search('super*');
if (count($results) != 1 || !isset($results[0]['ref']) || $results[0]['ref'] != $resourceb) {
    return false;
}

// Add plain text to caption field for resource c
update_field($resourcec, 8, "Supermarine");

// Do search for 'super*' again (now should return both resources b and c)
$results = do_search('super*');
if (
    count($results) != 2 || !isset($results[0]['ref']) || !isset($results[1]['ref']) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb) ||
    ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec)
) {
    return false;
}

// Do search for 'sam* super*'(should return no resources and get a suggestion back)
$results = do_search('sam* super*');
if (is_array($results)) {
    return false;
}

// Add text to caption field for resources a and b and a node to resource c
update_field($resourcea, 8, "Supercilious");
update_field($resourceb, 8, "Samuel Taylor Coleridge");
$sambucalandnode = set_node(null, 3, "Sambucaland", '', 1000);
add_resource_nodes($resourcec, array($sambucalandnode));

// Do search for 'sam* super*' again(should return resources a, b and c)
$results = do_search('sam* super*');
if (
    count($results) != 3 || !isset($results[0]['ref']) || !isset($results[1]['ref']) || !isset($results[2]['ref']) ||
    ($results[0]['ref'] != $resourcea && $results[1]['ref'] != $resourcea && $results[2]['ref'] != $resourcea) ||
    ($results[0]['ref'] != $resourceb && $results[1]['ref'] != $resourceb && $results[2]['ref'] != $resourceb) ||
    ($results[0]['ref'] != $resourcec && $results[1]['ref'] != $resourcec && $results[2]['ref'] != $resourcec)
) {
    return false;
}

$test_cases = [
    ["search" => "plant", "node_value" => "plant"],
    ["search" => "a3ewd44a43-a80eha-a464t0-aba24r*", "node_value" => "a3ewd44a43-a80eha-a464t0-aba24r-acf2b011a0763w"],
    ["search" => "ab123_*.jpg", "node_value" => "ab123_junk.jpg", "field" => 51],
    ["search" => "title:book*", "node_value" => "booking form", "field" => 8],
    ["search" => "originalfilename:dog_photo-1.jpg", "node_value" => "dog_photo-1.jpg", "field" => 51],
    ["search" => "title:pumpkin.patch", "node_value" => "pumpkin.patch", "field" => 8],
    ["search" => "title:up-at-em", "node_value" => "up-at-em", "field" => 8],
    ["search" => "title:2025.04*", "node_value" => "2025.04.13", "field" => 8],
    ["search" => "123.1*", "node_value" => "123.124.125"],
    ["search" => "1998.327.370.*", "node_value" => "1998", "include_resource" => false],
    ["search" => "2010.69*", "node_value" => "2010.701", "include_resource" => false],
    ["search" => "2010.69*", "node_value" => "2010.690.3.450"],
    ["search" => "*98.327.370.*", "node_value" => "1998.327.370.456"],
    ["search" => "bing.*.boom", "node_value" => "bing.bong.BOOM"],
    ["search" => "will.*", "node_value" => "will.i.am"], // Stop word compatibility
    ["search" => "title:123.45*;345.12*", "node_value" => "123.456", "field" => 8],
    ];

foreach ($test_cases as $case) {
    $wildcard_always_applied = true;
    if (!test_wildcard_search(
        $case["search"],
        $case["node_value"],
        $case["field"] ?? 8,
        $case["include_resource"] ?? true
        )) {
        return false;
    }
}

function test_wildcard_search(string $search, string $node_value, int $field, bool $include_resource = true): bool
{
    global $wildcard_always_applied;
    $resource = create_resource(1, 0);
    update_field($resource, $field, $node_value);
    $success = 0;

    for ($n = 0; $n <= 1; $n++) {
        $wildcard_always_applied = !$wildcard_always_applied;
        $results = is_array($search_result = do_search($search)) ? $search_result : [];

        if (in_array($resource,array_column($results,'ref')) ^ $include_resource) {
            return false;
        } else {
            $success++;
        }
    }

    return $success > 1;
}

# Test case for keywords search where word part is less than 3 characters and so not in full text index.
# Wildcard search for "look up above" should be full text index for "look" and "above" but use LIKE for "up".
$resource = create_resource(1, 0);
update_field($resource, 12, "look up above");
$result = do_search("look up above");
if (!is_array($result) || count($result) === 0) {
    echo "Search failed for short word - ";
    return false;
}

// teardown
$wildcard_always_applied = $wildcard_always_applied_cache;

return true;

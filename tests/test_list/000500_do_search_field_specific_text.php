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

// Alternative punctuated values must also work without verbatim keyword indexing.
$alternatives_field = create_resource_type_field(
    'Alternative values test',
    0,
    FIELD_TYPE_TEXT_BOX_MULTI_LINE,
    'alternatives_test',
    1
);
$alternative_resources = [];
foreach (['123.456', '234.567', '123.567', '123x456', '123.4567', '12.34'] as $value) {
    $alternative_resource = create_resource(1, 0);
    update_field($alternative_resource, $alternatives_field, $value);
    $alternative_resources[] = $alternative_resource;
}

$alternative_searches = [
    ['123.456;234.567', [0, 1]],
    ['123.456;missing', [0]],
    ['12.34;234.567', [1, 5]],
    ['123.456;234.567 title:Book', []],
];
foreach ([false, true] as $quote_search) {
    foreach ($alternative_searches as [$search_values, $expected_indexes]) {
        $search = 'alternatives_test:' . $search_values;
        // The additional field restriction is tested separately without quotes.
        if ($quote_search && strpos($search_values, ' ') !== false) {
            continue;
        }
        $results = do_search($quote_search ? '"' . $search . '"' : $search);
        $expected = array_map(static fn($index) => $alternative_resources[$index], $expected_indexes);
        if ($expected === [] && $results === '') {
            continue;
        }
        if (!is_array($results) || !match_values(array_column($results, 'ref'), $expected)) {
            echo 'TEST G: ' . $search . ($quote_search ? ' (quoted)' : '') . "\n";
            return false;
        }
    }
}

// General OR searches must preserve alternatives through form conversion and matching.
foreach ($alternative_searches as [$search_values, $expected_indexes]) {
    $saved_search_post = $_POST;
    try {
        $_POST = ['search' => $search_values];
        $search = search_form_to_search_query([], true);
    } finally {
        $_POST = $saved_search_post;
    }
    $results = do_search($search);
    $actual = is_array($results) ? array_intersect(array_column($results, 'ref'), $alternative_resources) : [];
    $expected = array_map(static fn($index) => $alternative_resources[$index], $expected_indexes);
    if (!match_values($actual, $expected)) {
        echo 'TEST I: General OR search: ' . $search_values . "\n";
        return false;
    }
}

$results = do_search('Book;Ship');
if (
    !is_array($results)
    || !in_array($resourceb, array_column($results, 'ref'))
    || !in_array($resourcec, array_column($results, 'ref'))
) {
    echo "TEST J: General keyword OR search\n";
    return false;
}

$saved_alternatives_permissions = $userpermissions;
$saved_alternatives_hidden_fields_cache = $hidden_fields_cache;
$userpermissions[] = 'f-' . $alternatives_field;
$hidden_fields_cache = null;
try {
    foreach (['alternatives_test:123.456;234.567', '"alternatives_test:123.456;234.567"'] as $search) {
        if (do_search($search) !== false) {
            echo "TEST H: Hidden field search\n";
            return false;
        }
    }
    $results = do_search('123.456;234.567');
    if (is_array($results) && array_intersect(array_column($results, 'ref'), $alternative_resources) !== []) {
        echo "TEST K: General OR search must exclude hidden fields\n";
        return false;
    }
} finally {
    $userpermissions = $saved_alternatives_permissions;
    $hidden_fields_cache = $saved_alternatives_hidden_fields_cache;
}

return true;

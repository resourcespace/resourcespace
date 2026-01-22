<?php

command_line_only();

$resourcea = create_resource(1, 0);
$resourceb = create_resource(1, 0);
$resourcec = create_resource(1, 0);
$resourced = create_resource(1, 0);

$node996 = set_node(null, 3, "000996_special_search", '', 1000);
add_resource_nodes($resourcea, array($node996));

ps_query(
    "UPDATE resource SET file_checksum='2d2d737cf0655b875a8f5649944779f8' WHERE ref IN (?,?,?)",
    ["i",$resourcea,"i",$resourceb,"i",$resourcec]
);

ps_query(
    "UPDATE resource SET file_path = '' WHERE ref = ?",
    ["i", $resourcec]
);

ps_query(
    "UPDATE resource SET file_path = 'test123.jpg' WHERE ref = ?",
    ["i", $resourced]
);

$use_cases = [
    [
        "name"          => "!list search for single resource",
        "search_string" => "!list$resourcea",
        "results"       => [$resourcea],
    ],
    [
        "name"          => "!list search for single resource and node",
        "search_string" => "!list$resourcea,@@$node996",
        "results"       => [$resourcea],
    ],
    [
        "name"          => "!list search for single resource excluded by node",
        "search_string" => "!list$resourceb,@@$node996",
        "results"       => [],
    ],
    [
        "name"          => "!unused search with node",
        "search_string" => "!unused,@@$node996",
        "results"       => [$resourcea],
    ],
    [
        "name"          => "!duplicates for specific resource search",
        "search_string" => "!duplicates$resourceb",
        "results"       => [$resourcea,$resourceb,$resourcec],
    ],
    [
        "name"          => "!duplicates for specific resource search with node",
        "search_string" => "!duplicates$resourceb,@@$node996",
        "results"       => [$resourcea],
    ],
    [
        "name"          => "!noningested search for resource with file_path set but not an empty string",
        "search_string" => "!noningested",
        "results"       => [$resourced],
    ],
];

foreach ($use_cases as $use_case) {
    $results = do_search(
        $use_case["search_string"],
        "",
        "relevance",
        "0",
        -1,
        "desc",
        false,
        DEPRECATED_STARSEARCH,
        false,
        false,
        "",
        false,
        true,
        true
    );
    
    $results = array_column($results, "ref");
    sort($results);
    sort($use_case["results"]);
    if ($results != $use_case["results"]) {
        echo "ERROR - SUBTEST " . $use_case["name"] . "  ";
        return false;
    }
}
// Tear down
unset($use_cases, $resourcea, $resourceb, $resourcec, $resourced, $results);

return true;

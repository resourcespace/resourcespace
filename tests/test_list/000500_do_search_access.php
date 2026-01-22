<?php

command_line_only();


// Set up
$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);
$resource_c = create_resource(1, 0);

update_field($resource_a, $view_title_field, "integrationTest_926");
update_field($resource_b, $view_title_field, "integrationTest_926");
update_field($resource_c, $view_title_field, "integrationTest_926");

ps_query("UPDATE resource SET access = 0 WHERE ref = ?", ["i",$resource_a]);
ps_query("UPDATE resource SET access = 1 WHERE ref = ?", ["i",$resource_b]);
ps_query("UPDATE resource SET access = 1 WHERE ref = ?", ["i",$resource_c]);

$test_case = function ($access, $assertion) {
    $results = do_search('integrationTest_926', '', 'relevance', '0', -1, 'desc', false, 0, false, false, '', false, true, false, false, false, $access);

    if (is_array($results) && count($results) == $assertion) {
        return true;
    }

    return false;
};


if (!$test_case("bad input", 3)) {
    echo "Search for access levels using a bad input - ";
    return false;
}


if (!$test_case(null, 3)) {
    echo "Search for all access levels - ";
    return false;
}

if (!$test_case(1, 2)) {
    echo "Search for Restricted resources - ";
    return false;
}


// Tear down
unset($test_case);

return true;

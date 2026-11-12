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


// Selection reconciliation must retain search permissions and leave inaccessible members alone.
$selection = create_collection($userref, 'Search selection reconciliation');
foreach ([$resource_a, $resource_b, $resource_c] as $selected_resource) {
    add_resource_to_collection($selected_resource, $selection);
}
$matches = do_search('integrationTest_926', access: 1, return_refs_only: true, resource_collection: $selection);
if (array_diff(array_column($matches, 'ref'), [$resource_b, $resource_c]) !== [] || count($matches) !== 2) {
    echo 'Selection-constrained search changed access filtering - ';
    return false;
}
$state = reconcile_selection_collection($selection, $matches);
if ($state['empty'] || array_values($state['removed']) !== [$resource_a]
    || !match_values(get_collection_resources($selection), [$resource_b, $resource_c])) {
    echo 'Selection cleanup or remaining state incorrect - ';
    return false;
}

$saved_permissions = $userpermissions;
$saved_search_states = $search_all_workflow_states;
try {
    $userpermissions = ['s', 'g'];
    $search_all_workflow_states = false;
    ps_query('UPDATE resource SET access = 2 WHERE ref = ?', ['i', $resource_c]);
    $state = reconcile_selection_collection($selection, []);
    if (!$state['empty'] || !match_values(get_collection_resources($selection), [$resource_c])) {
        echo 'Hidden selection member was removed or counted as visible - ';
        return false;
    }
} finally {
    $userpermissions = $saved_permissions;
    $search_all_workflow_states = $saved_search_states;
    ps_query('UPDATE resource SET access = 1 WHERE ref = ?', ['i', $resource_c]);
    delete_collection($selection);
}

// Count distinct access combinations, not joined rows or only distinct resource refs.
$saved_permissions = $userpermissions;
try {
    foreach ([0, 0, 1] as $custom_access_level) {
        ps_query('INSERT INTO resource_custom_access (resource, usergroup, access) VALUES (?, ?, ?)',
            ['i', $resource_a, 'i', $usergroup, 'i', $custom_access_level]);
        ps_query('INSERT INTO resource_custom_access (resource, user, access) VALUES (?, ?, ?)',
            ['i', $resource_a, 'i', $userref, 'i', $custom_access_level]);
    }
    $userpermissions = ['s', 'g', 'f*'];
    $page = do_search('integrationTest_926', fetchrows: [0, 1]);
    if ($page['total'] !== 6) {
        echo 'Search total lost access variants or counted duplicate joins - ';
        return false;
    }
} finally {
    $userpermissions = $saved_permissions;
    ps_query('DELETE FROM resource_custom_access WHERE resource = ?', ['i', $resource_a]);
}

// Tear down
unset($test_case);

return true;

<?php

command_line_only();


$web_root = dirname(dirname(__DIR__));

$resource = create_resource(1, 0);
update_field($resource, 8, "Title field8");
$uk_country = set_node(null, 3, 'United Kingdom', '', '');
add_resource_nodes($resource, array($uk_country));

update_fieldx(3);
update_fieldx(8);
$search_results = do_search($resource);

if (empty($search_results) || $search_results[0]['ref'] != $resource) {
    // If code gets here something else is wrong with the functions used above
    return false;
}

// Use case: should see field8 and field3 elements
if (!isset($search_results[0]['field8']) || !isset($search_results[0]['field3'])) {
    return false;
}

$original_user_data = $userdata;
$test_user_data = $original_user_data;
$test_user_data['permissions'] .= ',f-3';
setup_user($test_user_data);



$search_results = do_search($resource);
if (empty($search_results) || $search_results[0]['ref'] != $resource) {
    // If code gets here something else is wrong with the functions used above
    return false;
}

// Use case: should see only field8 element
if (isset($search_results[0]['field3']) || !isset($search_results[0]['field8'])) {
    return false;
}



// Reset permissions for the test user
setup_user($original_user_data);

return true;
